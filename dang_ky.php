<?php
// FR1.1 – Đăng ký
require_once 'auth.php';
require_once 'auth_giao_dien.php';

if (nguoi_dung_hien_tai()) chuyen_huong('index.php');

$loi = [];
$v = ['ten_dang_nhap' => '', 'email' => '', 'ho_ten' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $v['ten_dang_nhap'] = trim((string)($_POST['ten_dang_nhap'] ?? ''));
    $v['email']         = mb_strtolower(trim((string)($_POST['email'] ?? '')), 'UTF-8');
    $v['ho_ten']        = trim((string)($_POST['ho_ten'] ?? ''));
    $mk  = (string)($_POST['mat_khau'] ?? '');
    $mk2 = (string)($_POST['nhap_lai_mat_khau'] ?? '');

    if ($v['ho_ten'] === '' || mb_strlen($v['ho_ten'], 'UTF-8') > 150) {
        $loi[] = 'Họ tên là bắt buộc (tối đa 150 ký tự).';
    }
    if (!preg_match('/^[A-Za-z0-9_.]{3,50}$/', $v['ten_dang_nhap'])) {
        $loi[] = 'Tên đăng nhập gồm 3–50 ký tự: chữ không dấu, số, dấu chấm hoặc gạch dưới.';
    }
    if (!filter_var($v['email'], FILTER_VALIDATE_EMAIL) || strlen($v['email']) > 150) {
        $loi[] = 'Email không hợp lệ.';
    }
    if ($e = kiem_tra_mat_khau_moi($mk)) $loi[] = $e;
    if ($mk !== $mk2) $loi[] = 'Mật khẩu nhập lại không khớp.';

    // Email & tên đăng nhập phải duy nhất
    if (!$loi) {
        $st = $pdo->prepare('SELECT ten_dang_nhap, email FROM nguoi_dung WHERE ten_dang_nhap = ? OR email = ?');
        $st->execute([$v['ten_dang_nhap'], $v['email']]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            if (strcasecmp($r['ten_dang_nhap'], $v['ten_dang_nhap']) === 0) $loi['ten'] = 'Tên đăng nhập đã được sử dụng.';
            if (strcasecmp($r['email'], $v['email']) === 0)                 $loi['mail'] = 'Email đã được sử dụng.';
        }
    }

    if (!$loi) {
        try {
            $st = $pdo->prepare(
                "INSERT INTO nguoi_dung (ten_dang_nhap, email, mat_khau_bam, ho_ten, vai_tro, trang_thai)
                 VALUES (?, ?, ?, ?, 'THANH_VIEN', 'HOAT_DONG')"
            );
            $st->execute([$v['ten_dang_nhap'], $v['email'], bam_mat_khau($mk), $v['ho_ten']]);
            flash_dat('Đăng ký thành công! Hãy đăng nhập.');
            chuyen_huong('dang_nhap.php');
        } catch (PDOException $ex) {
            if ($ex->getCode() === '23000') {      // trùng khóa duy nhất do 2 request đồng thời
                $loi[] = 'Email hoặc tên đăng nhập đã được sử dụng.';
            } else {
                throw $ex;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Đăng Ký - Waka</title>
    <style>
        :root {
            --primary-waka: #1abc9c;
            --primary-waka-hover: #16a085;
            --bg-color: #050b10;
        }

        * { box-sizing: border-box; }

        html, body {
            margin: 0; min-height: 100%; font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; background: var(--bg-color);
        }

        button, input { font: inherit; }
        button { border: 0; }

        .flow-page {
            position: relative; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 48px 16px; overflow: hidden;
            background: radial-gradient(circle at 50% 45%, rgba(26, 188, 156, .075), transparent 34%), linear-gradient(180deg, #07131b 0%, #050a10 55%, #03070b 100%);
        }

        .flow-glow {
            position: absolute; left: 50%; top: 50%; width: 430px; height: 430px; transform: translate(-50%, -50%); border-radius: 50%;
            background: rgba(26, 188, 156, .065); filter: blur(110px); pointer-events: none; transition: .8s cubic-bezier(.22, 1, .36, 1);
        }

        .flow-wrapper { position: relative; width: 100%; max-width: 410px; display: flex; justify-content: center; margin: 30px 0; }

        .flow-orb { position: absolute; top: -50px; z-index: 30; width: 46px; height: 46px; transition: .75s cubic-bezier(.22, 1, .36, 1); }

        .orb-core {
            position: absolute; inset: 2px; border-radius: 50%;
            background: radial-gradient(circle at 30% 24%, #d4f7f0 0%, #8ae8d4 12%, #1abc9c 42%, #128c74 70%, #0a5243 100%);
            box-shadow: 0 0 20px rgba(26, 188, 156, .44), 0 0 50px rgba(26, 188, 156, .16);
        }

        .orb-highlight { position: absolute; left: 10px; top: 7px; width: 13px; height: 9px; transform: rotate(-20deg); border-radius: 50%; background: rgba(255, 255, 255, .75); filter: blur(2px); }
        .orb-ring { position: absolute; inset: -3px; border: 1px solid rgba(255, 255, 255, .12); border-radius: 50%; animation: orbRotate 7s linear infinite; }
        .orb-dot { position: absolute; right: 4px; top: 8px; width: 5px; height: 5px; border-radius: 50%; background: rgba(255, 255, 255, .7); filter: blur(1px); }
        @keyframes orbRotate { to { transform: rotate(360deg); } }

        .signup-card {
            position: relative; width: 100%; height: 198px; overflow: hidden; border: 1px solid rgba(26, 188, 156, .14); border-radius: 22px;
            background: linear-gradient(180deg, rgba(22, 43, 56, .97) 0%, rgba(11, 27, 37, .98) 43%, rgba(7, 12, 22, .99) 100%);
            box-shadow: 0 28px 90px rgba(0, 0, 0, .6), 0 0 60px rgba(26, 188, 156, .065); backdrop-filter: blur(24px); 
            transition: height .72s cubic-bezier(.22, 1, .36, 1), border-radius .72s cubic-bezier(.22, 1, .36, 1);
        }

        .signup-card.expanded { height: 1060px; border-radius: 27px; }

        .card-glow {
            position: absolute; inset: 0 0 auto; height: 115px; background: radial-gradient(ellipse at center top, rgba(26, 188, 156, .2), transparent 67%); filter: blur(9px); transition: height .7s cubic-bezier(.22, 1, .36, 1);
        }
        .signup-card.expanded .card-glow { height: 180px; }

        .card-content { position: relative; z-index: 10; padding: 36px 28px 24px; }

        .signup-header { text-align: center; transition: transform .5s cubic-bezier(.22, 1, .36, 1); }
        .signup-header h1 { margin: 0; color: var(--primary-waka); font-size: 32px; font-weight: 600; letter-spacing: -.045em; text-shadow: 0 0 17px rgba(26, 188, 156, .14); }
        .signup-header p { margin: 6px 0 0; color: rgba(255, 255, 255, .45); font-size: 12px; font-weight: 500; }

        .compact-button {
            position: absolute; top: 121px; left: 50%; height: 38px; transform: translateX(-50%); display: flex; align-items: center; gap: 6px; padding: 0 20px;
            border: 1px solid rgba(26, 188, 156, .2); border-radius: 999px; color: #ecfeff; background: rgba(7, 53, 74, .75); box-shadow: 0 0 20px rgba(26, 188, 156, .12); font-size: 12px; font-weight: 600; cursor: pointer; transition: .3s;
        }
        .compact-button:hover { background: #0a4057; }
        .compact-button svg { width: 13px; height: 13px; fill: none; stroke: currentColor; stroke-width: 2; }
        .signup-card.expanded .compact-button { opacity: 0; pointer-events: none; transform: translate(-50%, 12px); }

        .signup-form { margin-top: 32px; display: flex; flex-direction: column; gap: 14px; opacity: 0; transform: translateY(14px); pointer-events: none; transition: opacity .42s cubic-bezier(.22, 1, .36, 1) .2s, transform .42s cubic-bezier(.22, 1, .36, 1) .2s; }
        .signup-card.expanded .signup-form { opacity: 1; transform: translateY(0); pointer-events: auto; }

        .form-field { opacity: 0; transform: translateY(12px); filter: blur(5px); transition: opacity .42s cubic-bezier(.22, 1, .36, 1), transform .42s cubic-bezier(.22, 1, .36, 1), filter .42s cubic-bezier(.22, 1, .36, 1); }
        .signup-card.expanded .form-field { opacity: 1; transform: translateY(0); filter: blur(0); }
        .form-field:nth-child(1) { transition-delay: .20s; }
        .form-field:nth-child(2) { transition-delay: .25s; }
        .form-field:nth-child(3) { transition-delay: .30s; }
        .form-field:nth-child(4) { transition-delay: .35s; }

        .form-field label { display: block; margin-bottom: 6px; color: rgba(255, 255, 255, .42); font-size: 11px; font-weight: 600; }
        .input-wrap { position: relative; }
        .input-wrap input { width: 100%; height: 45px; padding: 0 14px; border: 1px solid rgba(255, 255, 255, .07); border-radius: 11px; outline: none; color: white; background: rgba(255, 255, 255, .025); font-size: 13px; font-weight: 500; transition: .25s; }
        .input-wrap input:focus { border-color: rgba(26, 188, 156, .4); background: rgba(255, 255, 255, .04); box-shadow: 0 0 0 2px rgba(26, 188, 156, 0.1); }
        .input-wrap input::placeholder { color: rgba(255, 255, 255, .25); }

        .field-icon { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); width: 15px; height: 15px; fill: none; stroke: rgba(255, 255, 255, .25); stroke-width: 1.8; pointer-events: none; }
        .input-wrap:has(.field-icon) input { padding-left: 40px; }
        .eye-button { position: absolute; right: 14px; top: 50%; width: 18px; height: 18px; padding: 0; transform: translateY(-50%); color: rgba(255, 255, 255, .25); background: transparent; cursor: pointer; transition: .25s; }
        .eye-button:hover { color: rgba(255, 255, 255, .6); }
        .eye-button svg { width: 15px; height: 15px; fill: none; stroke: currentColor; stroke-width: 1.8; }
        .input-wrap:has(.eye-button) input { padding-right: 44px; }

        .strength-bars { display: flex; gap: 4px; margin-top: 8px; }
        .strength-bars span { height: 3px; flex: 1; border-radius: 999px; background: var(--primary-waka); opacity: .2; transform: scaleX(.92); transform-origin: left; transition: .25s; }
        .strength-bars span.active { opacity: 1; transform: scaleX(1); }

        .password-error { display: none; margin: 6px 0 0; color: rgba(252, 165, 165, .6); font-size: 11px; font-weight: 500; }
        .password-error.show { display: block; }
        .loi-php { color: #fca5a5; font-size: 12px; margin-bottom: 10px; text-align: center; }

        .terms-field { opacity: 0; transform: translateY(10px); transition: opacity .4s ease .4s, transform .4s ease .4s; margin-top: 4px; }
        .signup-card.expanded .terms-field { opacity: 1; transform: translateY(0); }
        .terms-row { display: flex; align-items: flex-start; gap: 10px; margin-bottom: 12px; }
        .terms-checkbox {
            appearance: none; width: 17px; height: 17px; border: 1px solid rgba(255, 255, 255, 0.2); border-radius: 4px;
            background: transparent; cursor: pointer; position: relative; flex-shrink: 0; margin-top: 2px; transition: 0.2s;
        }
        .terms-checkbox:checked { background: var(--primary-waka); border-color: var(--primary-waka); }
        .terms-checkbox:checked::after { content: ''; position: absolute; left: 4px; top: 1px; width: 4px; height: 8px; border: solid white; border-width: 0 2px 2px 0; transform: rotate(45deg); }
        .terms-text { font-size: 11.5px; color: rgba(255, 255, 255, 0.45); line-height: 1.5; }
        .terms-text a { color: var(--primary-waka); text-decoration: none; font-weight: 600; }
        .terms-text a:hover { text-decoration: underline; }

        .create-button {
            height: 45px; width: 100%; display: flex; align-items: center; justify-content: center; gap: 8px; border-radius: 999px; color: white; background: var(--primary-waka); box-shadow: 0 11px 25px rgba(26, 188, 156, .2); font-size: 14px; font-weight: bold; cursor: pointer; opacity: 0; transform: translateY(10px); transition: opacity .4s cubic-bezier(.22, 1, .36, 1) .45s, transform .4s cubic-bezier(.22, 1, .36, 1) .45s;
        }
        .signup-card.expanded .create-button { opacity: 1; transform: translateY(0); }
        .create-button:hover { background: var(--primary-waka-hover); }

        .social-title { text-align: center; color: rgba(255, 255, 255, 0.35); font-size: 12px; font-weight: 500; margin: 10px 0 0; opacity: 0; transform: translateY(10px); transition: opacity .4s ease .5s, transform .4s ease .5s; }
        .signup-card.expanded .social-title { opacity: 1; transform: translateY(0); }
        .social-buttons { display: flex; gap: 12px; opacity: 0; transform: translateY(10px); transition: opacity .4s ease .55s, transform .4s ease .55s; }
        .signup-card.expanded .social-buttons { opacity: 1; transform: translateY(0); }
        .social-button {
            flex: 1; height: 40px; display: flex; align-items: center; justify-content: center; gap: 8px; border: 1px solid rgba(255, 255, 255, 0.05); border-radius: 10px;
            color: rgba(255, 255, 255, 0.6); background: rgba(255, 255, 255, 0.02); font-size: 12px; font-weight: 600; cursor: pointer; transition: 0.3s; text-decoration: none;
        }
        .social-button:hover { background: rgba(255, 255, 255, 0.06); color: #fff; }
        .social-button svg { width: 15px; height: 15px; fill: currentColor; }

        .signin-text { padding-top: 6px; margin: 0; text-align: center; color: rgba(255, 255, 255, .25); font-size: 12px; font-weight: 500; opacity: 0; transform: translateY(6px); transition: opacity .35s ease .65s, transform .35s ease .65s; }
        .signup-card.expanded .signin-text { opacity: 1; transform: translateY(0); }
        .signin-text a { color: var(--primary-waka); text-decoration: none; font-weight: bold; }
        .signin-text a:hover { text-decoration: underline; color: var(--primary-waka-hover);}

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

        @media (max-width: 480px) {
            .signup-card.expanded { height: 1060px; }
            .card-content { padding-left: 22px; padding-right: 22px; }
            .signup-header h1 { font-size: 29px; }
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

      <section class="signup-card <?= !empty($loi) ? 'expanded' : '' ?>" id="signupCard">
        <div class="card-glow"></div>
        <div class="card-content">
          <header class="signup-header">
            <h1>WAKA</h1>
            <p>Tạo tài khoản và trải nghiệm sách ngay.</p>
          </header>

          <button type="button" class="compact-button" id="compactButton">
            <span>Đăng ký ngay</span>
            <svg viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"></path></svg>
          </button>

          <form id="signupForm" class="signup-form" method="POST" novalidate>
            <?= csrf_field() ?>

            <!-- Báo lỗi từ PHP -->
            <?php if (!empty($loi)): ?>
                <div class="loi-php">
                    <?php foreach ($loi as $l): ?>
                        <div><?= htmlspecialchars($l) ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="form-field">
              <label for="ho_ten">Họ và tên</label>
              <div class="input-wrap">
                <svg class="field-icon" viewBox="0 0 24 24">
                  <path d="M20 21a8 8 0 0 0-16 0"></path>
                  <circle cx="12" cy="7" r="4"></circle>
                </svg>
                <input id="ho_ten" name="ho_ten" type="text" value="<?= htmlspecialchars($v['ho_ten']) ?>" placeholder="Nhập họ và tên..." required />
              </div>
            </div>

            <div class="form-field">
              <label for="ten_dang_nhap">Tên đăng nhập</label>
              <div class="input-wrap">
                <svg class="field-icon" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="10"></circle>
                    <path d="M8 14s1.5 2 4 2 4-2 4-2"></path>
                    <line x1="9" y1="9" x2="9.01" y2="9"></line>
                    <line x1="15" y1="9" x2="15.01" y2="9"></line>
                </svg>
                <input id="ten_dang_nhap" name="ten_dang_nhap" type="text" value="<?= htmlspecialchars($v['ten_dang_nhap']) ?>" autocomplete="username" placeholder="Nhập tên đăng nhập..." required />
              </div>
            </div>

            <div class="form-field">
              <label for="email">Email</label>
              <div class="input-wrap">
                <svg class="field-icon" viewBox="0 0 24 24">
                  <rect x="3" y="5" width="18" height="14" rx="2"></rect>
                  <path d="m3 7 9 6 9-6"></path>
                </svg>
                <input id="email" name="email" type="email" value="<?= htmlspecialchars($v['email']) ?>" autocomplete="email" placeholder="you@example.com" required />
              </div>
            </div>

            <div class="form-field">
              <label for="mat_khau">Mật khẩu</label>
              <div class="input-wrap">
                <input id="mat_khau" name="mat_khau" type="password" autocomplete="new-password" placeholder="Tạo mật khẩu (Tối thiểu 8 ký tự)" required />
                <button type="button" class="eye-button" id="passwordToggle" aria-label="Show password">
                  <svg id="passwordEye" viewBox="0 0 24 24">
                    <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"></path>
                    <circle cx="12" cy="12" r="3"></circle>
                  </svg>
                </button>
              </div>
              <div class="strength-bars">
                <span></span><span></span><span></span>
              </div>
            </div>

            <div class="form-field">
              <label for="nhap_lai_mat_khau">Nhập lại mật khẩu</label>
              <div class="input-wrap">
                <input id="nhap_lai_mat_khau" name="nhap_lai_mat_khau" type="password" autocomplete="new-password" placeholder="Nhập lại mật khẩu" required />
                <button type="button" class="eye-button" id="confirmToggle" aria-label="Show password">
                  <svg id="confirmEye" viewBox="0 0 24 24">
                    <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"></path>
                    <circle cx="12" cy="12" r="3"></circle>
                  </svg>
                </button>
              </div>
              <p class="password-error" id="passwordError">Mật khẩu nhập lại không khớp.</p>
            </div>

            <div class="terms-field">
                <div class="terms-row">
                    <input type="checkbox" id="agree_terms" class="terms-checkbox" required checked>
                    <label for="agree_terms" class="terms-text">
                        Tôi đã đọc và đồng ý với <a href="#">Điều khoản sử dụng</a>, <a href="#">Quy chế hoạt động sàn TMĐT</a>, <a href="#">Chính sách bảo mật thông tin</a> của Waka
                    </label>
                </div>

                <div class="terms-row">
                    <input type="checkbox" id="agree_promo" class="terms-checkbox">
                    <label for="agree_promo" class="terms-text">
                        (Không bắt buộc) Tôi đồng ý cho Waka sử dụng dữ liệu cá nhân của tôi để gợi ý nội dung phù hợp và gửi thông tin khuyến mại qua App push, Email, SMS, Zalo ZNS. Tôi có thể rút lại đồng ý này bất kỳ lúc nào.
                    </label>
                </div>
            </div>

            <button type="submit" class="create-button" id="createButton">
              <span id="createText">Tạo tài khoản</span>
            </button>

            <div class="social-title">Hoặc đăng ký với</div>
            <div class="social-buttons">
                <a href="#" class="social-button">
                    <svg viewBox="0 0 24 24" style="fill: rgba(255,255,255,0.6);"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.469h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.469h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                    Facebook
                </a>
                <a href="#" class="social-button">
                    <svg viewBox="0 0 24 24"><path fill="rgba(255,255,255,0.6)" d="M21.35 12.23c0-.72-.06-1.42-.18-2.09H12v3.96h5.24a4.48 4.48 0 0 1-1.94 2.94v2.45h3.14c1.84-1.69 2.91-4.18 2.91-7.26Z"/><path fill="rgba(255,255,255,0.6)" d="M12 21.75c2.63 0 4.84-.87 6.46-2.36l-3.14-2.45c-.87.58-1.98.93-3.32.93-2.55 0-4.7-1.72-5.47-4.03H3.28v2.53A9.75 9.75 0 0 0 12 21.75Z"/><path fill="rgba(255,255,255,0.6)" d="M6.53 13.84A5.86 5.86 0 0 1 6.22 12c0-.64.11-1.26.31-1.84V7.63H3.28A9.75 9.75 0 0 0 2.25 12c0 1.57.38 3.06 1.03 4.37l3.25-2.53Z"/><path fill="rgba(255,255,255,0.6)" d="M12 6.13c1.43 0 2.71.49 3.72 1.45l2.79-2.79C16.83 3.2 14.62 2.25 12 2.25a9.75 9.75 0 0 0-8.72 5.38l3.25 2.53C7.3 7.85 9.45 6.13 12 6.13Z"/></svg>
                    Google
                </a>
            </div>

            <p class="signin-text">
              Đã có tài khoản? <a href="dang_nhap.php" id="signinButton">Đăng nhập ngay</a>
            </p>

          </form>
        </div>
      </section>
    </div>
  </main>

  <script>
    const signupCard = document.getElementById("signupCard");
    const compactButton = document.getElementById("compactButton");

    const passwordInput = document.getElementById("mat_khau");
    const confirmInput = document.getElementById("nhap_lai_mat_khau");

    const passwordToggle = document.getElementById("passwordToggle");
    const confirmToggle = document.getElementById("confirmToggle");

    const passwordEye = document.getElementById("passwordEye");
    const confirmEye = document.getElementById("confirmEye");

    const strengthBars = document.querySelectorAll(".strength-bars span");
    const passwordError = document.getElementById("passwordError");

    let expanded = signupCard.classList.contains("expanded"); // Nếu có lỗi thì tự mở
    let passwordVisible = false;
    let confirmVisible = false;

    function expandCard() {
      expanded = true;
      signupCard.classList.add("expanded");
      document.querySelector(".flow-glow").style.opacity = "0.95";
      document.querySelector(".flow-glow").style.transform = "translate(-50%, -50%) scale(1.06)";
    }

    compactButton.addEventListener("click", () => {
      if (!expanded) expandCard();
    });

    function createEyeSVG(visible) {
      if (visible) {
        return `
          <path d="M3 3l18 18"></path>
          <path d="M10.6 10.6a2 2 0 0 0 2.8 2.8"></path>
          <path d="M9.9 5.2A9.9 9.9 0 0 1 12 5c6.5 0 10 7 10 7a18 18 0 0 1-3.2 4.1"></path>
          <path d="M6.1 6.1C3.5 7.8 2 12 2 12s3.5 7 10 7c1.6 0 3-.4 4.2-1"></path>
        `;
      }
      return `
        <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"></path>
        <circle cx="12" cy="12" r="3"></circle>
      `;
    }

    passwordToggle.addEventListener("click", () => {
      passwordVisible = !passwordVisible;
      passwordInput.type = passwordVisible ? "text" : "password";
      passwordEye.innerHTML = createEyeSVG(passwordVisible);
    });

    confirmToggle.addEventListener("click", () => {
      confirmVisible = !confirmVisible;
      confirmInput.type = confirmVisible ? "text" : "password";
      confirmEye.innerHTML = createEyeSVG(confirmVisible);
    });

    function updatePasswordStrength() {
      const p = passwordInput.value;
      let strength = 0;
      if (p.length > 0) strength = 1;
      if (p.length >= 6) strength = 2;
      if (p.length >= 8) strength = 3;

      strengthBars.forEach((bar, index) => {
        bar.classList.toggle("active", strength >= index + 1);
      });
    }

    function validatePasswords() {
      const confirmValue = confirmInput.value;
      if (confirmValue.length > 0 && passwordInput.value !== confirmValue) {
        passwordError.classList.add("show");
      } else {
        passwordError.classList.remove("show");
      }
    }

    passwordInput.addEventListener("input", () => {
      updatePasswordStrength();
      validatePasswords();
    });

    confirmInput.addEventListener("input", validatePasswords);
  </script>
</body>
</html>