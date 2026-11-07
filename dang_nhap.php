<?php
// FR1.2 – Đăng nhập
require_once 'auth.php';
require_once 'auth_giao_dien.php';

if (nguoi_dung_hien_tai()) chuyen_huong('index.php');

// Hash giả để thời gian phản hồi không lộ việc tài khoản có tồn tại hay không
const HASH_GIA = '$2y$12$0q3AsnjT2yLULHVZP7OdvOcovVwa/owaPclbCo4JYhsM04XGtCWCy';

$loi = [];
$dinh_danh = '';
$thong_bao = flash_lay();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $dinh_danh = trim((string)($_POST['dinh_danh'] ?? ''));
    $mk        = (string)($_POST['mat_khau'] ?? '');

    $st = $pdo->prepare(
        'SELECT ma_nguoi_dung, mat_khau_bam, trang_thai FROM nguoi_dung
         WHERE ten_dang_nhap = ? OR email = ? LIMIT 1'
    );
    $st->execute([$dinh_danh, $dinh_danh]);
    $nd = $st->fetch(PDO::FETCH_ASSOC);

    $dung_mk = password_verify($mk, $nd['mat_khau_bam'] ?? HASH_GIA);

    if (!$nd || !$dung_mk) {
        $loi[] = 'Sai tên đăng nhập/email hoặc mật khẩu.';
    } elseif ($nd['trang_thai'] === 'BI_KHOA') {
        $loi[] = 'Tài khoản của bạn đã bị khóa. Vui lòng liên hệ quản trị viên.';
    } else {
        if (password_needs_rehash($nd['mat_khau_bam'], PASSWORD_BCRYPT, ['cost' => 12])) {
            $pdo->prepare('UPDATE nguoi_dung SET mat_khau_bam = ? WHERE ma_nguoi_dung = ?')
                ->execute([bam_mat_khau($mk), $nd['ma_nguoi_dung']]);
        }
        $next = $_SESSION['next'] ?? 'index.php';
        bat_dau_session_dang_nhap((int)$nd['ma_nguoi_dung']);
        if (!preg_match('#^(admin/)?[a-z_]+\.php(\?[\w=&%.\-]*)?$#', $next)) $next = 'index.php';
        unset($_SESSION['next']);
        chuyen_huong($next);
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng Nhập - Waka</title>
    <style>
        :root {
            --primary-waka: #1abc9c;
            --primary-waka-hover: #16a085;
            --bg-color: #121212;
        }
        
        * { box-sizing: border-box; }
        
        html, body {
            margin: 0; min-height: 100%; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: var(--bg-color);
        }
        
        button, input { font: inherit; }
        button { border: 0; }
        
        .flow-page {
            position: relative; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 48px 16px; overflow: hidden;
            background: radial-gradient(circle at 50% 45%, rgba(26, 188, 156, .075), transparent 34%), linear-gradient(180deg, #181818 0%, #121212 55%, #0a0a0a 100%);
        }
        
        .flow-glow {
            position: absolute; left: 50%; top: 50%; width: 500px; height: 500px; transform: translate(-50%, -50%);
            border-radius: 50%; background: rgba(26, 188, 156, .065); filter: blur(110px); pointer-events: none; opacity: .7;
        }
        
        .flow-wrapper { 
            position: relative; width: 100%; max-width: 820px; display: flex; flex-direction: column; align-items: center; z-index: 10; 
        }
        
        .flow-orb { 
            position: absolute; top: -23px; left: 50%; transform: translateX(-50%); z-index: 30; width: 46px; height: 46px; 
        }
        
        .orb-core {
            position: absolute; inset: 2px; border-radius: 50%;
            background: radial-gradient(circle at 30% 24%, #e2fdf8 0%, #aef4e4 12%, #1abc9c 42%, #16a085 70%, #0d5f4f 100%);
            box-shadow: 0 0 20px rgba(26, 188, 156, .44), 0 0 50px rgba(26, 188, 156, .16);
        }
        .orb-highlight { position: absolute; left: 10px; top: 7px; width: 13px; height: 9px; transform: rotate(-20deg); border-radius: 50%; background: rgba(255, 255, 255, .75); filter: blur(2px); }
        .orb-ring { position: absolute; inset: -3px; border: 1px solid rgba(255, 255, 255, .12); border-radius: 50%; animation: orbRotate 7s linear infinite; }
        .orb-dot { position: absolute; right: 4px; top: 8px; width: 5px; height: 5px; border-radius: 50%; background: rgba(255, 255, 255, .7); filter: blur(1px); }
        @keyframes orbRotate { to { transform: rotate(360deg); } }
        
        .login-card {
            position: relative; width: 100%; display: flex; border: 1px solid rgba(26, 188, 156, .14); border-radius: 20px;
            background: linear-gradient(180deg, rgba(30, 30, 30, .97) 0%, rgba(20, 20, 20, .98) 43%, rgba(10, 10, 10, .99) 100%);
            box-shadow: 0 28px 90px rgba(0, 0, 0, .6), 0 0 60px rgba(26, 188, 156, .065); backdrop-filter: blur(24px);
        }

        .login-card::after {
            content: ''; position: absolute; top: 0; bottom: 0; left: 50%; width: 1px; background: rgba(255, 255, 255, 0.08); z-index: 10;
        }
        
        .card-glow {
            position: absolute; inset: 0 0 auto; height: 175px; width: 100%;
            background: radial-gradient(ellipse at center top, rgba(26, 188, 156, .15), transparent 70%); filter: blur(9px); opacity: 1; pointer-events: none;
        }
        
        .qr-section {
            width: 50%; padding: 40px 30px; display: flex; flex-direction: column; align-items: center; justify-content: center;
            text-align: center; position: relative; z-index: 10;
        }
        .qr-title { color: #fff; font-size: 16px; font-weight: 600; margin-bottom: 25px; }
        .qr-box { background: #fff; padding: 12px; border-radius: 12px; margin-bottom: 20px; box-shadow: 0 10px 25px rgba(0,0,0,0.3); }
        .qr-box img { width: 160px; height: 160px; display: block; }
        .qr-desc { color: rgba(255, 255, 255, 0.6); font-size: 13px; line-height: 1.5; padding: 0 10px; max-width: 280px; }

        .card-content { 
            width: 50%; position: relative; z-index: 10; padding: 40px 45px; 
            display: flex; flex-direction: column; justify-content: center; 
        }
        
        .login-header { text-align: center; margin-bottom: 25px; }
        .login-header h1 { margin: 0; color: #fff; font-size: 18px; font-weight: 600; }
        
        .alert { padding: 10px; border-radius: 8px; margin-bottom: 15px; font-size: 13px; text-align: center;}
        .alert-success { background: rgba(26, 188, 156, 0.1); color: var(--primary-waka); border: 1px solid var(--primary-waka); }
        .alert-error { background: rgba(231, 76, 60, 0.1); color: #e74c3c; border: 1px solid #e74c3c; }
        
        .login-form { display: flex; flex-direction: column; gap: 14px; }
        
        .input-wrap { position: relative; }
        .input-wrap input {
            width: 100%; height: 48px; padding: 0 16px; border: 1px solid rgba(255, 255, 255, 0.12); border-radius: 8px;
            outline: none; color: white; background: transparent; font-size: 14px; transition: .25s;
        }
        .input-wrap input:focus { border-color: rgba(26, 188, 156, .5); background: rgba(255, 255, 255, .02); }
        .input-wrap input::placeholder { color: rgba(255, 255, 255, 0.3); font-size: 13px; }
        
        .eye-button { position: absolute; right: 14px; top: 50%; transform: translateY(-50%); width: 20px; height: 20px; background: none; color: rgba(255, 255, 255, 0.4); cursor: pointer; transition: 0.3s; }
        .eye-button:hover { color: rgba(255, 255, 255, 0.8); }
        .eye-button svg { width: 100%; height: 100%; fill: none; stroke: currentColor; stroke-width: 1.8; }
        
        .forgot-link { display: block; text-align: right; margin-top: 2px; color: var(--primary-waka); font-size: 13px; font-weight: 500; text-decoration: none;}
        .forgot-link:hover { text-decoration: underline; }

        .terms-row { display: flex; align-items: flex-start; gap: 10px; margin-top: 5px; }
        .terms-checkbox {
            appearance: none; width: 18px; height: 18px; border: 1px solid rgba(255, 255, 255, 0.3); border-radius: 4px;
            background: transparent; cursor: pointer; position: relative; flex-shrink: 0; margin-top: 2px; transition: 0.2s;
        }
        .terms-checkbox:checked { background: var(--primary-waka); border-color: var(--primary-waka); }
        .terms-checkbox:checked::after { content: ''; position: absolute; left: 5px; top: 2px; width: 4px; height: 8px; border: solid white; border-width: 0 2px 2px 0; transform: rotate(45deg); }
        .terms-text { font-size: 12.5px; color: rgba(255, 255, 255, 0.7); line-height: 1.5; }
        .terms-text a { color: var(--primary-waka); text-decoration: none; font-weight: 600; }
        .terms-text a:hover { text-decoration: underline; }
        
        .signin-button {
            width: 100%; height: 45px; display: flex; align-items: center; justify-content: center; margin-top: 10px; border-radius: 25px;
            color: white; background: var(--primary-waka); font-size: 14px; font-weight: 600; cursor: pointer; transition: 0.3s;
        }
        .signin-button:hover { background: var(--primary-waka-hover); }
        
        .social-title { text-align: center; color: rgba(255, 255, 255, 0.8); font-size: 13px; font-weight: 500; margin: 15px 0 10px; }
        .social-buttons { display: flex; gap: 15px; }
        .social-button {
            flex: 1; height: 40px; display: flex; align-items: center; justify-content: center; gap: 8px; border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 25px;
            color: #fff; background: rgba(255, 255, 255, 0.03); font-size: 13px; font-weight: 600; cursor: pointer; transition: 0.3s; text-decoration: none;
        }
        .social-button:hover { background: rgba(255, 255, 255, 0.1); }
        .social-button svg { width: 16px; height: 16px; fill: currentColor; }
        
        .card-footer { text-align: center; padding: 25px 0 0; width: 100%; }
        .signup-text { margin: 0; color: #fff; font-size: 13.5px; font-weight: 500; }
        .signup-text a { color: var(--primary-waka); text-decoration: none; font-weight: 600; margin-left: 5px; }
        .signup-text a:hover { text-decoration: underline; color: var(--primary-waka-hover);}

        /* CSS Nút Về trang chủ */
        .back-home {
            position: absolute; top: 24px; left: 24px; display: flex; align-items: center; gap: 8px;
            color: rgba(255, 255, 255, 0.5); text-decoration: none; font-size: 13.5px; font-weight: 500;
            transition: 0.3s; z-index: 100; padding: 8px 16px; border-radius: 99px; 
            background: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.06);
        }
        .back-home:hover { 
            color: #fff; background: rgba(26, 188, 156, 0.15); border-color: rgba(26, 188, 156, 0.4); 
        }
        .back-home svg { width: 16px; height: 16px; fill: none; stroke: currentColor; stroke-width: 2; }

        @media (max-width: 768px) {
            .login-card { flex-direction: column; }
            .login-card::after { display: none; }
            .qr-section { width: 100%; border-bottom: 1px solid rgba(255, 255, 255, 0.08); padding: 30px; }
            .card-content { width: 100%; padding: 30px 20px; }
            .back-home { top: 12px; left: 12px; padding: 6px 12px; }
        }
    </style>
</head>
<body>

<main class="flow-page">
    <!-- Nút quay về trang chủ -->
    <a href="index.php" class="back-home">
        <svg viewBox="0 0 24 24"><path d="M19 12H5"></path><path d="M12 19l-7-7 7-7"></path></svg>
        Về trang chủ
    </a>

    <div class="flow-glow"></div>
    <div class="flow-wrapper">
        
        <div class="flow-orb">
            <div class="orb-core"></div>
            <div class="orb-highlight"></div>
            <div class="orb-ring"></div>
            <span class="orb-dot"></span>
        </div>

        <section class="login-card">
            <div class="card-glow"></div>
            
            <div class="qr-section">
                <div class="qr-title">Trải nghiệm app WAKA ngay!</div>
                <div class="qr-box">
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=160x160&data=https://waka.vn" alt="Mã QR Tải App">
                </div>
                <div class="qr-desc">
                    Bạn vui lòng quét mã QR để thực hiện cài app và trải nghiệm những tính năng dành riêng cho app nhé!
                </div>
            </div>

            <div class="card-content">
                <header class="login-header">
                    <h1>Đăng nhập với mật khẩu</h1>
                </header>

                <?php if ($thong_bao): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($thong_bao) ?></div>
                <?php endif; ?>
                
                <?php if (!empty($loi)): ?>
                    <div class="alert alert-error">
                        <?php foreach ($loi as $l): ?>
                            <div><?= htmlspecialchars($l) ?></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" class="login-form" novalidate>
                    <?= csrf_field() ?>
                    
                    <div class="input-wrap">
                        <input type="text" id="dinh_danh" name="dinh_danh" value="<?= htmlspecialchars($dinh_danh) ?>" autocomplete="username" placeholder="Tên đăng nhập/Số điện thoại" required>
                    </div>

                    <div class="input-wrap">
                        <input type="password" id="mat_khau" name="mat_khau" autocomplete="current-password" placeholder="Mật khẩu" required>
                        <button type="button" class="eye-button" id="togglePassword">
                            <svg viewBox="0 0 24 24">
                                <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                        </button>
                    </div>

                    <a href="quen_mat_khau.php" class="forgot-link">Quên mật khẩu</a>

                    <div class="terms-row">
                        <input type="checkbox" id="agree_terms" class="terms-checkbox" required checked>
                        <label for="agree_terms" class="terms-text">
                            Tôi đã đọc và đồng ý với <a href="#">Điều khoản sử dụng</a>, <a href="#">Quy chế hoạt động sàn TMĐT</a>, <a href="#">Chính sách bảo mật thông tin</a> của Waka
                        </label>
                    </div>

                    <button type="submit" class="signin-button">Đăng nhập</button>

                    <div class="social-title">Hoặc đăng nhập với</div>
                    <div class="social-buttons">
                        <a href="#" class="social-button">
                            <svg viewBox="0 0 24 24" style="fill: #1877F2;"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.469h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.469h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                            Facebook
                        </a>
                        <a href="#" class="social-button">
                            <svg viewBox="0 0 24 24"><path fill="#4285F4" d="M21.35 12.23c0-.72-.06-1.42-.18-2.09H12v3.96h5.24a4.48 4.48 0 0 1-1.94 2.94v2.45h3.14c1.84-1.69 2.91-4.18 2.91-7.26Z"/><path fill="#34A853" d="M12 21.75c2.63 0 4.84-.87 6.46-2.36l-3.14-2.45c-.87.58-1.98.93-3.32.93-2.55 0-4.7-1.72-5.47-4.03H3.28v2.53A9.75 9.75 0 0 0 12 21.75Z"/><path fill="#FBBC05" d="M6.53 13.84A5.86 5.86 0 0 1 6.22 12c0-.64.11-1.26.31-1.84V7.63H3.28A9.75 9.75 0 0 0 2.25 12c0 1.57.38 3.06 1.03 4.37l3.25-2.53Z"/><path fill="#EA4335" d="M12 6.13c1.43 0 2.71.49 3.72 1.45l2.79-2.79C16.83 3.2 14.62 2.25 12 2.25a9.75 9.75 0 0 0-8.72 5.38l3.25 2.53C7.3 7.85 9.45 6.13 12 6.13Z"/></svg>
                            Google
                        </a>
                    </div>
                </form>
            </div>
        </section>
        
        <div class="card-footer">
            <p class="signup-text">Bạn chưa có tài khoản? <a href="dang_ky.php">Đăng ký ngay</a></p>
        </div>
    </div>
</main>

<script>
    const togglePassword = document.getElementById('togglePassword');
    const password = document.getElementById('mat_khau');
    
    togglePassword.addEventListener('click', function (e) {
        const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
        password.setAttribute('type', type);
        
        if(type === 'text') {
            this.innerHTML = `<svg viewBox="0 0 24 24"><path d="M3 3l18 18"></path><path d="M10.6 10.6a2 2 0 0 0 2.8 2.8"></path><path d="M9.9 5.2A9.9 9.9 0 0 1 12 5c6.5 0 10 7 10 7a18 18 0 0 1-3.2 4.1"></path><path d="M6.1 6.1C3.5 7.8 2 12 2 12s3.5 7 10 7c1.6 0 3-.4 4.2-1"></path></svg>`;
        } else {
            this.innerHTML = `<svg viewBox="0 0 24 24"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"></path><circle cx="12" cy="12" r="3"></circle></svg>`;
        }
    });
</script>
</body>
</html>