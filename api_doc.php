<?php
// AJAX cho trang đọc: lưu tiến độ (FR4.3), thêm/xóa dấu trang (FR4.5). Chỉ POST + CSRF + đăng nhập.
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/inc/doc_sach.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_ra(['loi' => 'Method not allowed'], 405);
csrf_check();
$u = nguoi_dung_hien_tai();
if (!$u) json_ra(['loi' => 'Chưa đăng nhập'], 401);
$uid = (int)$u['ma_nguoi_dung'];
$act = $_POST['action'] ?? '';
try {
    if ($act === 'xoa_dau_trang') json_ra(['ok' => dau_trang_xoa($pdo, $uid, (int)($_POST['id'] ?? 0))]);
    $c = chuong_lay($pdo, (int)($_POST['c'] ?? 0));
    if (!$c) json_ra(['loi' => 'Chương không tồn tại'], 404);
    if (!quyen_chuong($c, $u, vip_con_han($pdo, $uid), so_huu($pdo, $uid, (int)$c['ma_sach']))['duoc_doc']) json_ra(['loi' => 'Không có quyền đọc chương này'], 403);
    $vt = vi_tri_hop_le($_POST['vt'] ?? null);
    if ($act === 'tien_do') { tien_do_luu($pdo, $uid, (int)$c['ma_chuong'], $vt); json_ra(['ok' => true]); }
    if ($act === 'them_dau_trang') {
        $id = dau_trang_them($pdo, $uid, (int)$c['ma_chuong'], $vt, (string)($_POST['ghi_chu'] ?? ''));
        json_ra(['ok' => true, 'id' => $id, 'tieu_de' => $c['tieu_de'], 'vt' => $vt, 'ghi_chu' => trim((string)($_POST['ghi_chu'] ?? ''))]);
    }
    json_ra(['loi' => 'Hành động không hợp lệ'], 400);
} catch (InvalidArgumentException $ex) {
    json_ra(['loi' => $ex->getMessage()], 422);
}
