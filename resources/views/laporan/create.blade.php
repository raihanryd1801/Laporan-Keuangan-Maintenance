@extends('layouts.app')

@php
    // Menangkap parameter 'jenis' dari URL untuk set default dropdown
    $defaultJenis = request()->query('jenis', 'debet');
@endphp

@section('title', 'Form Input Transaksi')

@section('content')
    <div class="header">
        <h2 id="form_title">Form Input Transaksi Retail</h2>
        <a href="{{ url('/laporan/menu-input') }}" style="text-decoration: none; color: #7f8c8d;">&larr; Kembali ke Menu
            Input</a>
    </div>

    <!-- [BARU] Tambahkan enctype="multipart/form-data" agar bisa upload file -->
    <form action="{{ url('/laporan/transaksi/simpan') }}" method="POST" style="max-width: 600px; margin: 0 auto;"
        enctype="multipart/form-data">
        @csrf

        <div style="margin-bottom: 15px;">
            <label style="display: block; font-weight: bold; margin-bottom: 5px;">Tanggal Transaksi</label>
            <input type="date" name="tanggal" value="{{ \Carbon\Carbon::now()->format('Y-m-d') }}" required
                style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;">
        </div>

        <div style="margin-bottom: 15px;">
            <label style="display: block; font-weight: bold; margin-bottom: 5px;">Jenis Transaksi:</label>
            <select name="jenis_transaksi" id="jenis_transaksi"
                style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;" required>
                <option value="debet" {{ $defaultJenis == 'debet' ? 'selected' : '' }}>Pemasukan (Debet)</option>
                <option value="kredit" {{ $defaultJenis == 'kredit' ? 'selected' : '' }}>Pengeluaran / Kasbon (Kredit)
                </option>
                <option value="mutasi" {{ $defaultJenis == 'mutasi' ? 'selected' : '' }}>Setor Tunai ke Bank (Mutasi)</option>
            </select>
        </div>

        <div style="margin-bottom: 15px;" id="div_kategori">
            <label style="display: block; font-weight: bold; margin-bottom: 5px;">Kategori</label>
            <select name="kategori_id" id="kategori_id" required
                style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;">
                <option value="">-- Pilih Kategori --</option>
                @foreach($kategoris as $kategori)
                    <option value="{{ $kategori->id }}">{{ $kategori->nama_kategori }}</option>
                @endforeach
            </select>
            <small id="kategori_helper" style="color: #7f8c8d; display: none;">*Kategori otomatis diset ke "Mutasi" oleh
                sistem.</small>
        </div>

        <!-- PILIHAN AREA -->
        <div style="margin-bottom: 15px;" id="div_area">
            <label style="display: block; font-weight: bold; margin-bottom: 5px;">Pilih Area / Wilayah (Khusus
                Retail)</label>
            <select name="area_id" style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;">
                <option value="">-- Pilih Area (Opsional / Umum) --</option>
                @foreach($areas as $area)
                    <option value="{{ $area->id }}">{{ $area->nama_area }}</option>
                @endforeach
            </select>
            <small style="color: #7f8c8d;">*Pilih area jika ini transaksi pemasangan/pemasukan retail wilayah
                tertentu.</small>
        </div>

        <!-- METODE PEMBAYARAN -->
        <div style="margin-bottom: 15px;" id="div_metode">
            <label id="label_metode" style="display: block; font-weight: bold; margin-bottom: 5px;">Metode Pembayaran /
                Tempat Uang Masuk</label>
            <select name="metode_pembayaran_id" id="metode_pembayaran_id" required
                style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;">
                @foreach($metodes as $metode)
                    <option value="{{ $metode->id }}">{{ $metode->nama_metode }}</option>
                @endforeach
            </select>
            <small id="metode_helper" style="color: #7f8c8d; display: none;">*Pilih bank tempat uang tunai
                disetorkan.</small>
        </div>

        <!-- NAMA TEKNISI -->
        <div style="margin-bottom: 15px;" id="div_teknisi">
            <label style="display: block; font-weight: bold; margin-bottom: 5px;">Nama Teknisi (Khusus KASBON)</label>
            <select name="user_id" style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;">
                <option value="">-- Bukan Kasbon (Abaikan) --</option>
                @foreach($users as $user)
                    <option value="{{ $user->id }}">{{ $user->name }}</option>
                @endforeach
            </select>
        </div>

        <div style="margin-bottom: 15px;">
            <label style="display: block; font-weight: bold; margin-bottom: 5px;">Keterangan / Deskripsi</label>
            <textarea name="keterangan" id="keterangan" rows="3" required
                placeholder="Contoh: Pembayaran internet bulanan a.n Budi"
                style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;"></textarea>
        </div>

        <!-- BAGIAN INPUT NOMINAL DENGAN KALKULATOR OTOMATIS -->
        <div style="margin-bottom: 20px;">
            <label style="display: block; font-weight: bold; margin-bottom: 5px; color: #2c3e50;">
                Nominal Transaksi (Bisa pakai rumus) <span style="color:red;">*</span>
            </label>
            <p style="font-size: 11.5px; color: #7f8c8d; margin-bottom: 8px; line-height: 1.4;">
                Anda bisa langsung mengetik angka total (contoh: <b>2600000</b>) atau menjumlahkan rinciannya dengan rumus
                matematika (contoh: <b>4*100000 + 20*110000</b>). Sistem akan otomatis menghitungnya.
            </p>

            <div style="display: flex; align-items: center;">
                <span
                    style="padding: 10px 15px; background: #e9ecef; border: 1px solid #ced4da; border-right: none; border-radius: 4px 0 0 4px; font-weight: bold; color: #495057;">
                    Rp
                </span>
                <input type="text" id="input_rumus_nominal" class="form-control"
                    placeholder="Contoh rumus: 4*100000 + 20*110000" value="{{ old('nominal') }}"
                    style="width: 100%; padding: 10px; border: 1px solid #ced4da; border-radius: 0 4px 4px 0; font-size: 14px;"
                    autocomplete="off" required>
                <input type="hidden" name="nominal" id="nominal_asli" value="{{ old('nominal') }}" required>
            </div>

            <div id="tampil_hasil"
                style="margin-top: 8px; font-size: 15px; font-weight: 800; color: #27ae60; display: none;">
                = Rp 0
            </div>
        </div>

        <!-- [BARU] BAGIAN UPLOAD NOTA FISIK -->
        <div style="margin-bottom: 20px; padding: 15px; border: 1px dashed #3498db; background-color: #ebf5fb; border-radius: 6px; display: none;"
            id="div_nota">
            <label id="label_nota" style="display: block; font-weight: bold; margin-bottom: 5px; color: #2980b9;">
                Upload Bukti Nota / Dokumen Fisik (Opsional)
            </label>
            <input type="file" name="nota" id="nota" class="form-control" accept="image/jpeg, image/png, image/jpg"
                style="width: 100%; padding: 8px; border: 1px solid #bdc3c7; border-radius: 4px; background: #fff;">
            <small style="color: #7f8c8d; display: block; margin-top: 5px;">*Format gambar (JPG, PNG). Maksimal 2MB.</small>
        </div>

        <button type="submit" id="btn_submit"
            style="width: 100%; padding: 12px; background-color: #2ecc71; color: white; border: none; border-radius: 4px; font-size: 16px; font-weight: bold; cursor: pointer; transition: 0.3s;">
            Simpan Transaksi
        </button>
    </form>

    <script>
        document.addEventListener("DOMContentLoaded", function () {
            // --- LOGIKA KALKULATOR NOMINAL ---
            let inputRumus = document.getElementById('input_rumus_nominal');
            let hiddenNominal = document.getElementById('nominal_asli');
            let tampilHasil = document.getElementById('tampil_hasil');

            function kalkulasiOtomatis() {
                let inputVal = inputRumus.value;
                let sanitized = inputVal.replace(/[^0-9+\-*/(). ]/g, '');

                if (sanitized !== inputVal) {
                    inputRumus.value = sanitized;
                }

                if (sanitized.trim() === '') {
                    tampilHasil.style.display = 'none';
                    hiddenNominal.value = '';
                    return;
                }

                try {
                    let hitung = new Function('return ' + sanitized)();
                    if (hitung !== undefined && !isNaN(hitung) && isFinite(hitung)) {
                        hiddenNominal.value = hitung;
                        tampilHasil.innerHTML = '= Rp ' + hitung.toLocaleString('id-ID');
                        tampilHasil.style.display = 'block';
                    } else {
                        tampilHasil.style.display = 'none';
                        hiddenNominal.value = '';
                    }
                } catch (error) {
                    tampilHasil.style.display = 'none';
                    hiddenNominal.value = '';
                }
            }

            inputRumus.addEventListener('input', kalkulasiOtomatis);
            if (inputRumus.value !== '') {
                kalkulasiOtomatis();
            }

            // --- LOGIKA DINAMIS JENIS TRANSAKSI (MUTASI, DEBET, KREDIT) ---
            let selectJenis = document.getElementById('jenis_transaksi');
            let formTitle = document.getElementById('form_title');
            let btnSubmit = document.getElementById('btn_submit');
            let divArea = document.getElementById('div_area');
            let divTeknisi = document.getElementById('div_teknisi');
            let inputKeterangan = document.getElementById('keterangan');

            // Elemen tambahan untuk penyesuaian UX
            let selectKategori = document.getElementById('kategori_id');
            let kategoriHelper = document.getElementById('kategori_helper');
            let divKategori = document.getElementById('div_kategori');
            let labelMetode = document.getElementById('label_metode');
            let selectMetode = document.getElementById('metode_pembayaran_id');
            let metodeHelper = document.getElementById('metode_helper');

            // Elemen Form Upload Nota
            let divNota = document.getElementById('div_nota');
            let labelNota = document.getElementById('label_nota');

            function sesuaikanForm() {
                let jenis = selectJenis.value;

                // Reset Tampilan ke Kondisi Normal (Debet/Kredit)
                divKategori.style.display = 'block';
                selectKategori.required = true;
                kategoriHelper.style.display = 'none';

                // Sembunyikan form upload nota secara default, hanya tampil di pengeluaran/kasbon & mutasi
                divNota.style.display = 'none';

                // Pastikan opsi 'Cash' muncul kembali di Metode Pembayaran
                Array.from(selectMetode.options).forEach(opt => {
                    opt.style.display = '';
                });

                if (jenis === 'mutasi') {
                    formTitle.innerHTML = '🟣 Form Setor Tunai ke Bank';
                    btnSubmit.style.backgroundColor = '#9b59b6'; // Ungu
                    btnSubmit.innerHTML = 'Simpan Setor Bank';

                    // Sembunyikan field yang tidak perlu
                    divArea.style.display = 'none';
                    divTeknisi.style.display = 'none';

                    // Sembunyikan pilihan Kategori karena dikendalikan oleh Controller
                    divKategori.style.display = 'none';
                    selectKategori.required = false;

                    // Ubah label Metode Pembayaran menjadi Bank Tujuan
                    labelMetode.innerHTML = "Pilih Bank Tujuan Saldo";
                    metodeHelper.style.display = "block";

                    // Tampilkan field nota bukti setor bank
                    divNota.style.display = 'block';
                    labelNota.innerHTML = "Upload Bukti Setor / Transfer Bank (Opsional)";

                    // (Opsional) Sembunyikan 'Cash' dari pilihan bank tujuan karena tidak mungkin setor tunai dari tunai ke tunai
                    Array.from(selectMetode.options).forEach(opt => {
                        if (opt.text.toLowerCase().includes('cash') || opt.text.toLowerCase().includes('tunai')) {
                            opt.style.display = 'none';
                            // Jika saat ini terpilih, ubah pilihan ke index 0
                            if (opt.selected) selectMetode.selectedIndex = 0;
                        }
                    });

                    if (inputKeterangan.value === '') {
                        inputKeterangan.value = 'Setor tunai penerimaan retail ke rekening Bank';
                    }

                } else if (jenis === 'kredit') {
                    formTitle.innerHTML = '🔴 Form Pengeluaran / Kasbon';
                    btnSubmit.style.backgroundColor = '#e74c3c'; // Merah
                    btnSubmit.innerHTML = 'Simpan Pengeluaran';

                    divArea.style.display = 'none';
                    divTeknisi.style.display = 'block';

                    labelMetode.innerHTML = "Sumber Dana (Uang diambil dari mana?)";
                    metodeHelper.style.display = "none";

                    // Tampilkan form upload nota untuk pengeluaran / kasbon teknisi
                    divNota.style.display = 'block';
                    labelNota.innerHTML = "Upload Bukti Nota Pengeluaran / Nota Kasbon (Opsional)";

                    if (inputKeterangan.value === 'Setor tunai penerimaan retail ke rekening Bank') {
                        inputKeterangan.value = '';
                    }

                } else {
                    formTitle.innerHTML = '🟢 Form Input Pemasukan Retail';
                    btnSubmit.style.backgroundColor = '#2ecc71'; // Hijau
                    btnSubmit.innerHTML = 'Simpan Pemasukan';

                    divArea.style.display = 'block';
                    divTeknisi.style.display = 'none';

                    labelMetode.innerHTML = "Metode Pembayaran (Tempat uang masuk)";
                    metodeHelper.style.display = "none";

                    if (inputKeterangan.value === 'Setor tunai penerimaan retail ke rekening Bank') {
                        inputKeterangan.value = '';
                    }
                }
            }

            sesuaikanForm();
            selectJenis.addEventListener('change', sesuaikanForm);
        });
    </script>
@endsection