@php
    // Function ditaruh langsung di sini (bukan di Helpers.php) supaya tidak
    // tergantung composer dump-autoload di server. Selalu tersedia begitu
    // blade ini dirender.
    if (!function_exists('formatRupiahPDF')) {
        function formatRupiahPDF($angka, $prefix = 'Rp')
        {
            $nominal = number_format($angka, 0, ',', '.');
            return '<table class="rp-wrap"><tr>
                            <td class="rp-prefix">' . $prefix . '</td>
                            <td class="rp-value">' . $nominal . '</td>
                        </tr></table>';
        }
    }
@endphp
<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Laporan Keuangan Retail</title>
    <style>
        /* Margin kertas disesuaikan agar pas 1 halaman A4 */
        @page {
            size: A4;
            margin: 4mm 8mm 5mm 8mm;
        }

        body {
            font-family: sans-serif;
            font-size: 9.5px;
            /* Ukuran font pas & jelas terbaca */
            color: #333;
            padding: 0;
            margin: 0;
            line-height: 1.15;
        }

        /* Kop Surat */
        .kop-container {
            border-bottom: 2px double #333;
            padding-bottom: 3px;
            margin-bottom: 4px;
            position: relative;
        }

        .kop-logo {
            position: absolute;
            left: 0;
            top: 1px;
            width: 38px;
        }

        .kop-text {
            text-align: center;
            width: 100%;
        }

        .kop-text h2 {
            margin: 0;
            font-size: 13.5px;
            text-transform: uppercase;
            font-weight: bold;
        }

        .kop-text p {
            margin: 1px 0 0 0;
            font-size: 10px;
            font-weight: bold;
            color: #444;
        }

        /* Footer Alamat di Bawah Halaman */
        .footer-alamat {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 7.5px;
            color: #555;
            border-top: 1px solid #ccc;
            padding-top: 2px;
            background: #fff;
        }

        /* Tabel Kompak */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 4px;
        }

        th,
        td {
            border: 1px solid #333;
            padding: 1.5px 4px;
            text-align: left;
        }

        th {
            background: #f1f2f6;
            font-weight: bold;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .bold {
            font-weight: bold;
        }

        .center-total {
            text-align: center !important;
            font-weight: bold;
        }

        .keep-together {
            page-break-inside: avoid;
        }

        /* Tabel nested untuk format Rp rata kiri - angka rata kanan */
        .rp-wrap {
            width: 100%;
            border: none;
            border-collapse: collapse;
        }

        .rp-wrap td {
            border: none;
            padding: 0;
        }

        .rp-prefix {
            text-align: left;
            width: 30px;
        }

        .rp-value {
            text-align: right;
        }

        @media print {
            body {
                padding: 0;
            }
        }
    </style>
</head>

<body onload="window.print()">

    <!-- KOP SURAT -->
    <div class="kop-container">
        <img src="{{ asset('images/fans.png') }}" alt="Logo" class="kop-logo" onerror="this.style.display='none'">
        <div class="kop-text">
            <h2>PT. FANS MEDIA JEMBER</h2>
            <p>Laporan Keuangan Retail</p>
            <div style="font-size: 9.5px; font-weight: normal; margin-top: 1px;">
                Periode {{ \Carbon\Carbon::parse($mulai)->translatedFormat('d F Y') }} -
                {{ \Carbon\Carbon::parse($sampai)->translatedFormat('d F Y') }}
            </div>
        </div>
    </div>

    <!-- TABEL UTAMA KEUANGAN (FORMAT RUNNING TOTAL CLIENT) -->
    <table>
        <thead>
            <tr>
                <th style="width: 25px;" class="text-center">No</th>
                <th>Keterangan</th>
                <th class="text-right" style="width: 95px;">Debet</th>
                <th class="text-right" style="width: 95px;">Kredit</th>
                <th class="text-right" style="width: 105px;">Saldo</th>
            </tr>
        </thead>
        <tbody>
            <!-- I. SALDO AWAL -->
            <tr class="bold">
                <td class="text-center">I</td>
                <td colspan="4">Saldo</td>
            </tr>
            @php
                // Perhitungan pecahan saldo awal (Cash vs Rekening)
                $cashAwal = $saldoAwalCash ?? 0;
                $rekeningAwal = $saldoAwal - $cashAwal;
            @endphp
            <tr>
                <td></td>
                <td>Saldo Awal Cash</td>
                <td class="text-right">{!! formatRupiahPDF($cashAwal) !!}</td>
                <td></td>
                <td></td>
            </tr>
            <tr>
                <td></td>
                <td>Saldo Awal Rekening</td>
                <td class="text-right">{!! formatRupiahPDF($rekeningAwal) !!}</td>
                <td></td>
                <td></td>
            </tr>
            <tr>
                <td></td>
                <td>Bunga Bank</td>
                <td class="text-right">Rp -</td>
                <td></td>
                <td></td>
            </tr>
            <tr class="bold">
                <td></td>
                <td>Total Saldo Awal</td>
                <td></td>
                <td></td>
                <td class="text-right">{!! formatRupiahPDF($saldoAwal) !!}</td>
            </tr>

            <!-- II. PEMASUKAN (DENGAN RUNNING TOTAL DI KOLOM SALDO) -->
            <tr class="bold">
                <td class="text-center">II</td>
                <td colspan="4">Pemasukan</td>
            </tr>
            @php
                $noPemasukan = 1;
                $runPemasukan = 0;
                $daftarAreaNames = $areas->pluck('nama_area')->toArray();
            @endphp

            @foreach($areas as $area)
                @if(($pemasukanPerArea[$area->nama_area] ?? 0) > 0)
                    @php
                        $nom = $pemasukanPerArea[$area->nama_area];
                        $runPemasukan += $nom;
                    @endphp
                    <tr>
                        <td class="text-center">{{ $noPemasukan++ }}</td>
                        <td>Pembayaran Retail {{ $area->nama_area }}</td>
                        <td class="text-right">{!! formatRupiahPDF($nom) !!}</td>
                        <td></td>
                        <td class="text-right">{!! formatRupiahPDF($runPemasukan) !!}</td>
                    </tr>
                @endif
            @endforeach

            @foreach($pemasukanPerArea as $namaKey => $nominalKey)
                @if(!in_array($namaKey, $daftarAreaNames) && $nominalKey > 0)
                    @php $runPemasukan += $nominalKey; @endphp
                    <tr>
                        <td class="text-center">{{ $noPemasukan++ }}</td>
                        <td>{{ $namaKey }}</td>
                        <td class="text-right">{!! formatRupiahPDF($nominalKey) !!}</td>
                        <td></td>
                        <td class="text-right">{!! formatRupiahPDF($runPemasukan) !!}</td>
                    </tr>
                @endif
            @endforeach

            @if($kasbonMasuk > 0)
                @php $runPemasukan += $kasbonMasuk; @endphp
                <tr>
                    <td class="text-center">{{ $noPemasukan++ }}</td>
                    <td>Pembayaran Kasbon Teknisi</td>
                    <td class="text-right">{!! formatRupiahPDF($kasbonMasuk) !!}</td>
                    <td></td>
                    <td class="text-right">{!! formatRupiahPDF($runPemasukan) !!}</td>
                </tr>
            @endif

            <tr class="bold">
                <td></td>
                <td>Total Pemasukan</td>
                <td></td>
                <td></td>
                <td class="text-right">{!! formatRupiahPDF($totalDebet) !!}</td>
            </tr>

            <!-- III. PENGELUARAN (DENGAN RUNNING TOTAL NEGATIF DI KOLOM SALDO) -->
            <tr class="bold">
                <td class="text-center">III</td>
                <td colspan="4">Pengeluaran</td>
            </tr>
            @php
                $noPengeluaran = 1;
                $runPengeluaran = 0;
            @endphp
            @foreach($pengeluaranPerKategori as $namaKat => $nominalKat)
                @if($nominalKat > 0)
                    @php $runPengeluaran += $nominalKat; @endphp
                    <tr>
                        <td class="text-center">{{ $noPengeluaran++ }}</td>
                        <td>{{ $namaKat }}</td>
                        <td></td>
                        <td class="text-right">{!! formatRupiahPDF($nominalKat) !!}</td>
                        <td class="text-right">{!! formatRupiahPDF($runPengeluaran, '-Rp') !!}</td>
                    </tr>
                @endif
            @endforeach
            @if($kasbonKeluar > 0)
                @php $runPengeluaran += $kasbonKeluar; @endphp
                <tr>
                    <td class="text-center">{{ $noPengeluaran++ }}</td>
                    <td>Kasbon Teknisi</td>
                    <td></td>
                    <td class="text-right">{!! formatRupiahPDF($kasbonKeluar) !!}</td>
                    <td class="text-right">{!! formatRupiahPDF($runPengeluaran, '-Rp') !!}</td>
                </tr>
            @endif

            <tr class="bold">
                <td></td>
                <td>Total Pengeluaran</td>
                <td></td>
                <td></td>
                <td class="text-right">
                    {!! formatRupiahPDF($totalPengeluaranOperasional + $kasbonKeluar, '-Rp') !!}
                </td>
            </tr>

            <!-- TOTAL KEUANGAN RETAIL (BARIS KUNING CLIENT) -->
            <tr class="bold" style="background-color: #ffff00;">
                <td colspan="2" class="center-total">TOTAL KEUANGAN RETAIL</td>
                <td class="text-right">{!! formatRupiahPDF($saldoAwal + $totalDebet) !!}</td>
                <td class="text-right">{!! formatRupiahPDF($totalPengeluaranOperasional + $kasbonKeluar) !!}</td>
                <td class="text-right">{!! formatRupiahPDF($saldoAkhir) !!}</td>
            </tr>
        </tbody>
    </table>

    <!-- KELOMPOK BAWAH (POSISI KAS & TTD) DIPASTIKAN UTUH DALAM 1 HALAMAN -->
    <div class="keep-together">
        <table>
            <thead>
                <tr style="background-color: #f1f2f6;">
                    <th colspan="3" class="text-center" style="padding: 4px; font-weight: bold;">
                        <div>
                            Laporan Keuangan Retail
                        </div>

                        <div style="font-size: 9.5px; font-weight: normal; margin-top: 2px;">
                            Posisi Saldo Akhir &amp; Periode
                            {{ \Carbon\Carbon::parse($mulai)->translatedFormat('d F Y') }} -
                            {{ \Carbon\Carbon::parse($sampai)->translatedFormat('d F Y') }}
                        </div>
                    </th>
                </tr>

                <tr>
                    <th style="width: 25px;" class="text-center">No</th>
                    <th>Keterangan</th>
                    <th class="text-right" style="width: 120px;">Saldo (Rp)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="text-center">1</td>
                    <td>Uang Cash di Operasional</td>
                    <td class="text-right">{!! formatRupiahPDF($saldoAwalCash ?? 0) !!}</td>
                </tr>
                <tr>
                    <td class="text-center">2</td>
                    <td>Uang Cash dari Retail yang belum disetor ke Bank</td>
                    <td class="text-right bold" style="color: #27ae60;">
                        {!! formatRupiahPDF($uangCashBelumDisetor ?? 0) !!}
                    </td>
                </tr>
                <tr>
                    <td class="text-center">3</td>
                    <td>Uang Retail di Rekening</td>
                    <td class="text-right">{!! formatRupiahPDF($uangRetailDiRekening ?? 0) !!}</td>
                </tr>
                <tr class="bold" style="background-color: #ffff00;">
                    <td colspan="2" class="center-total">TOTAL KEUANGAN RETAIL</td>
                    <td class="text-right">
                        {!! formatRupiahPDF($saldoAwalCash + $uangCashBelumDisetor + $uangRetailDiRekening) !!}
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- CATATAN -->
        <div style="margin-top: 2px; font-style: italic; font-size: 8px;">Catatan : Bukti Terlampir</div>

        <!-- TANDA TANGAN: DIREKTUR (KIRI) | KOMISARIS (TENGAH - AGAK TURUN KE BAWAH) | ADMIN RETAIL (KANAN) -->
        <table style="width: 100%; border: none; margin-top: 4px;">
            <tr>
                <td style="border: none; width: 33%; text-align: center; vertical-align: top;">
                    Mengetahui,<br>
                    <strong>Direktur</strong>
                    <br><br><br><br>
                    <span style="text-decoration: underline; font-weight: bold;">Fans Ach Farrosil Miqdad</span>
                </td>
                <td style="border: none; width: 34%; text-align: center; vertical-align: top; padding-top: 60px;">
                    Menyetujui,<br>
                    <strong>Komisaris</strong>
                    <br><br><br><br>
                    <span style="text-decoration: underline; font-weight: bold;">Erfan Effendi S.Pd., M.Pd</span>
                </td>
                <td style="border: none; width: 33%; text-align: center; vertical-align: top;">
                    Jember, {{ \Carbon\Carbon::parse($sampai)->translatedFormat('d F Y') }}<br>
                    <strong>Admin Retail</strong>
                    <br><br><br><br>
                    <span style="text-decoration: underline; font-weight: bold;">Hertina Rahmaningtyas</span>
                </td>
            </tr>
        </table>
    </div>

    <!-- FOOTER -->
    <div class="footer-alamat">
        Alamat: Perum Griya Mangli Indah Df 01, Wonosari, Mangli, Kec. Kaliwates, Jember | Telp: 0851-7505-9195
    </div>

</body>

</html>