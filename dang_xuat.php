<?php
// FR1.2 – Đăng xuất: hủy session (chỉ nhận POST + CSRF để tránh bị ép đăng xuất qua link/ảnh)
require_once 'auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    huy_session();
}
chuyen_huong('index.php');
