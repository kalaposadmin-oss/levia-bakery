<?php

declare(strict_types=1);

require __DIR__ . '/../lib/db.php';

date_default_timezone_set('Asia/Jakarta');

header('Content-Type: application/json');

function normalize_hours_schedule(string $json): array
{
    $decoded = json_decode($json, true);
    return is_array($decoded) ? $decoded : [];
}

function store_is_open_now(array $hours): array
{
    $dayMap = ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'];
    $dayKey = $dayMap[(int) date('w')] ?? 'mon';
    $today = $hours[$dayKey] ?? ['open' => '08:00', 'close' => '20:00', 'active' => 1];

    if (empty($today['active'])) {
        return ['allowed' => false, 'message' => 'Toko sedang libur hari ini. Request WhatsApp ditutup sementara.'];
    }

    $now = date('H:i');
    if ($now < ($today['open'] ?? '08:00') || $now > ($today['close'] ?? '20:00')) {
        return ['allowed' => false, 'message' => 'Toko sedang tutup. Request WhatsApp hanya tersedia saat jam operasional.'];
    }

    return ['allowed' => true, 'message' => ''];
}

function delivery_options(): array
{
    $decoded = json_decode((string) setting('delivery_options_json', ''), true);
    if (!is_array($decoded) || !$decoded) {
        return [
            ['value' => 'pickup', 'label' => 'Ambil Sendiri', 'needs_address' => false],
            ['value' => 'home_bake', 'label' => 'Home Bake', 'needs_address' => true],
            ['value' => 'gosend', 'label' => 'Gosend / Gojek', 'needs_address' => true],
        ];
    }

    return array_values(array_filter($decoded, fn($option) => is_array($option) && !empty($option['value']) && !empty($option['label'])));
}

try {
    verify_csrf();
    $cart = json_decode((string) ($_POST['cart'] ?? '[]'), true);
    if (!is_array($cart) || count($cart) === 0) {
        throw new RuntimeException('Keranjang kosong.');
    }

    $customerName = trim((string) ($_POST['customer_name'] ?? ''));
    if ($customerName === '') {
        throw new RuntimeException('Nama pelanggan wajib diisi.');
    }

    $hours = normalize_hours_schedule((string) setting('store_hours_json', ''));
    $storeState = store_is_open_now($hours);
    if (!$storeState['allowed']) {
        throw new RuntimeException($storeState['message']);
    }

    $deliveryOptions = delivery_options();
    $deliveryLabels = array_column($deliveryOptions, 'label', 'value');
    $deliveryNeedsAddress = array_column($deliveryOptions, 'needs_address', 'value');
    $deliveryMethod = trim((string) ($_POST['delivery_method'] ?? ($deliveryOptions[0]['value'] ?? 'pickup')));
    if ($deliveryMethod === '' || !isset($deliveryLabels[$deliveryMethod])) {
        $deliveryMethod = (string) ($deliveryOptions[0]['value'] ?? 'pickup');
    }

    $deliveryAddress = trim((string) ($_POST['delivery_address'] ?? ''));
    if (!empty($deliveryNeedsAddress[$deliveryMethod]) && $deliveryAddress === '') {
        throw new RuntimeException('Alamat pengiriman wajib diisi.');
    }

    $note = trim((string) ($_POST['note'] ?? ''));
    $notePrefix = 'Metode: ' . ($deliveryLabels[$deliveryMethod] ?? $deliveryMethod);
    if ($deliveryAddress !== '') {
        $notePrefix .= ' | Tujuan: ' . $deliveryAddress;
    }
    $note = trim($note === '' ? $notePrefix : $notePrefix . ' | Catatan: ' . $note);

    $pdo = db();
    $pdo->beginTransaction();

    $items = [];
    $subtotal = 0.0;
    foreach ($cart as $line) {
        $productId = (int) ($line['product_id'] ?? 0);
        $qty = max(1, (int) ($line['qty'] ?? 1));

        $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ? AND is_active = 1');
        $stmt->execute([$productId]);
        $product = $stmt->fetch();
        if (!$product) {
            throw new RuntimeException('Produk tidak ditemukan.');
        }
        if (($product['stock_status'] ?? '') === 'sold_out') {
            throw new RuntimeException($product['name'] . ' sedang habis.');
        }
        if (($product['stock_status'] ?? '') === 'limited' && (int) $product['stock'] > 0 && $qty > (int) $product['stock']) {
            throw new RuntimeException($product['name'] . ' maksimal ' . (int) $product['stock'] . ' pcs per request.');
        }

        $lineSubtotal = (float) $product['price'] * $qty;
        $subtotal += $lineSubtotal;
        $items[] = [$product, $qty, $lineSubtotal];
    }

    $orderCode = 'LB-' . date('ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
    $stmt = $pdo->prepare('INSERT INTO orders (order_code, customer_name, customer_phone, delivery_address, note, subtotal, total) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        $orderCode,
        $customerName,
        trim((string) ($_POST['customer_phone'] ?? '')),
        $deliveryAddress,
        $note,
        $subtotal,
        $subtotal,
    ]);
    $orderId = (int) $pdo->lastInsertId();

    foreach ($items as [$product, $qty, $lineSubtotal]) {
        $stmt = $pdo->prepare('INSERT INTO order_items (order_id, product_id, product_name, qty, price, subtotal) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$orderId, $product['id'], $product['name'], $qty, $product['price'], $lineSubtotal]);

    }

    $pdo->commit();
    echo json_encode(['ok' => true, 'order_code' => $orderCode]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
}
