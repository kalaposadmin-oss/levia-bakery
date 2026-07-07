<?php

declare(strict_types=1);

require __DIR__ . '/../lib/db.php';
require_admin();
header('Content-Type: application/json; charset=utf-8');

try {
    verify_csrf();
    if (empty($_FILES['image']['name'])) {
        throw new RuntimeException('Pilih gambar terlebih dahulu.');
    }
    $path = upload_image('image', null, ['prefix' => 'blog-content', 'max_width' => 1200, 'max_height' => 1200, 'quality' => 80, 'avif_quality' => 55, 'responsive_widths' => [480, 960, 1200]]);
    if (!$path) {
        throw new RuntimeException('Gambar tidak valid atau terlalu besar. Gunakan JPG, PNG, atau WebP maksimal 12 MB.');
    }
    echo json_encode(['ok' => true, 'path' => $path, 'preview_url' => '../' . $path], JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}
