@extends('layouts.app')
@section('title', 'Edit Transaksi Keuangan')

@section('content')
    <div style="
                                display: flex;
                                justify-content: center;
                                align-items: center;
                                min-height: 80vh;
                                padding: 20px;
                            ">
        <div style="
                                    width: 100%;
                                    max-width: 750px;
                                    background: #fff;
                                    padding: 35px 40px;
                                    border-radius: 12px;
                                    box-shadow: 0 6px 20px rgba(0,0,0,0.06);
                                ">
            <h2 style="
                                        font-size: 22px;
                                        font-weight: 600;
                                        color: #2c3e50;
                                        margin-top: 0;
                                        margin-bottom: 25px;
                                        text-align: center;
                                        border-bottom: 2px solid #ecf0f1;
                                        padding-bottom: 15px;
                                    ">
                Edit Data Transaksi
            </h2>

            <!-- [BARU] Tambahkan enctype="multipart/form-data" -->
            <form action="{{ url('/laporan/transaksi/update/' . $transaksi->id) }}" method="POST"
                enctype="multipart/form-data">
                @csrf
                @method('PUT')

                <!-- Tanggal -->
                <div style="margin-bottom: 18px;">
                    <label
                        style="display: block; font-weight: 600; margin-bottom: 6px; font-size: 14px; color: #34495e;">Tanggal
                        Transaksi</label>
                    <input type="date" name="tanggal" value="{{ $transaksi->tanggal }}" required
                        style="width: 100%; padding: 10px 14px; border: 1px solid #dce1e8; border-radius: 6px; font-size: 15px; transition: border-color 0.2s;">
                </div>

                @php
                    $isMutasi = str_contains($transaksi->keterangan, '[MUTASI MASUK]') || str_contains($transaksi->keterangan, '[MUTASI KELUAR]');
                @endphp

                <!-- Jenis Transaksi (Debet / Kredit / Mutasi) -->
                <div style="margin-bottom: 18px;">
                    <label
                        style="display: block; font-weight: 600; margin-bottom: 6px; font-size: 14px; color: #34495e;">Jenis
                        Transaksi</label>
                    <select name="jenis_transaksi" id="jenis_transaksi" required onchange="toggleJenisTransaksi()"
                        style="width: 100%; padding: 10px 14px; border: 1px solid #dce1e8; border-radius: 6px; font-size: 15px; background: #fff; transition: border-color 0.2s;">
                        <option value="debet" {{ (!$isMutasi && $transaksi->debet > 0) ? 'selected' : '' }}>Pemasukan
                            (Debet)</option>
                        <option value="kredit" {{ (!$isMutasi && $transaksi->kredit > 0) ? 'selected' : '' }}>Pengeluaran /
                            Kasbon (Kredit)</option>
                        <option value="mutasi" {{ $isMutasi ? 'selected' : '' }}>Setor Tunai / Mutasi</option>
                    </select>
                </div>

                <!-- Kategori -->
                <div class="form-group-kategori" style="margin-bottom: 18px; {{ $isMutasi ? 'display:none;' : '' }}">
                    <label
                        style="display: block; font-weight: 600; margin-bottom: 6px; font-size: 14px; color: #34495e;">Kategori
                        Transaksi</label>
                    <select name="kategori_id" id="kategori_id" {{ !$isMutasi ? 'required' : '' }}
                        style="width: 100%; padding: 10px 14px; border: 1px solid #dce1e8; border-radius: 6px; font-size: 15px; background: #fff; transition: border-color 0.2s;">
                        <option value="">-- Pilih Kategori --</option>
                        @foreach($kategoris as $kat)
                            <option value="{{ $kat->id }}" {{ $transaksi->kategori_id == $kat->id ? 'selected' : '' }}>
                                {{ $kat->nama_kategori }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Metode Pembayaran / Tujuan Bank -->
                <div style="margin-bottom: 18px;">
                    <label style="display: block; font-weight: 600; margin-bottom: 6px; font-size: 14px; color: #34495e;"
                        id="label-metode">Metode Pembayaran</label>
                    <select name="metode_pembayaran_id" id="metode_pembayaran_id" required
                        style="width: 100%; padding: 10px 14px; border: 1px solid #dce1e8; border-radius: 6px; font-size: 15px; background: #fff; transition: border-color 0.2s;">
                        @foreach($metodes as $metode)
                            @php
                                $namaMetodeLower = strtolower($metode->nama_metode);
                                $isCashMetode = str_contains($namaMetodeLower, 'cash') || str_contains($namaMetodeLower, 'tunai') || str_contains($namaMetodeLower, 'kas');
                            @endphp
                            <option value="{{ $metode->id }}" data-iscash="{{ $isCashMetode ? '1' : '0' }}" {{ $transaksi->metode_pembayaran_id == $metode->id ? 'selected' : '' }}>
                                {{ $metode->nama_metode }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Area / Lokasi -->
                <div style="margin-bottom: 18px;">
                    <label
                        style="display: block; font-weight: 600; margin-bottom: 6px; font-size: 14px; color: #34495e;">Area
                        / Lokasi (Opsional)</label>
                    <select name="area_id"
                        style="width: 100%; padding: 10px 14px; border: 1px solid #dce1e8; border-radius: 6px; font-size: 15px; background: #fff; transition: border-color 0.2s;">
                        <option value="">-- Umum / Pusat --</option>
                        @foreach($areas as $area)
                            <option value="{{ $area->id }}" {{ $transaksi->area_id == $area->id ? 'selected' : '' }}>
                                {{ $area->nama_area }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Teknisi / Pegawai -->
                <div style="margin-bottom: 18px;">
                    <label
                        style="display: block; font-weight: 600; margin-bottom: 6px; font-size: 14px; color: #34495e;">Nama
                        Teknisi / Pegawai (Opsional / Kasbon)</label>
                    <select name="user_id"
                        style="width: 100%; padding: 10px 14px; border: 1px solid #dce1e8; border-radius: 6px; font-size: 15px; background: #fff; transition: border-color 0.2s;">
                        <option value="">-- Tidak Ada --</option>
                        @foreach($users as $user)
                            <option value="{{ $user->id }}" {{ $transaksi->user_id == $user->id ? 'selected' : '' }}>
                                {{ $user->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Keterangan -->
                <div style="margin-bottom: 18px;">
                    <label
                        style="display: block; font-weight: 600; margin-bottom: 6px; font-size: 14px; color: #34495e;">Keterangan</label>
                    <input type="text" name="keterangan" value="{{ $transaksi->keterangan }}" required
                        style="width: 100%; padding: 10px 14px; border: 1px solid #dce1e8; border-radius: 6px; font-size: 15px; transition: border-color 0.2s;">
                </div>

                <!-- Nominal -->
                <div style="margin-bottom: 25px;">
                    <label
                        style="display: block; font-weight: 600; margin-bottom: 6px; font-size: 14px; color: #34495e;">Nominal
                        (Rp)</label>
                    <input type="number" name="nominal"
                        value="{{ $transaksi->debet > 0 ? $transaksi->debet : $transaksi->kredit }}" required
                        style="width: 100%; padding: 10px 14px; border: 1px solid #dce1e8; border-radius: 6px; font-size: 15px; transition: border-color 0.2s;">
                </div>

                <!-- [BARU] BAGIAN UPLOAD & PREVIEW NOTA FISIK -->
                <!-- BAGIAN UPLOAD & PREVIEW NOTA FISIK -->
                <div id="div_nota"
                    style="margin-bottom: 25px; padding: 15px; border: 1px dashed #3498db; background-color: #ebf5fb; border-radius: 6px; display: none;">
                    <label style="display: block; font-weight: bold; margin-bottom: 5px; color: #2980b9;">
                        Upload / Kelola Bukti Nota Fisik
                    </label>

                    @if($transaksi->nota)
                        <div
                            style="margin-bottom: 12px; padding: 10px; background: #fff; border-radius: 4px; border: 1px solid #dce1e8;">
                            <p style="font-size: 12px; color: #7f8c8d; margin-top: 0; margin-bottom: 8px; font-weight: bold;">
                                Nota saat ini terlampir:</p>
                            <img src="{{ asset('uploads/nota/' . $transaksi->nota) }}" alt="Nota Bukti"
                                style="max-height: 120px; border-radius: 4px; border: 1px solid #ccc; object-fit: contain; display: block; margin-bottom: 8px;">

                            <!-- [BARU] Checkbox untuk Hapus Nota -->
                            <label
                                style="font-size: 13px; color: #c0392b; cursor: pointer; font-weight: 600; display: inline-flex; align-items: center; gap: 6px;">
                                <input type="checkbox" name="hapus_nota" value="1" style="width: 15px; height: 15px;">
                                Hapus / Buang nota ini tanpa menggantinya
                            </label>
                        </div>
                    @endif

                    <input type="file" name="nota" id="nota" class="form-control" accept="image/jpeg, image/png, image/jpg"
                        style="width: 100%; padding: 8px; border: 1px solid #bdc3c7; border-radius: 4px; background: #fff;">
                    <small style="color: #7f8c8d; display: block; margin-top: 5px;">*Format gambar (JPG, PNG) Maks
                        2MB.</small>
                </div>

                <!-- Tombol Aksi -->
                <div style="display: flex; gap: 12px;">
                    <button type="submit" style="
                                                background: #f39c12;
                                                color: white;
                                                border: none;
                                                padding: 12px 24px;
                                                border-radius: 6px;
                                                font-weight: 600;
                                                font-size: 15px;
                                                cursor: pointer;
                                                flex: 1;
                                                transition: background 0.2s;
                                            " onmouseover="this.style.background='#e08e0b'"
                        onmouseout="this.style.background='#f39c12'">
                        Simpan Perubahan
                    </button>
                    <a href="{{ url()->previous() }}" style="
                        text-decoration: none;
                        background: #95a5a6;
                        color: white;
                        padding: 12px 24px;
                        border-radius: 6px;
                        font-weight: 600;
                        font-size: 15px;
                        text-align: center;
                        flex: 0.5;
                        transition: background 0.2s;
                    " onmouseover="this.style.background='#7f8c8d'" onmouseout="this.style.background='#95a5a6'">
                        Batal / Kembali
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Script Filter Otomatis saat Mutasi Dipilih -->
    <script>
        function toggleJenisTransaksi() {
            const jenis = document.getElementById('jenis_transaksi').value;
            const kategoriDiv = document.querySelector('.form-group-kategori');
            const kategoriSelect = document.getElementById('kategori_id');
            const labelMetode = document.getElementById('label-metode');
            const metodeSelect = document.getElementById('metode_pembayaran_id');
            const divNota = document.getElementById('div_nota'); // Elemen Nota

            if (jenis === 'mutasi') {
                kategoriDiv.style.display = 'none';
                kategoriSelect.removeAttribute('required');
                kategoriSelect.value = ''; // kosongkan jika mutasi

                labelMetode.innerText = 'Pilih Tujuan Bank (Setor Tunai)';
                divNota.style.display = 'block'; // Tampilkan form nota (opsional buat mutasi)

                // Sembunyikan opsi metode cash/tunai
                for (let option of metodeSelect.options) {
                    if (option.getAttribute('data-iscash') === '1') {
                        option.style.display = 'none';
                        if (option.selected) metodeSelect.value = ''; // reset jika tadinya cash
                    }
                }
            } else if (jenis === 'kredit') {
                // KONDISI KREDIT / PENGELUARAN / KASBON
                kategoriDiv.style.display = 'block';
                kategoriSelect.setAttribute('required', 'required');
                labelMetode.innerText = 'Sumber Dana (Uang diambil dari mana?)';

                divNota.style.display = 'block'; // Tampilkan form upload nota fisik

                // Munculkan kembali semua opsi metode
                for (let option of metodeSelect.options) {
                    option.style.display = 'block';
                }
            } else {
                // KONDISI DEBET / PEMASUKAN
                kategoriDiv.style.display = 'block';
                kategoriSelect.setAttribute('required', 'required');
                labelMetode.innerText = 'Metode Pembayaran';

                divNota.style.display = 'none'; // Sembunyikan untuk pemasukan biasa

                // Munculkan kembali semua opsi metode
                for (let option of metodeSelect.options) {
                    option.style.display = 'block';
                }
            }
        }

        // Jalankan saat halaman pertama kali dimuat
        document.addEventListener("DOMContentLoaded", function () {
            toggleJenisTransaksi();
        });
    </script>
@endsection