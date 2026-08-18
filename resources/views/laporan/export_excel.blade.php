<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Laporan Keuangan</title>
</head>

<body>
    <table>
        <tr>
            <td colspan="7" style="font-size: 16px; font-weight: bold;">LAPORAN KEUANGAN UTAMA & REKAPITULASI</td>
        </tr>
        <tr>
            <td colspan="7">Periode: {{ $mulai }} s/d {{ $sampai }}</td>
        </tr>
        <tr>
            <td colspan="7"></td>
        </tr>

        <!-- 1. PEMASUKAN CASH -->
        <tr>
            <td colspan="7" style="font-weight: bold; color: #2ecc71;">▶ Pemasukan via Cash</td>
        </tr>
        <thead>
            <tr style="background-color: #2ecc71; color: #ffffff; font-weight: bold;">
                <th>No</th>
                <th>Tanggal</th>
                <th>Kategori</th>
                <th>Lokasi / Area</th>
                <th>Nama Teknisi / Pegawai</th>
                <th>Keterangan</th>
                <th style="text-align: center;">Nominal (Debet)</th>
            </tr>
        </thead>
        <tbody>
            @php $sumCash = 0; @endphp
            @foreach($keuanganCash as $i => $row)
                @php $sumCash += $row->debet; @endphp
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $row->tanggal }}</td>
                    <td>{{ optional($row->kategori)->nama_kategori }}</td>
                    <td>{{ optional($row->area)->nama_area ?? 'Umum / Pusat' }}</td>
                    <td>{{ optional($row->user)->name ?? '-' }}</td>
                    <td>{{ $row->keterangan }}</td>
                    <td style="text-align: center;">{{ $row->debet }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr style="font-weight: bold;">
                <td colspan="6" align="right">TOTAL PEMASUKAN CASH:</td>
                <td style="text-align: center;">{{ $sumCash }}</td>
            </tr>
        </tfoot>
        <tr>
            <td colspan="7"></td>
        </tr>

        <!-- 2. PEMASUKAN TRANSFER -->
        <tr>
            <td colspan="7" style="font-weight: bold; color: #3498db;">▶ Pemasukan via Transfer Bank</td>
        </tr>
        <thead>
            <tr style="background-color: #3498db; color: #ffffff; font-weight: bold;">
                <th>No</th>
                <th>Tanggal</th>
                <th>Kategori</th>
                <th>Lokasi / Area</th>
                <th>Nama Teknisi / Pegawai</th>
                <th>Keterangan</th>
                <th style="text-align: center;">Nominal (Debet)</th>
            </tr>
        </thead>
        <tbody>
            @php $sumTransfer = 0; @endphp
            @foreach($keuanganTransfer as $i => $row)
                @php $sumTransfer += $row->debet; @endphp
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $row->tanggal }}</td>
                    <td>{{ optional($row->kategori)->nama_kategori }}</td>
                    <td>{{ optional($row->area)->nama_area ?? 'Umum / Pusat' }}</td>
                    <td>{{ optional($row->user)->name ?? '-' }}</td>
                    <td>{{ $row->keterangan }}</td>
                    <td style="text-align: center;">{{ $row->debet }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr style="font-weight: bold;">
                <td colspan="6" align="right">TOTAL PEMASUKAN TRANSFER:</td>
                <td style="text-align: center;">{{ $sumTransfer }}</td>
            </tr>
        </tfoot>
        <tr>
            <td colspan="7"></td>
        </tr>

        <!-- 3. PENGELUARAN OPERASIONAL -->
        <tr>
            <td colspan="8" style="font-weight: bold; color: #e74c3c;">▶ Pengeluaran Operasional</td>
        </tr>
        <thead>
            <tr style="background-color: #e74c3c; color: #ffffff; font-weight: bold;">
                <th>No</th>
                <th>Tanggal</th>
                <th>Kategori</th>
                <th>Lokasi / Area</th>
                <th>Nama Teknisi / Pegawai</th>
                <th>Metode</th>
                <th>Keterangan</th>
                <th style="text-align: center;">Nominal (Kredit)</th>
            </tr>
        </thead>
        <tbody>
            @php $sumKeluar = 0; @endphp
            @foreach($keuanganPengeluaran as $i => $row)
                @php $sumKeluar += $row->kredit; @endphp
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $row->tanggal }}</td>
                    <td>{{ optional($row->kategori)->nama_kategori }}</td>
                    <td>{{ optional($row->area)->nama_area ?? 'Umum / Pusat' }}</td>
                    <td>{{ optional($row->user)->name ?? '-' }}</td>
                    <td>{{ optional($row->metodePembayaran)->nama_metode }}</td>
                    <td>{{ $row->keterangan }}</td>
                    <td style="text-align: center;">{{ $row->kredit }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr style="font-weight: bold;">
                <td colspan="7" align="right">TOTAL PENGELUARAN:</td>
                <td style="text-align: center;">{{ $sumKeluar }}</td>
            </tr>
        </tfoot>
        <tr>
            <td colspan="8"></td>
        </tr>

        <!-- 4. KASBON TEKNISI -->
        <tr>
            <td colspan="7" style="font-weight: bold; color: #f39c12;">▶ Kasbon Teknisi</td>
        </tr>
        <thead>
            <tr style="background-color: #f39c12; color: #ffffff; font-weight: bold;">
                <th>No</th>
                <th>Tanggal</th>
                <th>Lokasi / Area</th>
                <th>Nama Teknisi</th>
                <th>Metode</th>
                <th>Keterangan</th>
                <th style="text-align: center;">Nominal (Kredit)</th>
            </tr>
        </thead>
        <tbody>
            @php $sumKasbon = 0; @endphp
            @foreach($keuanganKasbon as $i => $row)
                @php $sumKasbon += $row->kredit; @endphp
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $row->tanggal }}</td>
                    <td>{{ optional($row->area)->nama_area ?? 'Umum / Pusat' }}</td>
                    <td>{{ optional($row->user)->name }}</td>
                    <td>{{ optional($row->metodePembayaran)->nama_metode }}</td>
                    <td>{{ $row->keterangan }}</td>
                    <td style="text-align: center;">{{ $row->kredit }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr style="font-weight: bold;">
                <td colspan="6" align="right">TOTAL KASBON:</td>
                <td style="text-align: center;">{{ $sumKasbon }}</td>
            </tr>
        </tfoot>
    </table>
</body>

</html>