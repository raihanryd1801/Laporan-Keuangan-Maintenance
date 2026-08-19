<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LaporanController;

// Route Login (Bebas diakses tanpa firewall/auth)
Route::get('/login', [LaporanController::class, 'showLogin'])->name('login');
Route::post('/login', [LaporanController::class, 'processLogin']);
Route::post('/logout', [LaporanController::class, 'logout'])->name('logout');

// =========================================================================
// SEMUA RUTE APLIKASI KEUANGAN DIBUNGKUS SATU KALI DI SINI (AUTH + FIREWALL)
// =========================================================================
Route::middleware(['auth', 'firewall.ip'])->group(function () {

    // Laporan Utama
    Route::get('/laporan/keuangan', [LaporanController::class, 'laporanKeuangan']);
    Route::get('/laporan/pemasangan-baru', [LaporanController::class, 'pemasanganBaru']);
    Route::get('/laporan/pemasukan', [LaporanController::class, 'pemasukan']);
    Route::get('/laporan/pengeluaran', [LaporanController::class, 'pengeluaran']);
    Route::get('/laporan/kasbon', [LaporanController::class, 'kasbon']);

    // Menu Input & Transaksi
    Route::get('/laporan/menu-input', [LaporanController::class, 'menuInput']);
    Route::get('/laporan/transaksi/tambah', [LaporanController::class, 'create']);
    Route::post('/laporan/transaksi/store', [LaporanController::class, 'store']);
    Route::post('/laporan/transaksi/simpan', [LaporanController::class, 'store']);
    Route::get('/laporan/transaksi/edit/{id}', [LaporanController::class, 'editTransaksi']);
    Route::match(['post', 'put'], '/laporan/transaksi/update/{id}', [LaporanController::class, 'updateTransaksi']);
    Route::get('/laporan/transaksi/hapus/{id}', [LaporanController::class, 'destroyTransaksi']);
    Route::get('/laporan/transaksi/create', [LaporanController::class, 'create']);

    // Export Data
    Route::get('/laporan/export/excel', [LaporanController::class, 'exportExcel']);
    Route::get('/laporan/export/pdf', [LaporanController::class, 'exportPdf']);

    // Manajemen Teknisi
    Route::get('/laporan/teknisi', [LaporanController::class, 'indexTeknisi']);
    Route::post('/laporan/teknisi/store', [LaporanController::class, 'storeTeknisi']);
    Route::post('/laporan/teknisi/simpan', [LaporanController::class, 'storeTeknisi']);
    Route::get('/laporan/teknisi/edit/{id}', [LaporanController::class, 'editTeknisi']);
    Route::match(['get', 'post'], '/laporan/teknisi/update/{id}', [LaporanController::class, 'updateTeknisi']);
    Route::get('/laporan/teknisi/hapus/{id}', [LaporanController::class, 'destroyTeknisi']);

    // Master Area
    Route::get('/laporan/area', [LaporanController::class, 'laporanArea']);
    Route::get('/laporan/master-area', [LaporanController::class, 'indexArea']);
    Route::post('/laporan/master-area/store', [LaporanController::class, 'storeArea']);
    Route::post('/laporan/master-area/simpan', [LaporanController::class, 'storeArea']);
    Route::get('/laporan/master-area/edit/{id}', [LaporanController::class, 'editArea']);
    Route::match(['get', 'post'], '/laporan/master-area/update/{id}', [LaporanController::class, 'updateArea']);
    Route::get('/laporan/master-area/hapus/{id}', [LaporanController::class, 'destroyArea']);

    // Master Kategori & Log
    Route::get('/laporan/master-kategori', [LaporanController::class, 'indexKategori']);
    Route::post('/laporan/master-kategori/store', [LaporanController::class, 'storeKategori']);
    Route::post('/laporan/master-kategori/simpan', [LaporanController::class, 'storeKategori']);
    Route::get('/laporan/activity-log', [LaporanController::class, 'indexLog']);
    Route::get('/laporan/master-kategori/edit/{id}', [LaporanController::class, 'editKategori']);
    Route::match(['get', 'post'], '/laporan/master-kategori/update/{id}', [LaporanController::class, 'updateKategori']);
    Route::get('/laporan/master-kategori/hapus/{id}', [LaporanController::class, 'destroyKategori']);

    // Menu Firewall & Manajemen Sesi Admin
    Route::get('/laporan/firewall', [LaporanController::class, 'firewallManagement']);
    Route::post('/laporan/firewall/store-ip', [LaporanController::class, 'storeIp']);
    Route::delete('/laporan/firewall/kill/{id}', [LaporanController::class, 'killSession']);
    // Di dalam grup Route::middleware(['auth', 'firewall.ip'])->group(function () { ... })
    Route::delete('/laporan/firewall/delete-ip/{id}', [LaporanController::class, 'destroyIp']);

    // Laporan Utama
    Route::get('/laporan/keuangan', [LaporanController::class, 'laporanKeuangan']);
    Route::get('/laporan/statistik', [LaporanController::class, 'statistik']); // <--- TAMBAHKAN INI
    // ...
    Route::post('/laporan/firewall/fail2ban/store', [LaporanController::class, 'storeFail2banIp']);
    Route::post('/laporan/firewall/fail2ban/delete', [LaporanController::class, 'destroyFail2banIp']);
    Route::post('/laporan/firewall/fail2ban/config', [LaporanController::class, 'updateFail2banConfig']);
    Route::post('/laporan/firewall/fail2ban/unban', [LaporanController::class, 'unbanFail2banIp']);

    Route::get('/laporan/profile', [LaporanController::class, 'editProfile']);
    Route::put('/laporan/profile', [LaporanController::class, 'updateProfile']);

    Route::post('/laporan/mutasi-bank/store', [LaporanController::class, 'storeMutasiBank']);

    //EXPORT NOTA
    Route::get('/laporan/keuangan/export-nota', [\App\Http\Controllers\LaporanController::class, 'exportNotaPdf'])->name('laporan.export.nota');
});

// Redirect default ke laporan keuangan
Route::get('/', function () {
    return redirect('/laporan/keuangan');
});