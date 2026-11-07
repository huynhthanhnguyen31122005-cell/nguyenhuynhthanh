<?php
// FR4.1 – tủ sách: tab Tất cả/Yêu thích/Đã mua, tìm theo tên, sắp xếp; thả tim idempotent
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/inc/sach.php';
require_once __DIR__ . '/inc/doc_sach.php';

$u = yeu_cau_dang_nhap();
$uid = (int)$u['ma_nguoi_dung'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $ma = (int)($_POST['ma_sach'] ?? 0);
    $act = $_POST['action'] ?? '';
    $ton_tai = $pdo->prepare("SELECT 1 FROM sach WHERE ma_sach = ? AND tinh_trang <> 'TAM_AN'"); $ton_tai->execute([$ma]);
    if ($ton_tai->fetchColumn()) {
        if ($act === 'yeu_thich') tu_sach_dat_yeu_thich($pdo, $uid, $ma, (int)(($_POST['dat'] ?? 1) == 1));
        elseif ($act === 'them') { tu_sach_them($pdo, $uid, $ma); flash_dat('Đã thêm vào tủ sách.'); }
        elseif ($act === 'go') {
            $pdo->prepare('DELETE FROM thu_vien WHERE ma_nguoi_dung = ? AND ma_sach = ? AND da_mua = 0')->execute([$uid, $ma]);
        }
    }
    $back = (string)($_POST['quay_lai'] ?? 'tu_sach.php');
    chuyen_huong(preg_match('#^[a-z_]+\.php(\?[\w=&%.\-]*)?$#', $back) ? $back : 'tu_sach.php');
}
$tab = in_array($_GET['tab'] ?? '', ['yeu_thich', 'da_mua'], true) ? $_GET['tab'] : 'tat_ca';
$sap = in_array($_GET['sap'] ?? '', ['moi_doc', 'az'], true) ? $_GET['sap'] : 'moi_them';
$q = trim((string)($_GET['q'] ?? ''));
$p = [':u' => $uid, ':u2' => $uid];
$w = "tv.ma_nguoi_dung = :u AND s.tinh_trang <> 'TAM_AN'";
if ($tab === 'yeu_thich') $w .= ' AND tv.yeu_thich = 1';
if ($tab === 'da_mua') $w .= ' AND tv.da_mua = 1';
if ($q !== '') $w .= ' AND ' . sach_dieu_kien_tu_khoa($q, $p);
$o = ['moi_them' => 'tv.ngay_them DESC', 'moi_doc' => 'lan_doc_cuoi IS NULL, lan_doc_cuoi DESC', 'az' => 's.ten_sach ASC'][$sap];
$st = $pdo->prepare('SELECT ' . SACH_COT . ', tv.yeu_thich, tv.da_mua,
    (SELECT MAX(td.ngay_cap_nhat) FROM tien_do_doc td JOIN chuong c ON c.ma_chuong = td.ma_chuong WHERE td.ma_nguoi_dung = :u2 AND c.ma_sach = s.ma_sach) AS lan_doc_cuoi
    FROM thu_vien tv JOIN sach s ON s.ma_sach = tv.ma_sach JOIN tac_gia t ON t.ma_tac_gia = s.ma_tac_gia WHERE ' . $w . ' ORDER BY ' . $o);
$st->execute($p);
$ds = $st->fetchAll();
trang_dau('Tủ sách');
?>
<h1>Tủ sách của tôi</h1>
<div class="tab-bar">
<?php foreach (['tat_ca' => 'Tất cả', 'yeu_thich' => 'Yêu thích', 'da_mua' => 'Đã mua'] as $k => $t): ?><a class="<?= $tab === $k ? 'on' : '' ?>" href="?<?= e(http_build_query(['tab' => $k, 'q' => $q, 'sap' => $sap])) ?>"><?= $t ?></a><?php endforeach; ?></div>
<form class="thanh" method="get"><input type="hidden" name="tab" value="<?= e($tab) ?>">
  <input type="search" name="q" value="<?= e($q) ?>" placeholder="Tìm theo tên sách..." style="min-width:220px">
  <select name="sap"><?php foreach (['moi_them' => 'Mới thêm', 'moi_doc' => 'Mới đọc', 'az' => 'A–Z'] as $k => $t): ?><option value="<?= $k ?>" <?= $sap === $k ? 'selected' : '' ?>><?= $t ?></option><?php endforeach; ?></select>
  <button class="btn nho">Lọc</button></form>
<?php if (!$ds): ?><p class="muted">Chưa có sách nào ở mục này.</p><?php else: ?>
<div class="luoi">
<?php foreach ($ds as $s): $tiep = doc_tiep($pdo, $uid, (int)$s['ma_sach']); ?>
  <div><?= the_sach($s) ?>
    <div class="thanh" style="margin:8px 0 0;align-items:center">
      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="yeu_thich"><input type="hidden" name="ma_sach" value="<?= $s['ma_sach'] ?>"><input type="hidden" name="dat" value="<?= $s['yeu_thich'] ? 0 : 1 ?>"><input type="hidden" name="quay_lai" value="tu_sach.php?<?= e(http_build_query(['tab' => $tab, 'q' => $q, 'sap' => $sap])) ?>"><button class="nut-tron nho<?= $s['yeu_thich'] ? ' on' : '' ?>" title="Yêu thích" aria-label="Yêu thích"><svg viewBox="0 0 24 24"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1-1.1a5.5 5.5 0 0 0-7.8 7.8l1 1L12 21l7.8-7.6 1-1a5.5 5.5 0 0 0 0-7.8z"/></svg></button></form>
      <?php if ($tiep): ?><a class="btn nho" href="<?= e(url('doc.php?c=' . $tiep['ma_chuong'] . '&vt=' . $tiep['vi_tri_cuon'])) ?>">Đọc tiếp</a><?php endif; ?>
      <?php if ($s['da_mua']): ?><span class="tag HOAT_DONG">Đã mua</span>
      <?php else: ?><form method="post" data-xacnhan="Bỏ sách khỏi tủ sách?"><?= csrf_field() ?><input type="hidden" name="action" value="go"><input type="hidden" name="ma_sach" value="<?= $s['ma_sach'] ?>"><button class="btn nho xam" title="Bỏ khỏi tủ">✕</button></form><?php endif; ?>
    </div></div>
<?php endforeach; ?></div><?php endif; ?>
<?php trang_cuoi();
