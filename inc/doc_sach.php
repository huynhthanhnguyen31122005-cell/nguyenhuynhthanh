<?php
/** Module 4 – quyền đọc, nội dung chương, tiến độ, dấu trang, lịch sử, tủ sách. */

/** Nội dung đã mua của người dùng trong một sách (đơn MUA_NOI_DUNG đã thanh toán). */
function so_huu(PDO $pdo, int $uid, int $ma_sach): array {
    $r = ['sach' => false, 'chuong' => []];
    if ($uid <= 0) return $r;
    $st = $pdo->prepare("SELECT ct.ma_sach, ct.ma_chuong FROM chi_tiet_don_hang ct
        JOIN don_hang d ON d.ma_don_hang = ct.ma_don_hang AND d.loai_don = 'MUA_NOI_DUNG' AND d.trang_thai = 'DA_THANH_TOAN'
        LEFT JOIN chuong c ON c.ma_chuong = ct.ma_chuong
        WHERE d.ma_nguoi_dung = ? AND (ct.ma_sach = ? OR c.ma_sach = ?)");
    $st->execute([$uid, $ma_sach, $ma_sach]);
    foreach ($st as $x) {
        if ($x['ma_chuong'] === null) $r['sach'] = true; else $r['chuong'][(int)$x['ma_chuong']] = true;
    }
    return $r;
}

/** FR4.2 – quyền đọc 1 chương. Trả về ['duoc_doc'=>bool, 'goi_y'=>null|DANG_NHAP|MUA_VIP|MUA_CHUONG, 'ly_do'=>string]. */
function quyen_chuong(array $c, ?array $nd, bool $vip, array $sh): array {
    $ok = ['duoc_doc' => true, 'goi_y' => null, 'ly_do' => ''];
    if ($nd && $nd['vai_tro'] === 'QUAN_TRI') return $ok;
    $da_mua = $sh['sach'] || isset($sh['chuong'][(int)$c['ma_chuong']]);
    switch ($c['che_do_truy_cap']) {
        case 'MIEN_PHI': return $ok;
        case 'HOI_VIEN':
            if ($nd && ($vip || $da_mua)) return $ok;
            return ['duoc_doc' => false, 'goi_y' => $nd ? 'MUA_VIP' : 'DANG_NHAP', 'ly_do' => 'Chương dành cho hội viên WakaVIP còn hạn.'];
        default:
            if ($nd && $da_mua) return $ok;
            return ['duoc_doc' => false, 'goi_y' => $nd ? 'MUA_CHUONG' : 'DANG_NHAP', 'ly_do' => 'Chương trả phí, bạn chưa mua.'];
    }
}

function chuong_lay(PDO $pdo, int $id): ?array {
    $st = $pdo->prepare("SELECT c.*, s.ten_sach, s.dinh_dang, s.gia_hat_soi AS gia_sach, s.hinh_thuc_doc
        FROM chuong c JOIN sach s ON s.ma_sach = c.ma_sach WHERE c.ma_chuong = ? AND c.trang_thai = 1 AND s.tinh_trang <> 'TAM_AN'");
    $st->execute([$id]);
    return $st->fetch() ?: null;
}
function chuong_ke(PDO $pdo, array $c, string $huong): ?int {
    $st = $pdo->prepare('SELECT ma_chuong FROM chuong WHERE ma_sach = ? AND trang_thai = 1 AND so_thu_tu ' . ($huong === 'truoc' ? '< ? ORDER BY so_thu_tu DESC' : '> ? ORDER BY so_thu_tu') . ' LIMIT 1');
    $st->execute([$c['ma_sach'], $c['so_thu_tu']]);
    $v = $st->fetchColumn();
    return $v === false ? null : (int)$v;
}
function chuong_dau(PDO $pdo, int $ma_sach): ?int {
    $st = $pdo->prepare('SELECT ma_chuong FROM chuong WHERE ma_sach = ? AND trang_thai = 1 ORDER BY so_thu_tu LIMIT 1');
    $st->execute([$ma_sach]);
    $v = $st->fetchColumn();
    return $v === false ? null : (int)$v;
}

/** Ảnh truyện tranh trong thư mục uploads/… (chặn path traversal). */
function anh_truyen(string $thu_muc): array {
    $goc = realpath(APP_ROOT . '/uploads');
    $dir = realpath(APP_ROOT . '/' . ltrim($thu_muc, '/'));
    if (!$goc || !$dir || !is_dir($dir) || !str_starts_with($dir . '/', $goc . '/')) return [];
    $ds = glob($dir . '/*.{jpg,jpeg,png,webp,gif}', GLOB_BRACE) ?: [];
    natsort($ds);
    return array_map(fn($f) => trim($thu_muc, '/') . '/' . basename($f), array_values($ds));
}

/** Chỉ gọi sau khi đã kiểm tra quyền. */
function chuong_noi_dung_html(array $c): string {
    if ($c['dinh_dang'] === 'SACH_NOI') {
        $f = (string)$c['tep_noi_dung'];
        if ($f === '' || !is_file(APP_ROOT . '/' . ltrim($f, '/'))) return '<p class="muted">Tệp âm thanh chưa được tải lên.</p>';
        return '<audio id="audio" controls preload="metadata" style="width:100%" src="' . e(url($f)) . '"></audio>';
    }
    if ($c['dinh_dang'] === 'TRUYEN_TRANH') {
        $anh = anh_truyen((string)$c['tep_noi_dung']);
        if (!$anh) return '<p class="muted">Chương này chưa có trang truyện.</p>';
        return implode('', array_map(fn($a) => '<img class="trang" loading="lazy" src="' . e(url($a)) . '" alt="">', $anh));
    }
    return nl2br(e((string)$c['noi_dung'])) ?: '<p class="muted">Chương chưa có nội dung.</p>';
}

// ===== Tiến độ (FR4.3) =====
function vi_tri_hop_le($v): float {
    if (!is_numeric($v) || $v < 0 || $v > 100) throw new InvalidArgumentException('Vị trí phải từ 0 đến 100%.');
    return round((float)$v, 2);
}
function tien_do_luu(PDO $pdo, int $uid, int $ma_chuong, float $vt): void {
    $pdo->prepare('INSERT INTO tien_do_doc (ma_nguoi_dung, ma_chuong, vi_tri_cuon, ngay_cap_nhat) VALUES (?,?,?,NOW())
                   ON DUPLICATE KEY UPDATE vi_tri_cuon = VALUES(vi_tri_cuon), ngay_cap_nhat = NOW()')->execute([$uid, $ma_chuong, $vt]);
}
function tien_do_lay(PDO $pdo, int $uid, int $ma_chuong): float {
    $st = $pdo->prepare('SELECT vi_tri_cuon FROM tien_do_doc WHERE ma_nguoi_dung = ? AND ma_chuong = ?');
    $st->execute([$uid, $ma_chuong]);
    return (float)($st->fetchColumn() ?: 0);
}
/** "Đọc tiếp": chương + vị trí gần nhất của 1 sách. */
function doc_tiep(PDO $pdo, int $uid, int $ma_sach): ?array {
    $st = $pdo->prepare('SELECT t.ma_chuong, t.vi_tri_cuon FROM tien_do_doc t JOIN chuong c ON c.ma_chuong = t.ma_chuong
        WHERE t.ma_nguoi_dung = ? AND c.ma_sach = ? AND c.trang_thai = 1 ORDER BY t.ngay_cap_nhat DESC LIMIT 1');
    $st->execute([$uid, $ma_sach]);
    return $st->fetch() ?: null;
}

// ===== Dấu trang (FR4.5) =====
function dau_trang_them(PDO $pdo, int $uid, int $ma_chuong, float $vt, ?string $ghi_chu): int {
    $ghi_chu = trim((string)$ghi_chu);
    if (mb_strlen($ghi_chu, 'UTF-8') > 255) throw new InvalidArgumentException('Ghi chú tối đa 255 ký tự.');
    $pdo->prepare('INSERT INTO dau_trang (ma_nguoi_dung, ma_chuong, vi_tri_cuon, ghi_chu) VALUES (?,?,?,?)')->execute([$uid, $ma_chuong, $vt, $ghi_chu ?: null]);
    return (int)$pdo->lastInsertId();
}
function dau_trang_ds(PDO $pdo, int $uid, ?int $ma_sach = null): array {
    $st = $pdo->prepare('SELECT d.*, c.tieu_de, c.so_thu_tu, s.ma_sach, s.ten_sach FROM dau_trang d JOIN chuong c ON c.ma_chuong = d.ma_chuong
        JOIN sach s ON s.ma_sach = c.ma_sach WHERE d.ma_nguoi_dung = ?' . ($ma_sach ? ' AND s.ma_sach = ' . (int)$ma_sach : '') . ' ORDER BY d.ngay_tao DESC, d.ma_dau_trang DESC');
    $st->execute([$uid]);
    return $st->fetchAll();
}
/** Quyền sở hữu kiểm tra ngay trong câu DELETE. */
function dau_trang_xoa(PDO $pdo, int $uid, int $id): bool {
    $st = $pdo->prepare('DELETE FROM dau_trang WHERE ma_dau_trang = ? AND ma_nguoi_dung = ?');
    $st->execute([$id, $uid]);
    return $st->rowCount() > 0;
}

// ===== Lịch sử đọc (FR4.6) =====
function lich_su_ghi(PDO $pdo, int $uid, int $ma_chuong): void {
    $pdo->prepare('INSERT INTO lich_su_doc (ma_nguoi_dung, ma_chuong, ngay_doc, thoi_diem_doc) VALUES (?,?,CURDATE(),NOW())
                   ON DUPLICATE KEY UPDATE thoi_diem_doc = NOW()')->execute([$uid, $ma_chuong]);
}

// ===== Tủ sách (FR4.1) =====
/** Idempotent: đặt yêu thích = $v (0/1), tạo dòng nếu chưa có. */
function tu_sach_dat_yeu_thich(PDO $pdo, int $uid, int $ma_sach, int $v): void {
    $pdo->prepare('INSERT INTO thu_vien (ma_nguoi_dung, ma_sach, yeu_thich) VALUES (?,?,?) ON DUPLICATE KEY UPDATE yeu_thich = VALUES(yeu_thich)')->execute([$uid, $ma_sach, $v]);
}
function tu_sach_them(PDO $pdo, int $uid, int $ma_sach): void {
    $pdo->prepare('INSERT IGNORE INTO thu_vien (ma_nguoi_dung, ma_sach) VALUES (?,?)')->execute([$uid, $ma_sach]);
}
