<?php

declare(strict_types=1);

$sessionPath = __DIR__ . '/../storage/sessions';

if (!is_dir($sessionPath)) {
    mkdir($sessionPath, 0775, true);
}

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_save_path($sessionPath);
    session_start();
}

function config(): array
{
    static $config = null;
    if ($config === null) {
        $config = require __DIR__ . '/../config.php';
    }
    return $config;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $db = config()['db'];
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        $db['host'],
        $db['port'],
        $db['database'],
        $db['charset']
    );

    $pdo = new PDO($dsn, $db['username'], $db['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function money(float|int|string $value): string
{
    return 'Rp ' . number_format((float) $value, 0, ',', '.');
}

function base_url(string $path = ''): string
{
    $base = rtrim((string) (config()['base_url'] ?? ''), '/');
    return $base . '/' . ltrim($path, '/');
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf(): void
{
    $token = $_POST['_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', (string) $token)) {
        http_response_code(419);
        exit('CSRF token invalid.');
    }
}

function admin(): ?array
{
    if (empty($_SESSION['admin_id'])) {
        return null;
    }

    $stmt = db()->prepare('SELECT * FROM admins WHERE id = ? LIMIT 1');
    $stmt->execute([$_SESSION['admin_id']]);
    $admin = $stmt->fetch();

    return $admin ?: null;
}

function require_admin(): array
{
    $admin = admin();

    if (!$admin) {
        header('Location: ' . base_url('admin/login.php'));
        exit;
    }

    return $admin;
}

function redirect(string $path): void
{
    header('Location: ' . base_url($path));
    exit;
}

function slugify(string $text): string
{
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?: '';

    return trim($text, '-') ?: 'item-' . time();
}

function setting(string $key, ?string $default = null): ?string
{
    if (!isset($GLOBALS['levia_settings_cache'])) {
        $GLOBALS['levia_settings_cache'] = db()->query('SELECT setting_key, setting_value FROM settings')->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    return array_key_exists($key, $GLOBALS['levia_settings_cache']) ? (string) $GLOBALS['levia_settings_cache'][$key] : $default;
}

function set_setting(string $key, string $value): void
{
    $stmt = db()->prepare('
        INSERT INTO settings (setting_key, setting_value)
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
    ');

    $stmt->execute([$key, $value]);
    $GLOBALS['levia_settings_cache'][$key] = $value;
    clear_storefront_cache();
}

function cache_path(string $key): string
{
    return __DIR__ . '/../storage/cache/' . preg_replace('/[^a-z0-9_-]/i', '-', $key) . '.cache';
}

function cache_remember(string $key, int $ttl, callable $callback): mixed
{
    $path = cache_path($key);
    if (is_file($path) && filemtime($path) >= time() - $ttl) {
        $value = @unserialize((string) file_get_contents($path), ['allowed_classes' => false]);
        if ($value !== false || file_get_contents($path) === serialize(false)) {
            return $value;
        }
    }

    $value = $callback();
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    @file_put_contents($path, serialize($value), LOCK_EX);
    return $value;
}

function clear_storefront_cache(): void
{
    $dir = __DIR__ . '/../storage/cache';
    foreach (glob($dir . '/*.cache') ?: [] as $file) {
        @unlink($file);
    }
}

function ensure_blog_schema(): void
{
    db()->exec("CREATE TABLE IF NOT EXISTS blogs (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(190) NOT NULL,
        slug VARCHAR(190) NOT NULL UNIQUE,
        eyebrow VARCHAR(120) NULL,
        excerpt VARCHAR(255) NULL,
        content LONGTEXT NULL,
        image VARCHAR(255) NULL,
        is_featured TINYINT(1) NOT NULL DEFAULT 0,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_blogs_active_featured (is_active, is_featured)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function sanitize_blog_html(string $html): string
{
    $html = trim($html);
    if ($html === '') {
        return '';
    }
    if (!str_contains($html, '<')) {
        return implode('', array_map(fn($p) => '<p>' . nl2br(e(trim($p))) . '</p>', array_filter(preg_split('/\R{2,}/', $html) ?: [])));
    }

    $doc = new DOMDocument('1.0', 'UTF-8');
    libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="utf-8" ?><div id="blog-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    $allowed = ['p','br','h2','h3','strong','b','em','i','u','span','ul','ol','li','blockquote','a','figure','img','figcaption'];
    $fontClass = static function (string $font): string {
        $font = strtolower(str_replace(['"', "'"], '', $font));
        return match (true) {
            str_contains($font, 'playfair') => 'font-display',
            str_contains($font, 'georgia') => 'font-georgia',
            str_contains($font, 'arial') => 'font-arial',
            str_contains($font, 'courier') => 'font-mono',
            default => 'font-sans',
        };
    };
    $nodes = iterator_to_array($doc->getElementsByTagName('*'));
    foreach (array_reverse($nodes) as $node) {
        if ($node->getAttribute('id') === 'blog-root') { continue; }
        if (strtolower($node->nodeName) === 'font') {
            $replacement = $doc->createElement('span');
            $replacement->setAttribute('class', $fontClass($node->getAttribute('face')));
            while ($node->firstChild) { $replacement->appendChild($node->firstChild); }
            $node->parentNode?->replaceChild($replacement, $node);
            $node = $replacement;
        }
        if (!in_array(strtolower($node->nodeName), $allowed, true)) {
            while ($node->firstChild) { $node->parentNode?->insertBefore($node->firstChild, $node); }
            $node->parentNode?->removeChild($node);
            continue;
        }
        $style = strtolower($node->getAttribute('style'));
        if (in_array($node->nodeName, ['p','h2','h3'], true) && preg_match('/text-align\s*:\s*(left|center|right)/', $style, $match)) {
            $node->setAttribute('class', 'align-' . $match[1]);
        }
        if ($node->nodeName === 'span') {
            $class = $node->getAttribute('class');
            if (preg_match('/font-family\s*:\s*([^;]+)/', $style, $fontMatch)) { $class = $fontClass($fontMatch[1]); }
            $node->setAttribute('class', in_array($class, ['font-sans','font-display','font-georgia','font-arial','font-mono'], true) ? $class : 'font-sans');
        }
        foreach (iterator_to_array($node->attributes ?? []) as $attribute) {
            if (!in_array($attribute->name, ['href','src','alt','class','target','rel','loading','decoding'], true)) {
                $node->removeAttribute($attribute->name);
            }
        }
        if ($node->nodeName === 'a') {
            $href = trim($node->getAttribute('href'));
            if (!preg_match('~^(https?://|mailto:|/|#)~i', $href)) { $node->removeAttribute('href'); }
            $node->setAttribute('rel', 'noopener noreferrer');
        }
        if ($node->nodeName === 'img') {
            $src = trim($node->getAttribute('src'));
            if (!preg_match('~^(uploads/|assets/|https?://)~i', $src)) { $node->parentNode?->removeChild($node); continue; }
            $node->setAttribute('loading', 'lazy');
            $node->setAttribute('decoding', 'async');
        }
        if ($node->nodeName === 'figure') {
            $classes = preg_split('/\s+/', trim($node->getAttribute('class'))) ?: [];
            $alignment = array_values(array_intersect($classes, ['image-left','image-center','image-right']))[0] ?? 'image-center';
            $node->setAttribute('class', $alignment);
            if (preg_match('/width\s*:\s*(\d{1,3})%/', $style, $widthMatch)) {
                $width = max(20, min(100, (int) $widthMatch[1]));
                $node->setAttribute('style', 'width:' . $width . '%');
            }
        }
        if (in_array($node->nodeName, ['p','h2','h3'], true)) {
            $class = $node->getAttribute('class');
            if (!in_array($class, ['align-left','align-center','align-right'], true)) { $node->removeAttribute('class'); }
        }
    }
    $root = $doc->getElementById('blog-root');
    $output = '';
    if ($root) { foreach ($root->childNodes as $child) { $output .= $doc->saveHTML($child); } }
    return trim($output);
}

function local_image_source(string $path): ?array
{
    $clean = ltrim((string) parse_url($path, PHP_URL_PATH), '/');
    if ($clean === '' || str_contains($clean, '..')) { return null; }
    $root = realpath(__DIR__ . '/..');
    $absolute = realpath(__DIR__ . '/../' . $clean);
    if (!$root || !$absolute || !is_file($absolute)) { return null; }
    $root = str_replace('\\', '/', $root) . '/';
    if (!str_starts_with(str_replace('\\', '/', $absolute), $root)) { return null; }
    $size = @getimagesize($absolute);
    if (!$size) { return null; }
    return ['path' => $clean, 'absolute' => $absolute, 'width' => (int) $size[0], 'height' => (int) $size[1], 'mime' => (string) ($size['mime'] ?? '')];
}

function load_gd_image(string $absolute, string $mime): mixed
{
    return match ($mime) {
        'image/jpeg' => @imagecreatefromjpeg($absolute),
        'image/png' => @imagecreatefrompng($absolute),
        'image/webp' => @imagecreatefromwebp($absolute),
        'image/avif' => function_exists('imagecreatefromavif') ? @imagecreatefromavif($absolute) : false,
        default => false,
    };
}

function optimized_image_variant(string $path, int $targetWidth, string $format = 'webp', int $quality = 80): ?string
{
    if (!extension_loaded('gd') || !in_array($format, ['webp', 'avif'], true)) { return null; }
    $info = local_image_source($path);
    if (!$info) { return null; }
    $width = max(160, min($info['width'], $targetWidth));
    $height = max(1, (int) round($info['height'] * ($width / $info['width'])));
    $stamp = (string) (@filemtime($info['absolute']) ?: 0);
    $hash = substr(sha1($info['path'] . '|' . $stamp . '|' . $width . '|' . $format . '|' . $quality), 0, 12);
    $stem = preg_replace('/[^a-z0-9_-]+/i', '-', pathinfo($info['path'], PATHINFO_FILENAME)) ?: 'image';
    $relative = 'uploads/optimized/' . $stem . '-' . $hash . '-w' . $width . '.' . $format;
    $absolute = __DIR__ . '/../' . $relative;
    if (is_file($absolute) && filesize($absolute) > 0) { return $relative; }
    $source = load_gd_image($info['absolute'], $info['mime']);
    if (!$source) { return null; }
    $output = imagecreatetruecolor($width, $height);
    imagealphablending($output, false);
    imagesavealpha($output, true);
    imagefill($output, 0, 0, imagecolorallocatealpha($output, 0, 0, 0, 127));
    imagecopyresampled($output, $source, 0, 0, 0, 0, $width, $height, $info['width'], $info['height']);
    $dir = dirname($absolute);
    if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
    $temporary = $absolute;
    $saved = $format === 'avif' && function_exists('imageavif')
        ? @imageavif($output, $temporary, max(35, min(70, $quality)))
        : ($format === 'webp' ? @imagewebp($output, $temporary, max(60, min(90, $quality))) : false);
    imagedestroy($source); imagedestroy($output);
    if (!$saved) { @unlink($temporary); return null; }
    return is_file($absolute) && filesize($absolute) > 0 ? $relative : null;
}

function responsive_image_data(string $path, array $widths = [480, 960, 1400], int $webpQuality = 80, int $avifQuality = 55): array
{
    $info = local_image_source($path);
    if (!$info) { return ['src' => $path, 'width' => null, 'height' => null, 'webp' => '', 'avif' => '']; }
    $widths = array_values(array_unique(array_filter(array_map('intval', $widths), fn(int $width): bool => $width > 0)));
    sort($widths);
    $widths = array_values(array_unique(array_map(fn(int $width): int => min($width, $info['width']), $widths)));
    $webp = []; $avif = [];
    foreach ($widths as $width) {
        $webpPath = optimized_image_variant($path, $width, 'webp', $webpQuality);
        if ($webpPath) { $webp[$width] = $webpPath; }
        // AVIF via GD is CPU-heavy on shared hosting, so enable it explicitly when the server has a fast encoder.
        if (getenv('LEVIA_ENABLE_AVIF') === '1') {
            $avifPath = optimized_image_variant($path, $width, 'avif', $avifQuality);
            if ($avifPath) { $avif[$width] = $avifPath; }
        }
    }
    return [
        'src' => $webp ? end($webp) : $path,
        'width' => $info['width'], 'height' => $info['height'],
        'webp' => implode(', ', array_map(fn($url, $width): string => $url . ' ' . $width . 'w', $webp, array_keys($webp))),
        'avif' => implode(', ', array_map(fn($url, $width): string => $url . ' ' . $width . 'w', $avif, array_keys($avif))),
    ];
}

function responsive_image_html(string $path, string $alt, array $options = []): string
{
    $data = responsive_image_data($path, $options['widths'] ?? [480, 960, 1400], (int) ($options['webp_quality'] ?? 80), (int) ($options['avif_quality'] ?? 55));
    $sizes = (string) ($options['sizes'] ?? '100vw');
    $loading = (string) ($options['loading'] ?? 'lazy');
    $priority = (string) ($options['fetchpriority'] ?? 'auto');
    $class = trim((string) ($options['class'] ?? ''));
    $parts = ['<picture>'];
    if ($data['avif'] !== '') { $parts[] = '<source type="image/avif" srcset="' . e($data['avif']) . '" sizes="' . e($sizes) . '">'; }
    if ($data['webp'] !== '') { $parts[] = '<source type="image/webp" srcset="' . e($data['webp']) . '" sizes="' . e($sizes) . '">'; }
    $parts[] = '<img src="' . e($data['src']) . '" alt="' . e($alt) . '"' . ($class !== '' ? ' class="' . e($class) . '"' : '') . ($data['width'] ? ' width="' . $data['width'] . '" height="' . $data['height'] . '"' : '') . ' loading="' . e($loading) . '" decoding="async" fetchpriority="' . e($priority) . '">';
    $parts[] = '</picture>';
    return implode('', $parts);
}
function upload_image(string $field, ?string $fallback = null, array $options = []): ?string
{
    if (empty($_FILES[$field]['name']) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return $fallback;
    }

    $allowed = ['image/jpeg', 'image/png', 'image/webp'];

    $mime = mime_content_type($_FILES[$field]['tmp_name']);

    if (!in_array($mime, $allowed, true) || !extension_loaded('gd')) {
        return $fallback;
    }
    if ((int) ($_FILES[$field]['size'] ?? 0) > 12 * 1024 * 1024) {
        return $fallback;
    }
    $dimensions = @getimagesize($_FILES[$field]['tmp_name']);
    if (!$dimensions || ((int) $dimensions[0] * (int) $dimensions[1]) > 30000000) {
        return $fallback;
    }

    $source = load_gd_image($_FILES[$field]['tmp_name'], $mime);
    if (!$source) {
        return $fallback;
    }

    if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
        $exif = @exif_read_data($_FILES[$field]['tmp_name']);
        $orientation = (int) ($exif['Orientation'] ?? 1);
        if ($orientation === 3) { $source = imagerotate($source, 180, 0); }
        if ($orientation === 6) { $source = imagerotate($source, -90, 0); }
        if ($orientation === 8) { $source = imagerotate($source, 90, 0); }
    }

    $sourceWidth = imagesx($source);
    $sourceHeight = imagesy($source);
    $maxWidth = max(320, (int) ($options['max_width'] ?? 1200));
    $maxHeight = max(320, (int) ($options['max_height'] ?? 1200));
    $scale = min(1, $maxWidth / $sourceWidth, $maxHeight / $sourceHeight);
    $width = max(1, (int) round($sourceWidth * $scale));
    $height = max(1, (int) round($sourceHeight * $scale));
    $output = imagecreatetruecolor($width, $height);
    imagealphablending($output, false);
    imagesavealpha($output, true);
    $transparent = imagecolorallocatealpha($output, 0, 0, 0, 127);
    imagefill($output, 0, 0, $transparent);
    imagecopyresampled($output, $source, 0, 0, 0, 0, $width, $height, $sourceWidth, $sourceHeight);

    $dir = __DIR__ . '/../uploads';

    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    $prefix = preg_replace('/[^a-z0-9_-]/i', '-', (string) ($options['prefix'] ?? 'image'));
    $name = $prefix . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.webp';
    $saved = imagewebp($output, $dir . '/' . $name, max(60, min(90, (int) ($options['quality'] ?? 82))));
    imagedestroy($source);
    imagedestroy($output);
    if (!$saved) {
        return $fallback;
    }

    $path = 'uploads/' . $name;
    responsive_image_data(
        $path,
        $options['responsive_widths'] ?? [480, 960, 1400],
        max(60, min(90, (int) ($options['quality'] ?? 82))),
        max(35, min(70, (int) ($options['avif_quality'] ?? 55)))
    );
    clear_storefront_cache();

    return $path;
}
