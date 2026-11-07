<?php
// FR4.2 – đọc chương: kiểm tra quyền ở máy chủ TRƯỚC khi trả nội dung; FR4.3–4.6 tiến độ, dấu trang, lịch sử
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/inc/doc_sach.php';

$c = chuong_lay($pdo, (int)($_GET['c'] ?? 0));
if (!$c) { http_response_code(404); trang_dau('Không tìm thấy'); echo '<div class="panel">Chương không tồn tại hoặc đã bị ẩn.</div>'; trang_cuoi(); exit; }
$u = nguoi_dung_hien_tai();
$uid = $u ? (int)$u['ma_nguoi_dung'] : 0;
$sh = so_huu($pdo, $uid, (int)$c['ma_sach']);
$vip = $u && vip_con_han($pdo, $uid);
$q = quyen_chuong($c, $u, $vip, $sh);

$vt = 0.0; $dau_trang = [];
if ($q['duoc_doc'] && $u) {
    lich_su_ghi($pdo, $uid, (int)$c['ma_chuong']);                         // FR4.6
    $vt = isset($_GET['vt']) && is_numeric($_GET['vt']) ? max(0, min(100, (float)$_GET['vt'])) : tien_do_lay($pdo, $uid, (int)$c['ma_chuong']);
    $dau_trang = dau_trang_ds($pdo, $uid, (int)$c['ma_sach']);
}
$truoc = chuong_ke($pdo, $c, 'truoc'); $sau = chuong_ke($pdo, $c, 'sau');
$ds_chuong = $pdo->prepare('SELECT ma_chuong, tieu_de FROM chuong WHERE ma_sach = ? AND trang_thai = 1 ORDER BY so_thu_tu'); $ds_chuong->execute([$c['ma_sach']]); $ds_chuong = $ds_chuong->fetchAll();

trang_dau($c['tieu_de'] . ' – ' . $c['ten_sach'], ['main' => 'wrap', 'css' => []]);
?>
<div class="doc-thanh">
  <div><a href="<?= e(url('chi_tiet.php?id=' . $c['ma_sach'])) ?>">← <?= e($c['ten_sach']) ?></a>
    <select onchange="location='?c='+this.value" style="width:auto;margin-left:8px"><?php foreach ($ds_chuong as $x): ?><option value="<?= $x['ma_chuong'] ?>" <?= $x['ma_chuong'] == $c['ma_chuong'] ? 'selected' : '' ?>><?= e($x['tieu_de']) ?></option><?php endforeach; ?></select></div>
  <?php if ($q['duoc_doc'] && $c['dinh_dang'] !== 'SACH_NOI' && $c['dinh_dang'] !== 'TRUYEN_TRANH'): ?>
  <div class="doc-tuychinh" id="tuychinh">
    <button type="button" class="btn nho xam" data-size="-1">A−</button><button type="button" class="btn nho xam" data-size="1">A+</button>
    <select id="phong"><option value="serif">Serif</option><option value="sans">Sans</option><option value="mono">Mono</option></select>
    <select id="nen"><option value="toi">Nền tối</option><option value="sang">Nền sáng</option><option value="vang">Nền vàng</option></select>
  </div><?php endif; ?>
</div>

<?php if (!$q['duoc_doc']): ?>
  <div class="panel khoa">
    <h2>🔒 <?= e($c['tieu_de']) ?></h2>
    <p><?= e($q['ly_do']) ?></p>
    <?php if ($q['goi_y'] === 'DANG_NHAP'): ?><a class="btn" href="<?= e(url('dang_nhap.php')) ?>" onclick="">Đăng nhập để tiếp tục</a>
    <?php elseif ($q['goi_y'] === 'MUA_VIP'): ?><a class="btn" href="<?= e(url('hoi_vien.php')) ?>">Đăng ký WakaVIP</a>
    <?php else: ?>
      <p class="gia"><?= so($c['gia_hat_soi']) ?> hạt sồi</p>
      <form method="post" action="<?= e(url('gio_hang.php')) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="them"><input type="hidden" name="ma_chuong" value="<?= (int)$c['ma_chuong'] ?>"><button class="btn">Thêm chương vào giỏ</button></form>
      <?php if ($c['hinh_thuc_doc'] === 'MUA_LE' && (int)$c['gia_sach'] > 0): ?><p class="goi-y">hoặc <a href="<?= e(url('chi_tiet.php?id=' . $c['ma_sach'])) ?>">mua cả sách (<?= so($c['gia_sach']) ?> hạt sồi)</a></p><?php endif; ?>
    <?php endif; ?>
  </div>
<?php else: ?>
  <div class="doc-tien-do" id="tiendo-bar"></div>
  <article class="doc-khung" id="noidung" data-chuong="<?= (int)$c['ma_chuong'] ?>" data-vt="<?= e($vt) ?>" data-login="<?= $u ? 1 : 0 ?>">
    <h2 style="margin-top:0"><?= e($c['tieu_de']) ?></h2>
    <?= chuong_noi_dung_html($c) ?>
  </article>
  <div class="doc-thanh" style="margin-top:14px">
    <?php if ($truoc): ?><a class="btn xam" href="?c=<?= $truoc ?>">‹ Chương trước</a><?php else: ?><span></span><?php endif; ?>
    <?php if ($u): ?><button type="button" class="btn nho" id="them-dt">🔖 Thêm dấu trang</button><?php endif; ?>
    <?php if ($sau): ?><a class="btn" href="?c=<?= $sau ?>">Chương sau ›</a><?php else: ?><span></span><?php endif; ?>
  </div>
  <?php if ($u): ?>
  <div class="dt-ds"><h3>Dấu trang của sách này</h3><ul id="ds-dt" style="list-style:none;padding:0">
    <?php foreach ($dau_trang as $d): ?><li data-id="<?= $d['ma_dau_trang'] ?>"><span><a href="?c=<?= $d['ma_chuong'] ?>&vt=<?= e($d['vi_tri_cuon']) ?>"><?= e($d['tieu_de']) ?> · <?= e($d['vi_tri_cuon']) ?>%</a> <small><?= e($d['ghi_chu'] ?? '') ?></small></span><button class="btn nho xam" data-xoa="<?= $d['ma_dau_trang'] ?>">Xóa</button></li><?php endforeach; ?>
  </ul><p class="muted" id="dt-trong" <?= $dau_trang ? 'hidden' : '' ?>>Chưa có dấu trang.</p></div>
  <?php endif; ?>
<?php endif; ?>
<?php trang_cuoi(['js' => $q['duoc_doc'] ? ['assets/js/doc.js'] : []]);
