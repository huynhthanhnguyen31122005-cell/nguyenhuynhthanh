<?php
// FR4.5 – danh sách dấu trang, mở nhanh, xóa một hoặc tất cả (kiểm tra quyền sở hữu)
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/inc/doc_sach.php';

$u = yeu_cau_dang_nhap();
$uid = (int)$u['ma_nguoi_dung'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (($_POST['action'] ?? '') === 'xoa_het') $pdo->prepare('DELETE FROM dau_trang WHERE ma_nguoi_dung = ?')->execute([$uid]);
    else dau_trang_xoa($pdo, $uid, (int)($_POST['id'] ?? 0));
    chuyen_huong('dau_trang.php');
}
$nhom = [];
foreach (dau_trang_ds($pdo, $uid) as $d) $nhom[$d['ten_sach']][] = $d;
trang_dau('Dấu trang', ['main' => 'wrap hep']);
?>
<h1>Dấu trang</h1>
<?php if (!$nhom): ?><p class="muted">Chưa có dấu trang. Khi đọc, bấm “Thêm dấu trang” để lưu vị trí.</p><?php else: ?>
<form method="post" data-xacnhan="Xóa tất cả dấu trang?"><?= csrf_field() ?><input type="hidden" name="action" value="xoa_het"><button class="btn xam nho">Xóa tất cả</button></form>
<?php foreach ($nhom as $sach => $ds): ?>
  <h2><?= e($sach) ?></h2><div class="panel" style="padding:6px 18px"><ul class="ds-chuong">
  <?php foreach ($ds as $d): ?>
    <li><span><a href="<?= e(url('doc.php?c=' . $d['ma_chuong'] . '&vt=' . $d['vi_tri_cuon'])) ?>"><?= e($d['tieu_de']) ?> · <?= e($d['vi_tri_cuon']) ?>%</a> <small><?= e($d['ghi_chu'] ?? '') ?> · <?= e(date('d/m/Y H:i', strtotime($d['ngay_tao']))) ?></small></span>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$d['ma_dau_trang'] ?>"><button class="btn nho xam">Xóa</button></form></li>
  <?php endforeach; ?></ul></div>
<?php endforeach; endif; ?>
<?php trang_cuoi();
