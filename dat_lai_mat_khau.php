<?php
// FR1.3 – Đặt mật khẩu mới bằng token; dùng xong thì xóa token
require_once 'auth.php';
require_once 'auth_giao_dien.php';

$token = (string)($_GET['token'] ?? $_POST['token'] ?? '');
$hop_le_dinh_dang = (bool)preg_match('/^[a-f0-9]{64}$/', $token);
$bam = $hop_le_dinh_dang ? hash('sha256', $token) : '';

$ma = false;
if ($hop_le_dinh_dang) {
    $st = $pdo->prepare(
        "SELECT ma_nguoi_dung FROM nguoi_dung
         WHERE ma_dat_lai_mat_khau = ? AND han_dat_lai_mat_khau > NOW() AND trang_thai = 'HOAT_DONG'"
    );
    $st->execute([$bam]);
    $ma = $st->fetchColumn();
}

$loi = [];
if ($ma && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $mk  = (string)($_POST['mat_khau'] ?? '');
    $mk2 = (string)($_POST['nhap_lai_mat_khau'] ?? '');
    if ($e = kiem_tra_mat_khau_moi($mk)) $loi[] = $e;
    if ($mk !== $mk2) $loi[] = 'Mật khẩu nhập lại không khớp.';

    if (!$loi) {
        $st = $pdo->prepare(
            'UPDATE nguoi_dung
             SET mat_khau_bam = ?, ma_dat_lai_mat_khau = NULL, han_dat_lai_mat_khau = NULL
             WHERE ma_nguoi_dung = ? AND ma_dat_lai_mat_khau = ?'
        );
        $st->execute([bam_mat_khau($mk), $ma, $bam]);
        if ($st->rowCount() === 1) {
            flash_dat('Đặt lại mật khẩu thành công. Hãy đăng nhập bằng mật khẩu mới.');
            chuyen_huong('dang_nhap.php');
        }
        $ma = false;   // token vừa bị dùng ở request khác
    }
}

auth_dau_trang('Đặt lại mật khẩu');
?>
<h1>Đặt lại mật khẩu</h1>
<?php if (!$ma): ?>
    <div class="loi">Liên kết không hợp lệ hoặc đã hết hạn.</div>
    <div class="phu"><a href="quen_mat_khau.php">Yêu cầu liên kết mới</a></div>
<?php else: hien_loi($loi); ?>
    <form method="POST" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <label for="mat_khau">Mật khẩu mới</label>
        <input type="password" id="mat_khau" name="mat_khau" autocomplete="new-password" required>
        <div class="goi-y">Tối thiểu <?= MAT_KHAU_MIN ?> ký tự.</div>
        <label for="nhap_lai_mat_khau">Nhập lại mật khẩu mới</label>
        <input type="password" id="nhap_lai_mat_khau" name="nhap_lai_mat_khau" autocomplete="new-password" required>
        <button type="submit" class="btn">Đổi mật khẩu</button>
    </form>
<?php endif;
auth_cuoi_trang();
