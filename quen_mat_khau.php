<?php
// FR1.3 – Quên mật khẩu: sinh token ngẫu nhiên, lưu BĂM token + hạn 30 phút, gửi qua email
require_once 'auth.php';
require_once 'auth_giao_dien.php';

if (nguoi_dung_hien_tai()) chuyen_huong('doi_mat_khau.php');

$da_gui = false;
$loi = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $email = mb_strtolower(trim((string)($_POST['email'] ?? '')), 'UTF-8');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $loi[] = 'Email không hợp lệ.';
    } else {
        $st = $pdo->prepare("SELECT ma_nguoi_dung FROM nguoi_dung WHERE email = ? AND trang_thai = 'HOAT_DONG'");
        $st->execute([$email]);
        $id = $st->fetchColumn();

        if ($id) {
            $token = bin2hex(random_bytes(32));          // 64 ký tự hex, gửi cho người dùng
            $bam   = hash('sha256', $token);             // chỉ lưu bản băm (vừa CHAR(64))
            $pdo->prepare(
                'UPDATE nguoi_dung
                 SET ma_dat_lai_mat_khau = ?, han_dat_lai_mat_khau = DATE_ADD(NOW(), INTERVAL ' . TOKEN_HAN_PHUT . ' MINUTE)
                 WHERE ma_nguoi_dung = ?'
            )->execute([$bam, $id]);

            gui_email(
                $email,
                'Đặt lại mật khẩu Waka',
                "Bạn vừa yêu cầu đặt lại mật khẩu.\n\nNhấn vào liên kết (hiệu lực " . TOKEN_HAN_PHUT . " phút):\n"
                . APP_URL . '/dat_lai_mat_khau.php?token=' . $token
                . "\n\nNếu không phải bạn yêu cầu, hãy bỏ qua email này."
            );
        }
        // Luôn báo giống nhau dù email có tồn tại hay không (chống dò tài khoản)
        $da_gui = true;
    }
}

auth_dau_trang('Quên mật khẩu');
?>
<h1>Quên mật khẩu</h1>
<?php if ($da_gui): ?>
    <div class="ok">Nếu email tồn tại trong hệ thống, chúng tôi đã gửi hướng dẫn đặt lại mật khẩu. Liên kết có hiệu lực <?= TOKEN_HAN_PHUT ?> phút.</div>
<?php else: hien_loi($loi); ?>
    <form method="POST" novalidate>
        <?= csrf_field() ?>
        <label for="email">Email đã đăng ký</label>
        <input type="email" id="email" name="email" autocomplete="email" required>
        <button type="submit" class="btn">Gửi liên kết đặt lại</button>
    </form>
<?php endif; ?>
<div class="phu"><a href="dang_nhap.php">← Quay lại đăng nhập</a></div>
<?php auth_cuoi_trang();
