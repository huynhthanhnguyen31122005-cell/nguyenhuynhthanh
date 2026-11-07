<?php
// FR1.4 – Đổi mật khẩu (phải đăng nhập)
require_once 'auth.php';
require_once 'auth_giao_dien.php';

$u = yeu_cau_dang_nhap();
$loi = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $cu  = (string)($_POST['mat_khau_cu'] ?? '');
    $moi = (string)($_POST['mat_khau_moi'] ?? '');
    $moi2 = (string)($_POST['nhap_lai_mat_khau_moi'] ?? '');

    $st = $pdo->prepare('SELECT mat_khau_bam FROM nguoi_dung WHERE ma_nguoi_dung = ?');
    $st->execute([$u['ma_nguoi_dung']]);
    $hash_cu = (string)$st->fetchColumn();

    if (!password_verify($cu, $hash_cu)) {
        $loi[] = 'Mật khẩu cũ không đúng.';
    } else {
        if ($e = kiem_tra_mat_khau_moi($moi)) $loi[] = $e;
        elseif ($moi === $cu)                 $loi[] = 'Mật khẩu mới phải khác mật khẩu cũ.';
        if ($moi !== $moi2)                   $loi[] = 'Mật khẩu nhập lại không khớp.';
    }

    if (!$loi) {
        $pdo->prepare(
            'UPDATE nguoi_dung
             SET mat_khau_bam = ?, ma_dat_lai_mat_khau = NULL, han_dat_lai_mat_khau = NULL
             WHERE ma_nguoi_dung = ?'
        )->execute([bam_mat_khau($moi), $u['ma_nguoi_dung']]);
        session_regenerate_id(true);
        flash_dat('Đổi mật khẩu thành công.');
        chuyen_huong('ho_so.php');
    }
}

auth_dau_trang('Đổi mật khẩu');
?>
<h1>Đổi mật khẩu</h1>
<?php hien_loi($loi); ?>
<form method="POST" novalidate>
    <?= csrf_field() ?>
    <label for="mat_khau_cu">Mật khẩu hiện tại</label>
    <input type="password" id="mat_khau_cu" name="mat_khau_cu" autocomplete="current-password" required>

    <label for="mat_khau_moi">Mật khẩu mới</label>
    <input type="password" id="mat_khau_moi" name="mat_khau_moi" autocomplete="new-password" required>
    <div class="goi-y">Tối thiểu <?= MAT_KHAU_MIN ?> ký tự, khác mật khẩu cũ.</div>

    <label for="nhap_lai_mat_khau_moi">Nhập lại mật khẩu mới</label>
    <input type="password" id="nhap_lai_mat_khau_moi" name="nhap_lai_mat_khau_moi" autocomplete="new-password" required>

    <button type="submit" class="btn">Đổi mật khẩu</button>
</form>
<div class="phu"><a href="ho_so.php">← Về hồ sơ</a></div>
<?php auth_cuoi_trang();
