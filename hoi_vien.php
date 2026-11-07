<?php
// FR4.7 – gói WakaVIP: danh sách gói, trạng thái (Còn hạn / Sắp hết hạn / Hết hạn), lịch sử mua
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/inc/don_hang.php';

$u = nguoi_dung_hien_tai();
$goi = $pdo->query('SELECT * FROM goi_hoi_vien WHERE trang_thai = 1 AND gia_vnd > 0 AND thoi_han_ngay > 0 ORDER BY thoi_han_ngay')->fetchAll();
$tt = $u ? vip_trang_thai($pdo, (int)$u['ma_nguoi_dung']) : null;
$ls = [];
if ($u) {
    $st = $pdo->prepare("SELECT d.so_don_hang, d.trang_thai, d.tong_tien_goc - d.tien_giam AS tien, d.ngay_tao, g.ten_goi, v.ngay_bat_dau, v.ngay_het_han FROM don_hang d
        LEFT JOIN chi_tiet_don_hang ct ON ct.ma_don_hang = d.ma_don_hang LEFT JOIN goi_hoi_vien g ON g.ma_goi = ct.ma_goi LEFT JOIN dang_ky_hoi_vien v ON v.ma_don_hang = d.ma_don_hang
        WHERE d.ma_nguoi_dung = ? AND d.loai_don = 'MUA_VIP' ORDER BY d.ngay_tao DESC");
    $st->execute([$u['ma_nguoi_dung']]);
    $ls = $st->fetchAll();
}
$nhan = ['CON_HAN' => 'Còn hạn', 'SAP_HET_HAN' => 'Sắp hết hạn', 'HET_HAN' => 'Hết hạn', 'CHUA_DANG_KY' => 'Chưa đăng ký'];
trang_dau('WakaVIP');
?>
<h1>WakaVIP – đọc không giới hạn sách hội viên</h1>
<?php if ($tt): ?><div class="vip-trang-thai">Trạng thái của bạn: <span class="tag <?= e($tt['trang_thai']) ?>"><?= $nhan[$tt['trang_thai']] ?></span>
  <?php if ($tt['ngay_het_han']): ?> · hạn đến <b><?= e(date('d/m/Y H:i', strtotime($tt['ngay_het_han']))) ?></b><?= $tt['so_ngay'] > 0 ? ' (còn ' . $tt['so_ngay'] . ' ngày)' : '' ?><?php endif; ?>
  <?php if ($tt['trang_thai'] !== 'CHUA_DANG_KY'): ?><br><small>Gia hạn khi còn hạn sẽ được cộng dồn vào hạn hiện tại.</small><?php endif; ?></div><?php endif; ?>
<div class="mat-the">
<?php foreach ($goi as $g): ?>
  <div class="card"><h3><?= e($g['ten_goi']) ?></h3><p class="gia"><?= so($g['gia_vnd']) ?> ₫</p><p class="muted"><?= (int)$g['thoi_han_ngay'] ?> ngày · <?= e($g['quyen_loi']) ?></p>
    <a class="btn" href="<?= e(url('dat_hang.php?loai=MUA_VIP&goi=' . $g['ma_goi'])) ?>"><?= $tt && in_array($tt['trang_thai'], ['CON_HAN', 'SAP_HET_HAN'], true) ? 'Gia hạn' : 'Đăng ký' ?></a></div>
<?php endforeach; ?></div>
<?php if ($ls): ?><h2>Lịch sử mua gói</h2><div class="cuon"><table><tr><th>Đơn</th><th>Gói</th><th>Số tiền</th><th>Trạng thái</th><th>Hiệu lực</th></tr>
<?php foreach ($ls as $r): ?><tr><td><?= e($r['so_don_hang']) ?></td><td><?= e($r['ten_goi']) ?></td><td><?= so($r['tien']) ?> ₫</td><td><span class="tag <?= e($r['trang_thai']) ?>"><?= e(TEN_TRANG_THAI[$r['trang_thai']]) ?></span></td>
  <td><?= $r['ngay_het_han'] ? e(date('d/m/Y', strtotime($r['ngay_bat_dau'])) . ' → ' . date('d/m/Y', strtotime($r['ngay_het_han']))) : '—' ?></td></tr><?php endforeach; ?></table></div><?php endif; ?>
<?php trang_cuoi();
