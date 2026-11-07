<?php
/** Lõi dùng chung: session, CSRF, xác thực/phân quyền (Module 1), mail, flash, tiện ích. */
require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off']);
    session_start();
}

// ===== Tiện ích =====
function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function so($n): string { return number_format((float)$n, 0, ',', '.'); }
function chuyen_huong(string $url): never { header('Location: ' . $url); exit; }
function json_ra(array $d, int $code = 200): never {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($d, JSON_UNESCAPED_UNICODE);
    exit;
}
/** Đường dẫn gốc của ứng dụng so với DOCUMENT_ROOT ('' nếu ở gốc, '/waka' nếu ở thư mục con). */
function base_path(): string {
    static $b = null;
    if ($b === null) {
        $doc = realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '';
        $b = ($doc !== '' && str_starts_with(APP_ROOT, $doc)) ? str_replace('\\', '/', substr(APP_ROOT, strlen($doc))) : '';
    }
    return $b;
}
function url(string $p = ''): string { return base_path() . '/' . ltrim($p, '/'); }
/** Ảnh: http(s) giữ nguyên, còn lại tính từ gốc ứng dụng. */
function url_anh(?string $p, string $mac_dinh = 'HinhSach/banner.jpg'): string {
    $p = trim((string)$p);
    if ($p === '') $p = $mac_dinh;
    return preg_match('#^https?://#i', $p) ? $p : url($p);
}
/** Bỏ dấu tiếng Việt + chữ thường (tìm kiếm có dấu/không dấu, FR2.4). */
function bo_dau(string $s): string {
    $s = mb_strtolower(trim($s), 'UTF-8');
    if (class_exists('Normalizer')) $s = Normalizer::normalize($s, Normalizer::FORM_D);
    $s = preg_replace('/\p{Mn}+/u', '', $s);
    $s = str_replace(['đ', 'Đ'], 'd', $s);
    return trim(preg_replace('/\s+/u', ' ', $s));
}
function cache_lay(string $khoa, int $ttl, callable $tao) {
    $f = APP_ROOT . '/cache/' . preg_replace('/\W/', '_', $khoa) . '.json';
    if (is_file($f) && time() - filemtime($f) < $ttl) {
        $d = json_decode((string)file_get_contents($f), true);
        if (is_array($d)) return $d;
    }
    $d = $tao();
    if (is_dir(dirname($f))) @file_put_contents($f, json_encode($d, JSON_UNESCAPED_UNICODE), LOCK_EX);
    return $d;
}
function cache_xoa(): void { foreach (glob(APP_ROOT . '/cache/*.json') ?: [] as $f) @unlink($f); }

// ===== CSRF =====
function csrf_token(): string {
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">'; }
function csrf_check(): void {
    $gui = $_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($gui) || !hash_equals($_SESSION['csrf'] ?? '', $gui)) {
        http_response_code(419);
        exit('Phiên làm việc không hợp lệ hoặc đã hết hạn. Hãy tải lại trang và thử lại.');
    }
}

// ===== Người dùng hiện tại (FR1.6): đọc lại CSDL mỗi request nên khóa tài khoản có hiệu lực ngay =====
function nguoi_dung_hien_tai(): ?array {
    global $pdo;
    static $da = false, $nd = null;
    if ($da) return $nd;
    $da = true;
    $id = $_SESSION['uid'] ?? null;
    if (!$id) return null;
    $st = $pdo->prepare('SELECT ma_nguoi_dung, ten_dang_nhap, email, ho_ten, so_dien_thoai, ngay_sinh, gioi_tinh,
                                anh_dai_dien, vai_tro, trang_thai, so_du_hat_soi FROM nguoi_dung WHERE ma_nguoi_dung = ?');
    $st->execute([$id]);
    $row = $st->fetch();
    if (!$row || $row['trang_thai'] !== 'HOAT_DONG') { huy_session(); return null; }
    return $nd = $row;
}
function yeu_cau_dang_nhap(): array {
    $u = nguoi_dung_hien_tai();
    if (!$u) {
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
            $_SESSION['next'] = ltrim(substr($_SERVER['REQUEST_URI'] ?? '', strlen(base_path())), '/');
        }
        chuyen_huong(url('dang_nhap.php'));
    }
    return $u;
}
function bat_dau_session_dang_nhap(int $id): void {
    session_regenerate_id(true);
    unset($_SESSION['csrf']);
    $_SESSION['uid'] = $id;
}
function huy_session(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    if (session_status() === PHP_SESSION_ACTIVE) session_destroy();
}

