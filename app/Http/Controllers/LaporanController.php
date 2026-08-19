<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Transaksi;
use Carbon\Carbon;
use App\Models\Kategori;
use App\Models\MetodePembayaran;
use App\Models\User;
use App\Models\Area;
use App\Models\FirewallIp;
use App\Exports\KeuanganExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LaporanController extends Controller
{
    /**
     * 1. Laporan Keuangan Retail (Keseluruhan & Dikelompokkan)
     */
    public function laporanKeuangan(Request $request)
    {
        // Ambil filter tanggal
        $mulai = $request->input('tanggal_mulai', \Carbon\Carbon::now()->startOfMonth()->toDateString());
        $sampai = $request->input('tanggal_selesai', \Carbon\Carbon::now()->endOfMonth()->toDateString());

        // =========================================================
        // 1. Saldo Awal (Cash + Bank Global) -> sama seperti F8 Excel
        // =========================================================
        $transaksiSaldoAwal = \App\Models\Transaksi::whereBetween('tanggal', [$mulai, $sampai])
            ->whereHas('kategori', function ($q) {
                $q->where('nama_kategori', 'like', '%Saldo Awal%');
            })->sum('debet');

        $transaksiSebelumnya = \App\Models\Transaksi::where('tanggal', '<', $mulai)->get();
        $saldoAwalAkumulasi = $transaksiSebelumnya->sum('debet') - $transaksiSebelumnya->sum('kredit');

        $saldoAwal = $saldoAwalAkumulasi + $transaksiSaldoAwal;

        // =========================================================
        // 2. Transaksi Berjalan (dalam periode filter)
        // =========================================================
        $transaksi = \App\Models\Transaksi::whereBetween('tanggal', [$mulai, $sampai])
            ->whereDoesntHave('kategori', function ($q) {
                $q->where('nama_kategori', 'like', '%Saldo Awal%');
            })
            ->with(['kategori', 'metodePembayaran', 'area', 'user'])
            ->orderBy('tanggal', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        // =========================================================
        // 3. Omzet & Operasional Murni (tanpa Mutasi/Setor Tunai, biar
        //    perpindahan uang internal antar akun tidak dihitung dobel)
        //    -> sama seperti F45 Excel (Total Keuangan Retail)
        //    Kategori "Mutasi" (id 35) dan "Setor Tunai" (id 34) dikecualikan.
        // =========================================================
        $transaksiMurni = $transaksi->filter(function ($r) {
            if (!$r->kategori)
                return true;
            $namaKategori = strtolower($r->kategori->nama_kategori);
            return stripos($namaKategori, 'mutasi') === false && stripos($namaKategori, 'setor') === false;
        });

        $totalDebet = $transaksiMurni->sum('debet');
        $totalKredit = $transaksiMurni->sum('kredit');

        $mutasiBerjalan = $totalDebet - $totalKredit;
        $saldoAkhir = $saldoAwal + $mutasiBerjalan;   // <-- ini F45, Total Keuangan Retail

        // =========================================================
        // 4a. Saldo Cash saat ini (akumulatif dari awal s.d. akhir periode)
        //     Ini sudah otomatis memperhitungkan Mutasi Keluar (waktu cash
        //     disetor ke bank, saldo Cash otomatis berkurang lewat transaksi
        //     kategori "Mutasi" dengan metode_pembayaran_id = 1 di sisi kredit).
        // =========================================================
        $saldoCashSaatIni = \App\Models\Transaksi::where('tanggal', '<=', $sampai)
            ->where('metode_pembayaran_id', 1) // 1 = Cash
            ->selectRaw('COALESCE(SUM(debet) - SUM(kredit), 0) as saldo')
            ->value('saldo');

        // =========================================================
        // 4b. F50 & F51 - Split Cash Operasional vs Belum Disetor
        //     Aturan dari client (Faros kasih plafon operasional maks 3jt):
        //
        //     IF saldoCashSaatIni <= 3.000.000
        //         uangCashOperasional   = saldoCashSaatIni
        //         uangCashBelumDisetor  = 0
        //     ELSE
        //         uangCashOperasional   = 3.000.000
        //         uangCashBelumDisetor  = saldoCashSaatIni - 3.000.000
        // =========================================================
        $PLAFON_CASH_OPERASIONAL = 3000000;

        if ($saldoCashSaatIni <= $PLAFON_CASH_OPERASIONAL) {
            $uangCashOperasional = max(0, $saldoCashSaatIni);
            $uangCashBelumDisetor = 0;
        } else {
            $uangCashOperasional = $PLAFON_CASH_OPERASIONAL;
            $uangCashBelumDisetor = $saldoCashSaatIni - $PLAFON_CASH_OPERASIONAL;
        }

        // =========================================================
        // 4c. F52 - Uang Retail di Rekening Faros
        //
        //     ⚠️ BELUM BISA DIHITUNG OTOMATIS: transaksi yang duitnya masuk
        //     ke rekening Faros pakai metode_pembayaran_id yang SAMA dengan
        //     transfer bank biasa, jadi tidak ada cara membedakannya di DB
        //     saat ini.
        //
        //     SEMENTARA pakai input manual (mirip cara F50 dulu di Excel).
        //     REKOMENDASI: tambahkan salah satu ini agar bisa otomatis:
        //       a) kategori baru "Pembayaran Retail ke Rekening Faros", atau
        //       b) baris baru "Rekening Faros" di tabel metode_pembayarans
        //     Begitu salah satunya ada, ganti baris di bawah ini dengan query.
        // =========================================================
        $uangRekeningFaros = (float) $request->input('uang_rekening_faros', 0);

        // =========================================================
        // 5. UANG RETAIL DI REKENING PT -> sama persis F53 Excel
        //    Rumus: F45 - F50 - F51 - F52
        // =========================================================
        $uangRetailDiRekening = $saldoAkhir - $uangCashOperasional - $uangCashBelumDisetor - $uangRekeningFaros;

        // Alias biar view lama yang masih pakai nama variabel lama tidak error
        // (opsional — hapus kalau blade sudah diupdate pakai nama variabel baru)
        $saldoAwalCash = $uangCashOperasional;

        // --- Data Pelengkap untuk View (tidak berubah dari versi lama) ---
        $areas = \App\Models\Area::all();
        $pemasukanPerArea = [];
        foreach ($areas as $area) {
            $pemasukanPerArea[$area->nama_area] = $transaksi->where('area_id', $area->id)->sum('debet');
        }

        $pemasukanLainnya = $transaksi->filter(function ($r) use ($areas) {
            return $r->debet > 0 && is_null($r->area_id) && $r->kategori
                && stripos($r->kategori->nama_kategori, 'Kasbon') === false
                && $r->kategori->nama_kategori !== 'Pemasangan Baru'
                && stripos($r->kategori->nama_kategori, 'Setor') === false
                && stripos($r->kategori->nama_kategori, 'Mutasi') === false;
        });
        foreach ($pemasukanLainnya as $trx) {
            $namaKat = $trx->kategori->nama_kategori;
            $pemasukanPerArea[$namaKat] = ($pemasukanPerArea[$namaKat] ?? 0) + $trx->debet;
        }

        $kasbonMasuk = $transaksi->filter(fn($r) => $r->debet > 0 && $r->kategori && stripos($r->kategori->nama_kategori, 'Kasbon') !== false)->sum('debet');

        $kategoris = \App\Models\Kategori::all();
        $pengeluaranPerKategori = [];
        $totalPengeluaranOperasional = 0;
        foreach ($kategoris as $kat) {
            $nominalKredit = $transaksi->where('kategori_id', $kat->id)->sum('kredit');
            if (
                $nominalKredit > 0
                && stripos($kat->nama_kategori, 'Kasbon') === false
                && stripos($kat->nama_kategori, 'Setor') === false
                && stripos($kat->nama_kategori, 'Mutasi') === false
            ) {
                $pengeluaranPerKategori[$kat->nama_kategori] = $nominalKredit;
                $totalPengeluaranOperasional += $nominalKredit;
            }
        }
        $kasbonKeluar = $transaksi->filter(fn($r) => $r->kredit > 0 && $r->kategori && stripos($r->kategori->nama_kategori, 'Kasbon') !== false)->sum('kredit');
        $sisaKasbonTeknisi = $kasbonKeluar - $kasbonMasuk;

        return view('laporan.keuangan', compact(
            'saldoAwal',
            'pemasukanPerArea',
            'kasbonMasuk',
            'pengeluaranPerKategori',
            'totalPengeluaranOperasional',
            'kasbonKeluar',
            'totalDebet',
            'totalKredit',
            'saldoAkhir',
            'mulai',
            'sampai',
            'areas',
            'saldoAwalCash',
            'saldoCashSaatIni',
            'uangCashOperasional',
            'uangCashBelumDisetor',
            'uangRekeningFaros',
            'uangRetailDiRekening'
        ));
    }
    /**
     * 2. Laporan Pemasangan Baru
     */
    public function pemasanganBaru(Request $request)
    {
        $mulai = $request->input('tanggal_mulai', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $sampai = $request->input('tanggal_selesai', Carbon::now()->endOfMonth()->format('Y-m-d'));

        $transaksi = Transaksi::with(['kategori', 'metodePembayaran', 'area', 'user'])
            ->whereBetween('tanggal', [$mulai, $sampai])
            ->whereHas('kategori', function ($q) {
                $q->where('nama_kategori', 'Pemasangan Baru');
            })
            ->orderBy('tanggal', 'asc')
            ->get();

        $totalPemasangan = $transaksi->sum(function ($row) {
            return $row->debet > 0 ? $row->debet : $row->kredit;
        });

        return view('laporan.pemasangan_baru', compact('transaksi', 'totalPemasangan', 'mulai', 'sampai'));
    }

    /**
     * 3. Laporan Pemasukan (Cash & Transfer) + Filter Tanggal
     */
    /**
     * 3. Laporan Pemasukan (Cash & Transfer) + Filter Tanggal
     */
    public function pemasukan(Request $request)
    {
        $mulai = $request->input('tanggal_mulai', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $sampai = $request->input('tanggal_selesai', Carbon::now()->endOfMonth()->format('Y-m-d'));

        // 1. Pemasukan Cash Murni (Tanpa Mutasi) -> termasuk baris "Saldo Awal"
        $pemasukanCash = Transaksi::with(['kategori', 'metodePembayaran', 'area', 'user'])
            ->where('debet', '>', 0)
            ->whereBetween('tanggal', [$mulai, $sampai])
            ->whereHas('metodePembayaran', fn($q) => $q->where('nama_metode', 'Cash'))
            ->whereDoesntHave('kategori', function ($q) {
                $q->where('nama_kategori', 'like', '%Mutasi%')
                    ->orWhere('nama_kategori', 'like', '%Setor%');
            })
            ->orderBy('tanggal', 'asc')
            ->get();

        // 2. Pemasukan Transfer Murni (Tanpa Mutasi) -> termasuk baris "Saldo Awal"
        $pemasukanTransfer = Transaksi::with(['kategori', 'metodePembayaran', 'area', 'user'])
            ->where('debet', '>', 0)
            ->whereBetween('tanggal', [$mulai, $sampai])
            ->whereHas('metodePembayaran', fn($q) => $q->where('nama_metode', 'Transfer Bank'))
            ->whereDoesntHave('kategori', function ($q) {
                $q->where('nama_kategori', 'like', '%Mutasi%')
                    ->orWhere('nama_kategori', 'like', '%Setor%');
            })
            ->orderBy('tanggal', 'asc')
            ->get();

        // 3. KHUSUS RIWAYAT MUTASI / SETOR TUNAI (Agar bisa di-edit/delete Mbak Rahma)
        $riwayatMutasi = Transaksi::with(['kategori', 'metodePembayaran', 'area', 'user'])
            ->where('debet', '>', 0)
            ->whereBetween('tanggal', [$mulai, $sampai])
            ->whereHas('kategori', function ($q) {
                $q->where('nama_kategori', 'like', '%Mutasi%')
                    ->orWhere('nama_kategori', 'like', '%Setor%');
            })
            ->orderBy('tanggal', 'asc')
            ->get();

        $totalCash = $pemasukanCash->sum('debet');
        $totalTransfer = $pemasukanTransfer->sum('debet');
        $totalMutasi = $riwayatMutasi->sum('debet');

        // =========================================================
        // [BARU] Total Pemasukan "Berjalan" -> Total dikurangi Saldo Awal
        // periode ini, biar kelihatan omzet murni bulan berjalan tanpa
        // ikut kebawa saldo carry-over dari bulan sebelumnya.
        // =========================================================
        $saldoAwalCashPeriode = $pemasukanCash
            ->filter(fn($r) => $r->kategori && stripos($r->kategori->nama_kategori, 'Saldo Awal') !== false)
            ->sum('debet');

        $saldoAwalTransferPeriode = $pemasukanTransfer
            ->filter(fn($r) => $r->kategori && stripos($r->kategori->nama_kategori, 'Saldo Awal') !== false)
            ->sum('debet');

        $totalCashBerjalan = $totalCash - $saldoAwalCashPeriode;
        $totalTransferBerjalan = $totalTransfer - $saldoAwalTransferPeriode;

        // Jangan lupa passing variabel riwayatMutasi ke View
        return view('laporan.pemasukan', compact(
            'pemasukanCash',
            'pemasukanTransfer',
            'riwayatMutasi',
            'totalCash',
            'totalTransfer',
            'totalMutasi',
            'totalCashBerjalan',
            'totalTransferBerjalan',
            'mulai',
            'sampai'
        ));
    }

    /**
     * 4. Laporan Pengeluaran + Filter Tanggal
     */
    public function pengeluaran(Request $request)
    {
        $mulai = $request->input('tanggal_mulai', \Carbon\Carbon::now()->startOfMonth()->format('Y-m-d'));
        $sampai = $request->input('tanggal_selesai', \Carbon\Carbon::now()->endOfMonth()->format('Y-m-d'));

        $pengeluaranCash = Transaksi::with(['kategori', 'metodePembayaran', 'area', 'user'])
            ->where('kredit', '>', 0)
            ->whereBetween('tanggal', [$mulai, $sampai])
            ->whereHas('metodePembayaran', fn($q) => $q->where('nama_metode', 'Cash'))
            ->whereDoesntHave('kategori', function ($q) {
                $q->where('nama_kategori', 'like', '%Kasbon%')
                    ->orWhere('nama_kategori', 'like', '%Mutasi%')
                    ->orWhere('nama_kategori', 'like', '%Setor%');
            })
            ->orderBy('tanggal', 'asc')
            ->get();

        $pengeluaranTransfer = Transaksi::with(['kategori', 'metodePembayaran', 'area', 'user'])
            ->where('kredit', '>', 0)
            ->whereBetween('tanggal', [$mulai, $sampai])
            ->whereHas('metodePembayaran', fn($q) => $q->where('nama_metode', 'Transfer Bank'))
            ->whereDoesntHave('kategori', function ($q) {
                $q->where('nama_kategori', 'like', '%Kasbon%')
                    ->orWhere('nama_kategori', 'like', '%Mutasi%')
                    ->orWhere('nama_kategori', 'like', '%Setor%');
            })
            ->orderBy('tanggal', 'asc')
            ->get();

        $totalCash = $pengeluaranCash->sum('kredit');
        $totalTransfer = $pengeluaranTransfer->sum('kredit');

        return view('laporan.pengeluaran', compact('pengeluaranCash', 'pengeluaranTransfer', 'totalCash', 'totalTransfer', 'mulai', 'sampai'));
    }

    /**
     * 5. Laporan Kasbon Teknisi + Filter Tanggal
     */
    public function kasbon(Request $request)
    {
        $mulai = $request->input('tanggal_mulai', \Carbon\Carbon::now()->startOfMonth()->format('Y-m-d'));
        $sampai = $request->input('tanggal_selesai', \Carbon\Carbon::now()->endOfMonth()->format('Y-m-d'));
        $teknisiId = $request->input('teknisi_id');

        // Daftar teknisi yang PERNAH punya transaksi kasbon s.d. tanggal filter
        $userIdsKasbon = \App\Models\Transaksi::where('tanggal', '<=', $sampai)
            ->whereHas('kategori', fn($q) => $q->where('nama_kategori', 'like', '%Kasbon%'))
            ->whereNotNull('user_id')
            ->pluck('user_id')
            ->unique();

        $teknisis = \App\Models\User::whereIn('id', $userIdsKasbon)->get();

        // [DIPERBAIKI] Akumulatif dari awal waktu s.d. tanggal "sampai",
        // supaya pembayaran yang menutup kasbon bulan-bulan sebelumnya
        // tetap match dengan pinjaman aslinya.
        $kasbon = \App\Models\Transaksi::with(['user', 'area', 'metodePembayaran'])
            ->where('tanggal', '<=', $sampai)
            ->whereHas('kategori', fn($q) => $q->where('nama_kategori', 'like', '%Kasbon%'))
            ->when($teknisiId, function ($query) use ($teknisiId) {
                return $query->where('user_id', $teknisiId);
            })
            ->orderBy('tanggal', 'asc')
            ->get();

        // Total sisa hutang kasbon SEMUA teknisi per tanggal "sampai"
        $totalKasbon = $kasbon->sum('kredit') - $kasbon->sum('debet');

        // [BARU] Rincian sisa hutang PER teknisi, biar kelihatan siapa yang
        // masih punya hutang dan siapa yang sudah lunas / bayar lebih.
        $sisaPerTeknisi = $kasbon->groupBy('user_id')->map(function ($rows) {
            $user = $rows->first()->user;
            return [
                'nama' => $user ? $user->name : 'Tanpa Nama',
                'sisa' => $rows->sum('kredit') - $rows->sum('debet'),
            ];
        })->values();

        return view('laporan.kasbon', compact(
            'kasbon',
            'totalKasbon',
            'sisaPerTeknisi',
            'mulai',
            'sampai',
            'teknisis',
            'teknisiId'
        ));
    }
    public function create(Request $request)
    {
        $kategoris = Kategori::all();
        $metodes = MetodePembayaran::all();
        $users = User::all();
        $areas = Area::all();

        $defaultJenis = $request->input('jenis', 'debet');

        return view('laporan.create', compact('kategoris', 'metodes', 'users', 'areas', 'defaultJenis'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'tanggal' => 'required|date',
            'jenis_transaksi' => 'required|in:debet,kredit,mutasi',
            'kategori_id' => 'required_unless:jenis_transaksi,mutasi',
            'metode_pembayaran_id' => 'required',
            'keterangan' => 'required|string|max:255',
            'nominal' => 'required|numeric|min:0',
            'nota' => 'nullable|image|mimes:jpeg,png,jpg|max:2048', // [BARU] Validasi Nota Maks 2MB
        ]);
        $tglFormat = \Carbon\Carbon::parse($request->tanggal)->format('d/m/Y');

        // [BARU] Proses Upload Gambar Nota
        $namaFileNota = null;
        if ($request->hasFile('nota')) {
            $file = $request->file('nota');
            $namaFileNota = 'nota_' . time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            // Simpan di folder public/uploads/nota biar aman tanpa symlink
            $file->move(public_path('uploads/nota'), $namaFileNota);
        }

        if ($request->jenis_transaksi === 'mutasi') {
            $this->recordLog('Mutasi Bank', 'Setor tunai ke bank tgl ' . $tglFormat . ' senilai Rp ' . number_format($request->nominal, 0, ',', '.') . ' (' . $request->keterangan . ')');
            $kategoriMutasi = \App\Models\Kategori::firstOrCreate(['nama_kategori' => 'Mutasi']);

            Transaksi::create([
                'tanggal' => $request->tanggal,
                'kategori_id' => $kategoriMutasi->id,
                'metode_pembayaran_id' => 1,
                'area_id' => null,
                'user_id' => auth()->id(),
                'keterangan' => '[MUTASI KELUAR] ' . $request->keterangan,
                'debet' => 0,
                'kredit' => $request->nominal,
                'nota' => $namaFileNota, // [BARU] Simpan nota
            ]);

            Transaksi::create([
                'tanggal' => $request->tanggal,
                'kategori_id' => $kategoriMutasi->id,
                'metode_pembayaran_id' => $request->metode_pembayaran_id,
                'area_id' => null,
                'user_id' => auth()->id(),
                'keterangan' => '[MUTASI MASUK] ' . $request->keterangan,
                'debet' => $request->nominal,
                'kredit' => 0,
            ]);

            return redirect('/laporan/keuangan')->with('success', 'Berhasil! Setor tunai tercatat.');
        }

        // LOGIKA NORMAL
        $debet = $request->jenis_transaksi === 'debet' ? $request->nominal : 0;
        $kredit = $request->jenis_transaksi === 'kredit' ? $request->nominal : 0;

        $this->recordLog('Tambah Transaksi', 'Menambahkan transaksi senilai Rp ' . number_format($request->nominal, 0, ',', '.') . ' (' . $request->keterangan . ')');

        Transaksi::create([
            'tanggal' => $request->tanggal,
            'kategori_id' => $request->kategori_id,
            'metode_pembayaran_id' => $request->metode_pembayaran_id,
            'area_id' => $request->area_id ?: null,
            'user_id' => $request->user_id ?: null,
            'keterangan' => $request->keterangan,
            'debet' => $debet,
            'kredit' => $kredit,
            'nota' => $namaFileNota, // [BARU] Simpan nota ke DB
        ]);

        return redirect('/laporan/keuangan')->with('success', 'Data transaksi & nota berhasil ditambahkan!');
    }
    public function menuInput()
    {
        return view('laporan.menu_input');
    }

    public function indexTeknisi()
    {
        $users = User::all();
        return view('laporan.teknisi', compact('users'));
    }

    public function storeTeknisi(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        User::create([
            'name' => $request->name,
            'email' => strtolower(str_replace(' ', '', $request->name)) . '@teknisi.local',
            'password' => bcrypt('password123'),
            'jabatan' => 'Teknisi'
        ]);

        return redirect('/laporan/teknisi')->with('success', 'Nama teknisi berhasil ditambahkan!');
    }

    public function laporanArea(Request $request)
    {
        $mulai = $request->input('tanggal_mulai', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $sampai = $request->input('tanggal_selesai', Carbon::now()->endOfMonth()->format('Y-m-d'));
        $areaId = $request->input('area_id');

        $areas = Area::all();

        $transaksi = Transaksi::with(['kategori', 'metodePembayaran', 'area', 'user'])
            ->whereBetween('tanggal', [$mulai, $sampai])
            ->when($areaId, function ($query) use ($areaId) {
                return $query->where('area_id', $areaId);
            })
            ->orderBy('tanggal', 'asc')
            ->get();

        $totalNominal = $transaksi->sum('debet');

        return view('laporan.area', compact('transaksi', 'totalNominal', 'areas', 'areaId', 'mulai', 'sampai'));
    }

    public function indexArea()
    {
        $areas = Area::all();
        return view('laporan.master_area', compact('areas'));
    }

    public function storeArea(Request $request)
    {
        $request->validate([
            'nama_area' => 'required|string|max:255|unique:areas,nama_area',
        ]);

        Area::create([
            'nama_area' => $request->nama_area
        ]);

        return redirect('/laporan/master-area')->with('success', 'Area baru berhasil ditambahkan!');
    }

    public function indexKategori()
    {
        $kategoris = Kategori::all();
        return view('laporan.master_kategori', compact('kategoris'));
    }

    public function storeKategori(Request $request)
    {
        $request->validate([
            'nama_kategori' => 'required|string|max:255|unique:kategoris,nama_kategori',
        ]);

        Kategori::create([
            'nama_kategori' => $request->nama_kategori
        ]);

        return redirect('/laporan/master-kategori')->with('success', 'Kategori baru berhasil ditambahkan!');
    }

    public function editArea($id)
    {
        $area = Area::findOrFail($id);
        return view('laporan.edit_area', compact('area'));
    }

    public function updateArea(Request $request, $id)
    {
        $request->validate(['nama_area' => 'required|string|max:255']);
        Area::where('id', $id)->update(['nama_area' => $request->nama_area]);
        return redirect('/laporan/master-area')->with('success', 'Area berhasil diperbarui!');
    }

    public function destroyArea($id)
    {
        Area::destroy($id);
        return redirect('/laporan/master-area')->with('success', 'Area berhasil dihapus!');
    }

    public function editTeknisi($id)
    {
        $user = User::findOrFail($id);
        return view('laporan.edit_teknisi', compact('user'));
    }

    public function updateTeknisi(Request $request, $id)
    {
        $request->validate(['name' => 'required|string|max:255']);
        User::where('id', $id)->update(['name' => $request->name]);
        return redirect('/laporan/teknisi')->with('success', 'Data teknisi berhasil diperbarui!');
    }

    public function destroyTeknisi($id)
    {
        User::destroy($id);
        return redirect('/laporan/teknisi')->with('success', 'Data teknisi berhasil dihapus!');
    }

    public function editKategori($id)
    {
        $kategori = Kategori::findOrFail($id);
        return view('laporan.edit_kategori', compact('kategori'));
    }

    public function updateKategori(Request $request, $id)
    {
        $request->validate(['nama_kategori' => 'required|string|max:255']);
        Kategori::where('id', $id)->update(['nama_kategori' => $request->nama_kategori]);
        return redirect('/laporan/master-kategori')->with('success', 'Kategori berhasil diperbarui!');
    }

    public function destroyKategori($id)
    {
        Kategori::destroy($id);
        return redirect('/laporan/master-kategori')->with('success', 'Kategori berhasil dihapus!');
    }

    public function editTransaksi($id)
    {
        $transaksi = Transaksi::findOrFail($id);
        $kategoris = Kategori::all();
        $metodes = MetodePembayaran::all();
        $users = User::all();
        $areas = Area::all();

        // Tangkap URL halaman asal (mis. /laporan/kasbon, /laporan/pengeluaran) SEKARANG,
        // selagi url()->previous() masih benar. Nilai ini dikirim lewat hidden input
        // di form supaya tetap akurat walau nanti di-submit via POST/PUT.
        $originUrl = url()->previous();
        if ($originUrl === url()->current()) {
            $originUrl = '/laporan/keuangan';
        }

        return view('laporan.edit_transaksi', compact('transaksi', 'kategoris', 'metodes', 'users', 'areas', 'originUrl'));
    }

    public function updateTransaksi(Request $request, $id)
    {
        $request->validate([
            'tanggal' => 'required|date',
            'jenis_transaksi' => 'required|in:debet,kredit,mutasi', // Sesuaikan jika jenis mutasi diizinkan saat edit
            'kategori_id' => 'required_unless:jenis_transaksi,mutasi',
            'metode_pembayaran_id' => 'required',
            'keterangan' => 'required|string|max:255',
            'nominal' => 'required|numeric|min:0',
            'nota' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        $trx = Transaksi::findOrFail($id);

        $debet = $request->jenis_transaksi === 'debet' ? $request->nominal : 0;
        $kredit = $request->jenis_transaksi === 'kredit' ? $request->nominal : 0;
        $this->recordLog('Edit Transaksi', 'Memperbarui data transaksi ID: ' . $id . ' (' . $request->keterangan . ')');

        // ========================================================
        // [BARU] LOGIKA HAPUS ATAU GANTI NOTA LAMA
        // ========================================================
        $namaFileNota = $trx->nota;

        // Jika checkbox "hapus_nota" dicentang ATAU user mengupload nota baru
        if ($request->has('hapus_nota') || $request->hasFile('nota')) {
            // Hapus file fisik lama dari folder jika ada
            if ($trx->nota && file_exists(public_path('uploads/nota/' . $trx->nota))) {
                unlink(public_path('uploads/nota/' . $trx->nota));
            }
            $namaFileNota = null; // Set null di database secara default
        }

        // Jika user mengupload file nota yang BARU
        if ($request->hasFile('nota')) {
            $file = $request->file('nota');
            $namaFileNota = 'nota_' . time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $file->move(public_path('uploads/nota'), $namaFileNota);
        }
        // ========================================================

        Transaksi::where('id', $id)->update([
            'tanggal' => $request->tanggal,
            'kategori_id' => $request->kategori_id,
            'metode_pembayaran_id' => $request->metode_pembayaran_id,
            'area_id' => $request->area_id ?: null,
            'user_id' => $request->user_id ?: null,
            'keterangan' => $request->keterangan,
            'debet' => $debet,
            'kredit' => $kredit,
            'nota' => $namaFileNota, // Menyimpan nama file baru atau null jika dihapus
        ]);

        return redirect($request->input('origin_url', '/laporan/keuangan'))
            ->with('success', 'Data transaksi & nota berhasil diperbarui!');
    }
    public function destroyTransaksi($id)
    {
        $trx = Transaksi::find($id);

        if (!$trx) {
            return back()->with('error', 'Data transaksi tidak ditemukan.');
        }

        // Cek apakah transaksi ini adalah bagian dari Mutasi (memiliki keterangan [MUTASI MASUK] atau [MUTASI KELUAR])
        if (str_contains($trx->keterangan, '[MUTASI MASUK]') || str_contains($trx->keterangan, '[MUTASI KELUAR]')) {

            // Ambil nominal yang aktif (bisa dari debet atau kredit)
            $nominalTarget = $trx->debet > 0 ? $trx->debet : $trx->kredit;

            // Bersihkan teks tag mutasi untuk mencari pasangannya berdasarkan keterangan asli & nominal yang sama
            $keteranganAsli = str_replace(['[MUTASI MASUK] ', '[MUTASI KELUAR] '], '', $trx->keterangan);

            // [BARU] Ambil semua transaksi mutasi terkait yang memiliki file nota, lalu hapus file fisiknya
            $transaksiMutasiList = Transaksi::where('tanggal', $trx->tanggal)
                ->where(function ($query) use ($nominalTarget) {
                    $query->where('debet', $nominalTarget)->orWhere('kredit', $nominalTarget);
                })
                ->where('keterangan', 'like', '%' . $keteranganAsli . '%')
                ->get();

            foreach ($transaksiMutasiList as $m) {
                if ($m->nota && file_exists(public_path('uploads/nota/' . $m->nota))) {
                    unlink(public_path('uploads/nota/' . $m->nota));
                }
            }

            // Hapus SEMUA transaksi lain yang punya tanggal, nominal, dan keterangan dasar yang sama persis dari database
            Transaksi::where('tanggal', $trx->tanggal)
                ->where(function ($query) use ($nominalTarget) {
                    $query->where('debet', $nominalTarget)->orWhere('kredit', $nominalTarget);
                })
                ->where('keterangan', 'like', '%' . $keteranganAsli . '%')
                ->delete();

            $deskripsiLog = 'Menghapus Mutasi Setor Tunai: ' . $keteranganAsli;
        } else {
            // [BARU] Hapus file fisik nota tunggal jika ada sebelum datanya dihapus
            if ($trx->nota && file_exists(public_path('uploads/nota/' . $trx->nota))) {
                unlink(public_path('uploads/nota/' . $trx->nota));
            }

            // Hapus transaksi normal biasa (Pemasukan / Pengeluaran tunggal)
            $nominalNormal = $trx->debet > 0 ? $trx->debet : $trx->kredit;
            $deskripsiLog = 'Menghapus transaksi: ' . $trx->keterangan . ' (Rp ' . number_format($nominalNormal, 0, ',', '.') . ')';
            $trx->delete();
        }

        $this->recordLog('Hapus Transaksi', $deskripsiLog);

        return back()->with('success', 'Data transaksi beserta file notanya berhasil dihapus bersih!');
    }

    public function exportExcel(Request $request)
    {
        $mulai = $request->input('tanggal_mulai', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $sampai = $request->input('tanggal_selesai', Carbon::now()->endOfMonth()->format('Y-m-d'));

        $transaksiSaldoAwal = \App\Models\Transaksi::whereBetween('tanggal', [$mulai, $sampai])
            ->whereHas('kategori', function ($q) {
                $q->where('nama_kategori', 'like', '%Saldo Awal%');
            })->sum('debet');

        $transaksiSebelumnya = \App\Models\Transaksi::where('tanggal', '<', $mulai)->get();
        $saldoAwalAkumulasi = $transaksiSebelumnya->sum('debet') - $transaksiSebelumnya->sum('kredit');
        $saldoAwal = $saldoAwalAkumulasi + $transaksiSaldoAwal;

        $transaksi = \App\Models\Transaksi::whereBetween('tanggal', [$mulai, $sampai])
            ->whereDoesntHave('kategori', function ($q) {
                $q->where('nama_kategori', 'like', '%Saldo Awal%');
            })
            ->with(['kategori', 'metodePembayaran', 'area', 'user'])
            ->get();

        $totalDebet = $transaksi->sum('debet');
        $totalKredit = $transaksi->sum('kredit');
        $mutasiBerjalan = $totalDebet - $totalKredit;
        $saldoAkhir = $saldoAwal + $mutasiBerjalan;

        $areas = \App\Models\Area::all();

        return Excel::download(
            new KeuanganExport($transaksi, $mulai, $sampai, $saldoAwal, $totalDebet, $totalKredit, $saldoAkhir, $areas),
            'Laporan_Keuangan_' . $mulai . '_sd_' . $sampai . '.xlsx'
        );
    }

    public function exportPdf(Request $request)
    {
        // Ambil filter tanggal
        $mulai = $request->input('tanggal_mulai', \Carbon\Carbon::now()->startOfMonth()->toDateString());
        $sampai = $request->input('tanggal_selesai', \Carbon\Carbon::now()->endOfMonth()->toDateString());

        // =========================================================
        // 1. Saldo Awal (Cash + Bank Global)
        // =========================================================
        $transaksiSaldoAwal = \App\Models\Transaksi::whereBetween('tanggal', [$mulai, $sampai])
            ->whereHas('kategori', function ($q) {
                $q->where('nama_kategori', 'like', '%Saldo Awal%');
            })->sum('debet');

        $transaksiSebelumnya = \App\Models\Transaksi::where('tanggal', '<', $mulai)->get();
        $saldoAwalAkumulasi = $transaksiSebelumnya->sum('debet') - $transaksiSebelumnya->sum('kredit');

        $saldoAwal = $saldoAwalAkumulasi + $transaksiSaldoAwal;

        // =========================================================
        // 2. Transaksi Berjalan (dalam periode filter)
        // =========================================================
        $transaksi = \App\Models\Transaksi::whereBetween('tanggal', [$mulai, $sampai])
            ->whereDoesntHave('kategori', function ($q) {
                $q->where('nama_kategori', 'like', '%Saldo Awal%');
            })
            ->with(['kategori', 'metodePembayaran', 'area', 'user'])
            ->orderBy('tanggal', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        // =========================================================
        // 3. Omzet & Operasional Murni (tanpa Mutasi/Setor Tunai)
        // =========================================================
        $transaksiMurni = $transaksi->filter(function ($r) {
            if (!$r->kategori)
                return true;
            $namaKategori = strtolower($r->kategori->nama_kategori);
            return stripos($namaKategori, 'mutasi') === false && stripos($namaKategori, 'setor') === false;
        });

        $totalDebet = $transaksiMurni->sum('debet');
        $totalKredit = $transaksiMurni->sum('kredit');

        $mutasiBerjalan = $totalDebet - $totalKredit;
        $saldoAkhir = $saldoAwal + $mutasiBerjalan;

        // =========================================================
        // 4a. Saldo Cash saat ini (akumulatif dari awal s.d. akhir periode)
        // =========================================================
        $saldoCashSaatIni = \App\Models\Transaksi::where('tanggal', '<=', $sampai)
            ->where('metode_pembayaran_id', 1) // 1 = Cash
            ->selectRaw('COALESCE(SUM(debet) - SUM(kredit), 0) as saldo')
            ->value('saldo');

        // =========================================================
        // 4b. Split Cash Operasional vs Belum Disetor (Maks 3 Juta)
        // =========================================================
        $PLAFON_CASH_OPERASIONAL = 3000000;

        if ($saldoCashSaatIni <= $PLAFON_CASH_OPERASIONAL) {
            $uangCashOperasional = max(0, $saldoCashSaatIni);
            $uangCashBelumDisetor = 0;
        } else {
            $uangCashOperasional = $PLAFON_CASH_OPERASIONAL;
            $uangCashBelumDisetor = $saldoCashSaatIni - $PLAFON_CASH_OPERASIONAL;
        }

        // =========================================================
        // 4c. Uang Retail di Rekening Faros (jika ada param dicetak, default 0)
        // =========================================================
        $uangRekeningFaros = (float) $request->input('uang_rekening_faros', 0);

        // =========================================================
        // 5. UANG RETAIL DI REKENING PT
        // =========================================================
        $uangRetailDiRekening = $saldoAkhir - $uangCashOperasional - $uangCashBelumDisetor - $uangRekeningFaros;

        // Alias agar view blade lama yang pakai nama variabel saldoAwalCash tidak error
        $saldoAwalCash = $uangCashOperasional;

        // --- Data Pelengkap untuk View ---
        $areas = \App\Models\Area::all();
        $pemasukanPerArea = [];
        foreach ($areas as $area) {
            $pemasukanPerArea[$area->nama_area] = $transaksi->where('area_id', $area->id)->sum('debet');
        }

        $pemasukanLainnya = $transaksi->filter(function ($r) use ($areas) {
            return $r->debet > 0 && is_null($r->area_id) && $r->kategori
                && stripos($r->kategori->nama_kategori, 'Kasbon') === false
                && $r->kategori->nama_kategori !== 'Pemasangan Baru'
                && stripos($r->kategori->nama_kategori, 'Setor') === false
                && stripos($r->kategori->nama_kategori, 'Mutasi') === false;
        });
        foreach ($pemasukanLainnya as $trx) {
            $namaKat = $trx->kategori->nama_kategori;
            $pemasukanPerArea[$namaKat] = ($pemasukanPerArea[$namaKat] ?? 0) + $trx->debet;
        }

        $kasbonMasuk = $transaksi->filter(fn($r) => $r->debet > 0 && $r->kategori && stripos($r->kategori->nama_kategori, 'Kasbon') !== false)->sum('debet');

        $kategoris = \App\Models\Kategori::all();
        $pengeluaranPerKategori = [];
        $totalPengeluaranOperasional = 0;
        foreach ($kategoris as $kat) {
            $nominalKredit = $transaksi->where('kategori_id', $kat->id)->sum('kredit');
            if (
                $nominalKredit > 0
                && stripos($kat->nama_kategori, 'Kasbon') === false
                && stripos($kat->nama_kategori, 'Setor') === false
                && stripos($kat->nama_kategori, 'Mutasi') === false
            ) {
                $pengeluaranPerKategori[$kat->nama_kategori] = $nominalKredit;
                $totalPengeluaranOperasional += $nominalKredit;
            }
        }
        $kasbonKeluar = $transaksi->filter(fn($r) => $r->kredit > 0 && $r->kategori && stripos($r->kategori->nama_kategori, 'Kasbon') !== false)->sum('kredit');

        return view('laporan.export_pdf', compact(
            'saldoAwal',
            'pemasukanPerArea',
            'kasbonMasuk',
            'pengeluaranPerKategori',
            'totalPengeluaranOperasional',
            'kasbonKeluar',
            'totalDebet',
            'totalKredit',
            'saldoAkhir',
            'mulai',
            'sampai',
            'areas',
            'saldoAwalCash',
            'saldoCashSaatIni',
            'uangCashOperasional',
            'uangCashBelumDisetor',
            'uangRekeningFaros',
            'uangRetailDiRekening'
        ));
    }

    public function showLogin()
    {
        return view('auth.login');
    }

    public function processLogin(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::attempt($credentials)) {
            $request->session()->regenerate();

            // Opsional: Catat log jika login sukses
            $this->recordLog('Login Berhasil', 'Pengguna dengan email ' . $request->email . ' berhasil masuk ke sistem dari IP: ' . $request->ip());

            return redirect()->intended('/laporan/keuangan');
        }

        // Catat log gagal login ke database activity_logs
        $this->recordLog('fail2ban', 'Percobaan login gagal untuk email: ' . $request->email . ' dari IP: ' . $request->ip());

        // Format log file (opsional jika masih ingin dicatat di laravel.log)
        Log::warning("[AUTH-FAILED] Failed login attempt from IP: {$request->ip()} for email: {$request->email}");

        return back()->withErrors([
            'email' => 'Email atau password yang anda masukkan salah.',
        ])->onlyInput('email');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }

    private function recordLog($aktivitas, $deskripsi)
    {
        \App\Models\ActivityLog::create([
            'user_id' => auth()->id(),
            'aktivitas' => $aktivitas,
            'deskripsi' => $deskripsi,
        ]);
    }

    public function indexLog(Request $request)
    {
        $mulai = $request->input('tanggal_mulai', \Carbon\Carbon::now()->startOfMonth()->format('Y-m-d'));
        $sampai = $request->input('tanggal_selesai', \Carbon\Carbon::now()->endOfMonth()->format('Y-m-d'));

        $query = \App\Models\ActivityLog::with('user')
            ->whereBetween('created_at', [$mulai . ' 00:00:00', $sampai . ' 23:59:59'])
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'asc');

        if ($request->has('search') && $request->search != '') {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('aktivitas', 'like', '%' . $search . '%')
                    ->orWhere('deskripsi', 'like', '%' . $search . '%')
                    ->orWhereHas('user', function ($qUser) use ($search) {
                        $qUser->where('name', 'like', '%' . $search . '%');
                    });
            });
        }

        $logs = $query->paginate(20);

        return view('laporan.activity_log', compact('logs', 'mulai', 'sampai'));
    }

    // --- HELPER UNTUK SYNCHRONIZE FAIL2BAN CONFIG ---
    private function generateFail2banConfig()
    {
        $allowedIps = \App\Models\FirewallIp::pluck('ip_address')->toArray();
        $defaultIgnore = ['127.0.0.1', '118.99.116.4', '103.148.197.17'];
        $allIgnoreIps = array_unique(array_merge($defaultIgnore, $allowedIps));
        $ignoreIpString = implode(' ', $allIgnoreIps);

        $configContent = "[laravel-auth]\n" .
            "enabled = true\n" .
            "filter = laravel-auth\n" .
            "backend = auto\n" .
            "logpath = /var/www/html/finance/storage/logs/laravel.log\n" .
            "port = 8002\n" .
            "action = iptables-multiport[name=laravel-auth, port=\"8002\", protocol=tcp]\n" .
            "maxretry = 3\n" .
            "findtime = 600\n" .
            "bantime = 3600\n" .
            "ignoreip = " . $ignoreIpString . "\n";

        $tempPath = storage_path('app/laravel-auth.local');
        file_put_contents($tempPath, $configContent);

        // Eksekusi sudo cp dan restart fail2ban sesuai izin visudo abang
        shell_exec('sudo cp ' . $tempPath . ' /etc/fail2ban/jail.d/laravel-auth.local');
        shell_exec('sudo systemctl restart fail2ban');
    }

    public function firewallManagement()
    {
        // 1. Whitelist untuk Aplikasi Web
        $allowedIps = \App\Models\FirewallIp::all();

        // 2. Konfigurasi khusus Fail2ban dari tabel terpisah
        $fail2banConfig = \App\Models\Fail2banConfig::first();
        $fail2banIps = [];
        if ($fail2banConfig && !empty($fail2banConfig->ignoreip)) {
            $fail2banIps = array_filter(explode(' ', trim($fail2banConfig->ignoreip)));
        }

        $activeSessions = \DB::table('sessions')->leftJoin('users', 'sessions.user_id', '=', 'users.id')->select('sessions.*', 'users.name')->get();

        return view('laporan.firewall', compact('allowedIps', 'fail2banConfig', 'fail2banIps', 'activeSessions'));
    }

    public function storeFail2banIp(Request $request)
    {
        $request->validate(['ip_address' => 'required|ip']);
        $newIp = $request->ip_address;

        // Ambil atau buat konfigurasi default fail2ban di database terpisah
        $config = \App\Models\Fail2banConfig::firstOrCreate(
            ['jail_name' => 'laravel-auth'],
            ['maxretry' => 3, 'bantime' => 3600, 'ignoreip' => '127.0.0.1 118.99.116.4 103.148.197.17']
        );

        $currentIps = array_filter(explode(' ', trim($config->ignoreip ?? '')));

        if (!in_array($newIp, $currentIps)) {
            $currentIps[] = $newIp;
            $config->ignoreip = implode(' ', $currentIps);
            $config->save();
        }

        // Sinkronisasi otomatis ke file jail.d & restart fail2ban
        $this->generateFail2banConfigFromDedicatedDb();

        return back()->with('success', 'IP Address berhasil ditambahkan ke Fail2ban Whitelist!');
    }

    public function destroyFail2banIp(Request $request)
    {
        $ipToRemove = $request->input('ip');

        $config = \App\Models\Fail2banConfig::first();
        if ($config && !empty($config->ignoreip)) {
            $currentIps = array_filter(explode(' ', trim($config->ignoreip)));

            // Buang IP yang dicabut
            $updatedIps = array_diff($currentIps, [$ipToRemove]);

            $config->ignoreip = implode(' ', $updatedIps);
            $config->save();
        }

        // Sinkronisasi ulang
        $this->generateFail2banConfigFromDedicatedDb();

        return back()->with('success', 'IP Address berhasil dicabut dari Fail2ban Whitelist!');
    }

    // Update Pengaturan MaxRetry & BanTime
    public function updateFail2banConfig(Request $request)
    {
        $request->validate([
            'maxretry' => 'required|integer|min:1',
            'bantime' => 'required|integer|min:1', // Dalam detik, misal 3600 (1 jam) atau nilai besar untuk permanen
        ]);

        $config = \App\Models\Fail2banConfig::first();
        if ($config) {
            $config->maxretry = $request->maxretry;
            $config->bantime = $request->bantime;
            $config->save();
        }

        // Regenerate file konfigurasi & restart fail2ban
        $this->generateFail2banConfigFromDedicatedDb();

        return back()->with('success', 'Pengaturan Fail2ban (Max Retry & Ban Time) berhasil diperbarui!');
    }

    // Fitur Unban IP yang terblokir
    public function unbanFail2banIp(Request $request)
    {
        $request->validate(['ip' => 'required|ip']);
        $ipToUnban = $request->ip;

        // Eksekusi perintah fail2ban-client untuk unban IP pada jail laravel-auth
        $output = shell_exec('sudo fail2ban-client set laravel-auth unbanip ' . $ipToUnban);

        return back()->with('success', 'IP Address ' . $ipToUnban . ' berhasil di-unban dari Fail2ban!');
    }

    private function generateFail2banConfigFromDedicatedDb()
    {
        $config = \App\Models\Fail2banConfig::first();
        $ignoreIpString = $config ? $config->ignoreip : '127.0.0.1';
        $maxretry = $config ? $config->maxretry : 3;
        $bantime = $config ? $config->bantime : 3600;

        $configContent = "[laravel-auth]\n" .
            "enabled = true\n" .
            "filter = laravel-auth\n" .
            "backend = auto\n" .
            "logpath = /var/www/html/finance/storage/logs/laravel.log\n" .
            "port = 8002\n" .
            "action = iptables-multiport[name=laravel-auth, port=\"8002\", protocol=tcp]\n" .
            "maxretry = {$maxretry}\n" .
            "findtime = 600\n" .
            "bantime = {$bantime}\n" .
            "ignoreip = " . $ignoreIpString . "\n";

        $tempPath = storage_path('app/laravel-auth.local');
        file_put_contents($tempPath, $configContent);

        shell_exec('sudo cp ' . $tempPath . ' /etc/fail2ban/jail.d/laravel-auth.local');
        shell_exec('sudo systemctl restart fail2ban');
    }

    public function storeIp(Request $request)
    {
        $request->validate([
            'ip_address' => 'required|ip',
            'keterangan' => 'required|string|max:255'
        ]);

        // Menggunakan updateOrCreate: Jika IP sudah ada, update keterangannya. Jika belum, buat baru.
        FirewallIp::updateOrCreate(
            ['ip_address' => $request->ip_address],
            ['keterangan' => $request->keterangan]
        );

        return back()->with('success', 'IP Address berhasil disimpan ke dalam whitelist!');
    }

    public function destroyIp($id)
    {
        FirewallIp::destroy($id);
        return back()->with('success', 'IP Address berhasil dicabut dari whitelist!');
    }

    // --- HALAMAN STATISTIK KEUANGAN ---
    public function statistik(Request $request)
    {
        $tahun = $request->input('tahun', date('Y'));
        $bulan = $request->input('bulan', 'all');

        // Ambil transaksi di tahun tersebut (Kecuali Saldo Awal)
        $query = \App\Models\Transaksi::with('kategori')
            ->whereYear('tanggal', $tahun)
            ->whereDoesntHave('kategori', function ($q) {
                $q->where('nama_kategori', 'like', '%Saldo Awal%')
                    ->orWhere('nama_kategori', 'like', '%Mutasi%')
                    ->orWhere('nama_kategori', 'like', '%Setor%');
            });

        // Jika filter bulan spesifik dipilih
        if ($bulan !== 'all') {
            $query->whereMonth('tanggal', $bulan);
        }

        $transaksi = $query->get();

        // 1. Data untuk Bar Chart
        if ($bulan === 'all') {
            // Jika Semua Bulan = Tampilkan 12 Bulan
            $chartLabels = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
            $pemasukanData = array_fill(0, 12, 0);
            $pengeluaranData = array_fill(0, 12, 0);

            foreach ($transaksi as $trx) {
                $index = date('n', strtotime($trx->tanggal)) - 1;
                if ($trx->debet > 0)
                    $pemasukanData[$index] += $trx->debet;
                if ($trx->kredit > 0)
                    $pengeluaranData[$index] += $trx->kredit;
            }
        } else {
            // Jika 1 Bulan Spesifik = Tampilkan Tanggal (1 s.d 30/31)
            $jumlahHari = cal_days_in_month(CAL_GREGORIAN, $bulan, $tahun);
            $chartLabels = [];
            $pemasukanData = array_fill(0, $jumlahHari, 0);
            $pengeluaranData = array_fill(0, $jumlahHari, 0);

            for ($i = 1; $i <= $jumlahHari; $i++) {
                $chartLabels[] = 'Tgl ' . $i;
            }

            foreach ($transaksi as $trx) {
                $index = date('j', strtotime($trx->tanggal)) - 1;
                if ($trx->debet > 0)
                    $pemasukanData[$index] += $trx->debet;
                if ($trx->kredit > 0)
                    $pengeluaranData[$index] += $trx->kredit;
            }
        }

        // 2. Data untuk Pie Chart (Per Kategori)
        $piePemasukan = [];
        $piePengeluaran = [];

        foreach ($transaksi as $trx) {
            $namaKategori = $trx->kategori ? $trx->kategori->nama_kategori : 'Tanpa Kategori';

            if ($trx->debet > 0) {
                $piePemasukan[$namaKategori] = ($piePemasukan[$namaKategori] ?? 0) + $trx->debet;
            }
            if ($trx->kredit > 0) {
                $piePengeluaran[$namaKategori] = ($piePengeluaran[$namaKategori] ?? 0) + $trx->kredit;
            }
        }

        // Hitung total
        $totalPemasukan = array_sum($pemasukanData);
        $totalPengeluaran = array_sum($pengeluaranData);
        $labaRugi = $totalPemasukan - $totalPengeluaran;

        return view('laporan.statistic', compact(
            'tahun',
            'bulan',
            'chartLabels',
            'pemasukanData',
            'pengeluaranData',
            'totalPemasukan',
            'totalPengeluaran',
            'labaRugi',
            'piePemasukan',
            'piePengeluaran'
        ));
    }

    // Tampilkan Halaman Edit Profil Akun yang Sedang Login
    public function editProfile()
    {
        $user = auth()->user();
        return view('laporan.edit_profile', compact('user'));
    }

    // Simpan Perubahan Profil (Nama, Email, Password)
    public function updateProfile(Request $request)
    {
        $user = auth()->user();

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'password' => 'nullable|min:6|confirmed', // Memerlukan input konfirmasi password jika diisi
        ]);

        $user->name = $request->name;
        $user->email = $request->email;

        // Jika kolom password diisi, update password baru (di-hash)
        if ($request->filled('password')) {
            $user->password = bcrypt($request->password);
        }

        $user->save();

        return back()->with('success', 'Profil, email, dan password berhasil diperbarui!');
    }

    public function killSession($id)
    {
        \DB::table('sessions')->where('id', $id)->delete();
        return back()->with('success', 'Sesi pengguna berhasil diputus (Force Logout)!');
    }
    public function exportNotaPdf(Request $request)
    {
        $mulai = $request->input('tanggal_mulai', \Carbon\Carbon::now()->startOfMonth()->toDateString());
        $sampai = $request->input('tanggal_selesai', \Carbon\Carbon::now()->endOfMonth()->toDateString());

        // Ambil SEMUA transaksi (Pengeluaran maupun Kasbon Teknisi) yang punya pengeluaran (kredit > 0) dan ada notanya
        $transaksi = \App\Models\Transaksi::whereBetween('tanggal', [$mulai, $sampai])
            ->where('kredit', '>', 0)
            ->whereNotNull('nota')
            ->where('nota', '!=', '')
            ->with(['kategori', 'user'])
            ->orderBy('tanggal', 'asc')
            ->get();

        return view('laporan.export_nota_pdf', compact('transaksi', 'mulai', 'sampai'));
    }
}