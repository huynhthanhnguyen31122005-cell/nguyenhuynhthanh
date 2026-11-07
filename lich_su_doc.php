<?php
// FR4.6 – lịch sử đọc nhóm theo ngày; xóa từng mục hoặc toàn bộ
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/inc/doc_sach.php';

$u = yeu_cau_dang_nhap();
$uid = (int)$u['ma_nguoi_dung'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (($_POST['action'] ?? '') === 'xoa_het') $pdo->prepare('DELETE FROM lich_su_doc WHERE ma_nguoi_dung = ?')->execute([$uid]);
    else $pdo->prepare('DELETE FROM lich_su_doc WHERE ma_lich_su = ? AND ma_nguoi_dung = ?')->execute([(int)($_POST['id'] ?? 0), $uid]);
    chuyen_huong('lich_su_doc.php');
}
$st = $pdo->prepare('SELECT l.ma_lich_su, l.ngay_doc, l.thoi_diem_doc, c.ma_chuong, c.tieu_de, s.ma_sach, s.ten_sach FROM lich_su_doc l
    JOIN chuong c ON c.ma_chuong = l.ma_chuong JOIN sach s ON s.ma_sach = c.ma_sach WHERE l.ma_nguoi_dung = ? ORDER BY l.ngay_doc DESC, l.thoi_diem_doc DESC');
$st->execute([$uid]);
$nhom = [];
foreach ($st as $r) $nhom[$r['ngay_doc']][] = $r;
trang_dau('Lịch sử đọc', ['main' => 'wrap hep']);
?>
<h1>Lịch sử đọc</h1>
<?php if (!$nhom): ?><p class="muted">Bạn chưa đọc chương nào.</p><?php else: ?>
<form method="post" data-xacnhan="Xóa toàn bộ lịch sử đọc?"><?= csrf_field() ?><input type="hidden" name="action" value="xoa_het"><button class="btn xam nho">Xóa toàn bộ lịch sử</button></form>
<?php foreach ($nhom as $ngay => $ds): ?>
  <h2><?= e(date('d/m/Y', strtotime($ngay))) ?></h2>
  <div class="panel" style="padding:6px 18px"><ul class="ds-chuong">
  <?php foreach ($ds as $r): ?>
    <li><span><a href="<?= e(url('doc.php?c=' . $r['ma_chuong'])) ?>"><?= e($r['ten_sach']) ?> – <?= e($r['tieu_de']) ?></a> <small>· <?= e(date('H:i', strtotime($r['thoi_diem_doc']))) ?></small></span>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['ma_lich_su'] ?>"><button class="btn nho xam">Xóa</button></form></li>
  <?php endforeach; ?></ul></div>
<?php endforeach; endif; ?>
<?php trang_cuoi();
