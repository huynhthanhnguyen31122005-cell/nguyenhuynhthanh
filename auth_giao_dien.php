<?php
/** Khung thẻ cho các trang xác thực (Module 1), dùng layout chung. */
require_once __DIR__ . '/layout.php';

function auth_dau_trang(string $tieu_de, string $rong = '440px'): void {
    trang_dau($tieu_de, ['main' => 'wrap hep']);
    echo '<div class="panel" style="max-width:' . e($rong) . ';margin:20px auto">';
}
function auth_cuoi_trang(): void { echo '</div>'; trang_cuoi(); }
