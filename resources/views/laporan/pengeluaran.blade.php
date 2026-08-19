@extends('layouts.app')
@section('title', 'Laporan Pengeluaran Operasional')

@section('content')
    <!-- BUNGKUS UTAMA (FLEX-SHRINK: 0) AGAR TABEL TIDAK DIGENCET -->
    <div style="flex-shrink: 0; width: 100%; padding-bottom: 40px;">

        <div class="header" style="margin-bottom: 25px;">
            <h2>Laporan Pengeluaran Operasional</h2>
        </div>

        <!-- ========================================== -->
        <!-- WIDGET CARDS SUMMARY PENGELUARAN -->
        <!-- ========================================== -->
        <div style="display: flex; gap: 20px; margin-bottom: 30px; flex-wrap: wrap;">

            <!-- Card 1: Pengeluaran Cash (Oranye) -->
            <div
                style="flex: 1; min-width: 250px; background: #fff; border-radius: 8px; border-left: 5px solid #e67e22; padding: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.05);">
                <p
                    style="margin: 0; font-size: 13px; color: #7f8c8d; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px;">
                    Pengeluaran Cash
                </p>
                <h3 style="margin: 10px 0 5px 0; font-size: 26px; color: #e67e22;">
                    Rp {{ number_format($totalCash, 0, ',', '.') }}
                </h3>
                <small style="color: #95a5a6; font-size: 11px;">Biaya Operasional dari Laci/Tunai</small>
            </div>

            <!-- Card 2: Pengeluaran Transfer (Merah Muda / Coral) -->
            <div
                style="flex: 1; min-width: 250px; background: #fff; border-radius: 8px; border-left: 5px solid #e74c3c; padding: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.05);">
                <p
                    style="margin: 0; font-size: 13px; color: #7f8c8d; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px;">
                    Pengeluaran Transfer
                </p>
                <h3 style="margin: 10px 0 5px 0; font-size: 26px; color: #e74c3c;">
                    Rp {{ number_format($totalTransfer, 0, ',', '.') }}
                </h3>
                <small style="color: #95a5a6; font-size: 11px;">Biaya Operasional via Bank</small>
            </div>

            <!-- Card 3: Total Pengeluaran (Merah Gelap) -->
            <div
                style="flex: 1; min-width: 250px; background: #fff; border-radius: 8px; border-left: 5px solid #c0392b; padding: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.05);">
                <p
                    style="margin: 0; font-size: 13px; color: #7f8c8d; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px;">
                    Total Seluruh Pengeluaran
                </p>
                <h3 style="margin: 10px 0 5px 0; font-size: 26px; color: #c0392b;">
                    Rp {{ number_format($totalCash + $totalTransfer, 0, ',', '.') }}
                </h3>
                <small style="color: #95a5a6; font-size: 11px;">Gabungan Cash + Transfer Bank</small>
            </div>

        </div>
        <!-- ========================================== -->

        <div class="header" style="flex-direction: column; align-items: flex-start; gap: 15px; margin-bottom: 25px;">
            <h2>Filter Pengeluaran</h2>

            <form method="GET" action=""
                style="display: flex; gap: 10px; flex-wrap: wrap; background: #f8f9fa; padding: 15px; border-radius: 6px; width: 100%;">
                <div>
                    <label style="display: block; font-size: 11px; font-weight: bold; margin-bottom: 3px;">Dari
                        Tanggal:</label>
                    <input type="date" name="tanggal_mulai" value="{{ $mulai }}"
                        style="padding: 6px; border: 1px solid #ccc; border-radius: 4px;">
                </div>
                <div>
                    <label style="display: block; font-size: 11px; font-weight: bold; margin-bottom: 3px;">Sampai
                        Tanggal:</label>
                    <input type="date" name="tanggal_selesai" value="{{ $sampai }}"
                        style="padding: 6px; border: 1px solid #ccc; border-radius: 4px;">
                </div>

                <!-- [BARU] Grup Tombol Filter & Export -->
                <div style="display: flex; align-items: flex-end; gap: 10px;">
                    <button type="submit"
                        style="background: #27ae60; color: white; border: none; padding: 8px 15px; border-radius: 4px; font-weight: bold; cursor: pointer;">
                        Terapkan Filter
                    </button>
                    <a href="{{ route('laporan.export.nota', ['tanggal_mulai' => $mulai, 'tanggal_selesai' => $sampai]) }}"
                        target="_blank"
                        style="background: #e67e22; color: white; border: none; padding: 8px 15px; border-radius: 4px; font-weight: bold; cursor: pointer; text-decoration: none;">
                        📄 Cetak Lampiran Nota (PDF)
                    </a>
                </div>
            </form>
        </div>

        <!-- Tabel Pengeluaran Cash -->
        <h3 style="margin-top: 10px; margin-bottom: 10px; color: #2ecc71; font-size: 16px;">▶ Pengeluaran via Cash</h3>
        <div
            style="background: #fff; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.02); padding: 15px; overflow-x: auto; margin-bottom: 30px;">
            <table style="width: 100%; min-width: 1050px; border-collapse: collapse; font-size: 13px;">
                <thead>
                    <tr style="background-color: #f1f2f6; border-top: 2px solid #333; border-bottom: 2px solid #333;">
                        <th style="width: 50px; text-align: center; padding: 10px;">No</th>
                        <th style="width: 100px; padding: 10px;">Tanggal</th>
                        <th style="padding: 10px;">Kategori</th>
                        <th style="padding: 10px;">Lokasi / Area</th>
                        <th style="padding: 10px;">Keterangan</th>
                        <!-- [BARU] Kolom Bukti Nota -->
                        <th style="width: 80px; text-align: center; padding: 10px;">Bukti Nota</th>
                        <th class="text-right" style="width: 130px; padding: 10px;">Nominal (Kredit)</th>
                        <th style="text-align: center; width: 140px; padding: 10px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($pengeluaranCash as $index => $row)
                        <tr style="border-bottom: 1px solid #f1f1f1;">
                            <td style="text-align: center; padding: 10px;">{{ $index + 1 }}</td>
                            <td style="padding: 10px;">{{ \Carbon\Carbon::parse($row->tanggal)->format('d/m/Y') }}</td>
                            <td style="padding: 10px;">{{ optional($row->kategori)->nama_kategori }}</td>
                            <td style="padding: 10px;">{{ optional($row->area)->nama_area ?? 'Pusat' }}</td>
                            <td style="padding: 10px;">{{ $row->keterangan }}</td>

                            <!-- [BARU] Tampilkan Tombol Bukti Jika Ada -->
                            <td style="text-align: center; padding: 10px;">
                                @if($row->nota)
                                    <a href="{{ asset('uploads/nota/' . $row->nota) }}" target="_blank"
                                        style="background: #3498db; color: white; padding: 4px 8px; text-decoration: none; border-radius: 3px; font-size: 11px; display: inline-block;">Lihat</a>
                                @else
                                    <span style="color: #95a5a6; font-size: 11px; font-style: italic;">-</span>
                                @endif
                            </td>

                            <td class="text-right" style="padding: 10px; color: #e74c3c; font-weight: 600;">Rp
                                {{ number_format($row->kredit, 0, ',', '.') }}</td>
                            <td style="text-align: center; padding: 10px;">
                                <a href="{{ url('/laporan/transaksi/edit/' . $row->id) }}"
                                    style="background: #f39c12; color: white; padding: 4px 8px; text-decoration: none; border-radius: 3px; font-size: 11px;">Edit</a>
                                <a href="{{ url('/laporan/transaksi/hapus/' . $row->id) }}"
                                    onclick="return confirm('Yakin ingin menghapus transaksi ini?')"
                                    style="background: #e74c3c; color: white; padding: 4px 8px; text-decoration: none; border-radius: 3px; font-size: 11px; margin-left: 3px;">Hapus</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 20px; color: #7f8c8d;">Belum ada data
                                pengeluaran cash.</td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr
                        style="background-color: #f1f2f6; font-weight: bold; border-top: 2px solid #333; border-bottom: 2px solid #333;">
                        <!-- [BARU] Colspan diubah dari 5 jadi 6 karena ada kolom nota -->
                        <td colspan="6" class="text-right" style="padding: 10px;">TOTAL CASH:</td>
                        <td class="text-right" style="padding: 10px; color: #e74c3c;">Rp
                            {{ number_format($totalCash, 0, ',', '.') }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- Tabel Pengeluaran Transfer Bank -->
        <h3 style="margin-top: 10px; margin-bottom: 10px; color: #3498db; font-size: 16px;">▶ Pengeluaran via Transfer Bank
        </h3>
        <div
            style="background: #fff; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.02); padding: 15px; overflow-x: auto; margin-bottom: 30px;">
            <table style="width: 100%; min-width: 1050px; border-collapse: collapse; font-size: 13px;">
                <thead>
                    <tr style="background-color: #f1f2f6; border-top: 2px solid #333; border-bottom: 2px solid #333;">
                        <th style="width: 50px; text-align: center; padding: 10px;">No</th>
                        <th style="width: 100px; padding: 10px;">Tanggal</th>
                        <th style="padding: 10px;">Kategori</th>
                        <th style="padding: 10px;">Lokasi / Area</th>
                        <th style="padding: 10px;">Keterangan</th>
                        <!-- [BARU] Kolom Bukti Nota -->
                        <th style="width: 80px; text-align: center; padding: 10px;">Bukti Nota</th>
                        <th class="text-right" style="width: 130px; padding: 10px;">Nominal (Kredit)</th>
                        <th style="text-align: center; width: 140px; padding: 10px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($pengeluaranTransfer as $index => $row)
                        <tr style="border-bottom: 1px solid #f1f1f1;">
                            <td style="text-align: center; padding: 10px;">{{ $index + 1 }}</td>
                            <td style="padding: 10px;">{{ \Carbon\Carbon::parse($row->tanggal)->format('d/m/Y') }}</td>
                            <td style="padding: 10px;">{{ optional($row->kategori)->nama_kategori }}</td>
                            <td style="padding: 10px;">{{ optional($row->area)->nama_area ?? 'Pusat' }}</td>
                            <td style="padding: 10px;">{{ $row->keterangan }}</td>

                            <!-- [BARU] Tampilkan Tombol Bukti Jika Ada -->
                            <td style="text-align: center; padding: 10px;">
                                @if($row->nota)
                                    <a href="{{ asset('uploads/nota/' . $row->nota) }}" target="_blank"
                                        style="background: #3498db; color: white; padding: 4px 8px; text-decoration: none; border-radius: 3px; font-size: 11px; display: inline-block;">Lihat</a>
                                @else
                                    <span style="color: #95a5a6; font-size: 11px; font-style: italic;">-</span>
                                @endif
                            </td>

                            <td class="text-right" style="padding: 10px; color: #e74c3c; font-weight: 600;">Rp
                                {{ number_format($row->kredit, 0, ',', '.') }}</td>
                            <td style="text-align: center; padding: 10px;">
                                <a href="{{ url('/laporan/transaksi/edit/' . $row->id) }}"
                                    style="background: #f39c12; color: white; padding: 4px 8px; text-decoration: none; border-radius: 3px; font-size: 11px;">Edit</a>
                                <a href="{{ url('/laporan/transaksi/hapus/' . $row->id) }}"
                                    onclick="return confirm('Yakin ingin menghapus transaksi ini?')"
                                    style="background: #e74c3c; color: white; padding: 4px 8px; text-decoration: none; border-radius: 3px; font-size: 11px; margin-left: 3px;">Hapus</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 20px; color: #7f8c8d;">Belum ada data
                                pengeluaran transfer.</td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr
                        style="background-color: #f1f2f6; font-weight: bold; border-top: 2px solid #333; border-bottom: 2px solid #333;">
                        <!-- [BARU] Colspan diubah dari 5 jadi 6 karena ada kolom nota -->
                        <td colspan="6" class="text-right" style="padding: 10px;">TOTAL TRANSFER:</td>
                        <td class="text-right" style="padding: 10px; color: #e74c3c;">Rp
                            {{ number_format($totalTransfer, 0, ',', '.') }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>

    </div>
@endsection