// ===== Mật khẩu =====
function bam_mat_khau(string $mk): string { return password_hash($mk, PASSWORD_BCRYPT, ['cost' => 12]); }
function kiem_tra_mat_khau_moi(string $mk): ?string {
    if (mb_strlen($mk, 'UTF-8') < MAT_KHAU_MIN) return 'Mật khẩu phải có ít nhất ' . MAT_KHAU_MIN . ' ký tự.';
    if (strlen($mk) > MAT_KHAU_MAX_BYTE) return 'Mật khẩu quá dài (tối đa ' . MAT_KHAU_MAX_BYTE . ' byte).';
    return null;
}

// ===== Email: luôn ghi logs/mail.log khi APP_DEBUG, gửi SMTP nếu đã cấu hình =====
function gui_email(string $den, string $tieu_de, string $noi_dung): bool {
    if (APP_DEBUG) {
        @file_put_contents(APP_ROOT . '/logs/mail.log', '[' . date('c') . "] TO: $den\nSUBJECT: $tieu_de\n$noi_dung\n\n", FILE_APPEND | LOCK_EX);
    }
    if (SMTP_USER === '' || !is_file(APP_ROOT . '/vendor/autoload.php')) return APP_DEBUG;
    require_once APP_ROOT . '/vendor/autoload.php';
    $m = new PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $m->isSMTP(); $m->Host = 'smtp.gmail.com'; $m->SMTPAuth = true;
        $m->Username = SMTP_USER; $m->Password = SMTP_PASS;
        $m->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS; $m->Port = 587; $m->CharSet = 'UTF-8';
        $m->setFrom(SMTP_USER, 'Waka'); $m->addAddress($den);
        $m->Subject = $tieu_de; $m->Body = $noi_dung;
        $m->send();
        return true;
    } catch (Throwable $ex) {
        error_log('Gửi mail lỗi: ' . $ex->getMessage());
        return false;
    }
}

// ===== Flash =====
function flash_dat(string $msg, string $loai = 'ok'): void { $_SESSION['flash'] = $msg; $_SESSION['flash_loai'] = $loai; }
function flash_lay(): ?string {
    $m = $_SESSION['flash'] ?? null;
    $GLOBALS['flash_loai'] = $_SESSION['flash_loai'] ?? 'ok';
    unset($_SESSION['flash'], $_SESSION['flash_loai']);
    return $m;
}
function hien_flash(): void {
    $m = flash_lay();
    if ($m) echo '<div class="' . ($GLOBALS['flash_loai'] === 'loi' ? 'loi' : 'ok') . '">' . e($m) . '</div>';
}

// ===== Hội viên (dùng ở nhiều module) =====
/** Trả về ['trang_thai'=>CON_HAN|SAP_HET_HAN|HET_HAN|CHUA_DANG_KY, 'ngay_het_han', 'so_ngay'] (FR4.7). */
function vip_trang_thai(PDO $pdo, int $uid): array {
    $st = $pdo->prepare('SELECT MAX(ngay_het_han) han, TIMESTAMPDIFF(SECOND, NOW(), MAX(ngay_het_han)) giay
                         FROM dang_ky_hoi_vien WHERE ma_nguoi_dung = ?');
    $st->execute([$uid]);
    $r = $st->fetch();
    if (!$r || $r['han'] === null) return ['trang_thai' => 'CHUA_DANG_KY', 'ngay_het_han' => null, 'so_ngay' => 0];
    $g = (int)$r['giay'];
    return ['trang_thai' => $g <= 0 ? 'HET_HAN' : ($g < 7 * 86400 ? 'SAP_HET_HAN' : 'CON_HAN'),
            'ngay_het_han' => $r['han'], 'so_ngay' => max(0, (int)ceil($g / 86400))];
}
function vip_con_han(PDO $pdo, int $uid): bool {
    return in_array(vip_trang_thai($pdo, $uid)['trang_thai'], ['CON_HAN', 'SAP_HET_HAN'], true);
}
