<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Lampiran Bukti Nota</title>
    <style>
        @page {
            size: A4;
            margin: 8mm;
        }

        body {
            font-family: sans-serif;
            font-size: 11px;
            color: #333;
        }

        .header {
            text-align: center;
            border-bottom: 2px solid #333;
            padding-bottom: 5px;
            margin-bottom: 15px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        td {
            border: 1px solid #ccc;
            width: 50%;
            padding: 10px;
            text-align: center;
            vertical-align: top;
        }

        .nota-title {
            background: #f1f2f6;
            font-weight: bold;
            padding: 4px;
            margin-bottom: 5px;
        }

        .img-container {
            height: 180px;
            margin-bottom: 8px;
        }

        .img-container img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        .desc {
            font-style: italic;
            font-size: 10px;
            line-height: 1.3;
        }

        .nominal {
            color: #e74c3c;
            font-weight: bold;
            font-size: 11px;
        }
    </style>
</head>

<body onload="window.print()">

    <div class="header">
        <h2>LAMPIRAN BUKTI FISIK PENGELUARAN</h2>
        <p>Periode: {{ \Carbon\Carbon::parse($mulai)->format('d F Y') }} -
            {{ \Carbon\Carbon::parse($sampai)->format('d F Y') }}</p>
    </div>

    @if($transaksi->isEmpty())
        <p style="text-align: center; margin-top: 50px; font-style: italic;">Tidak ada bukti nota yang diunggah pada periode
            ini.</p>
    @else
        <table>
            <tr>
                @php $counter = 0; @endphp
                @foreach($transaksi as $trx)
                    <td>
                        <div class="nota-title">
                            {{ \Carbon\Carbon::parse($trx->tanggal)->format('d/m/Y') }} -
                            {{ optional($trx->kategori)->nama_kategori }}
                        </div>

                        <div class="img-container">
                            <!-- DIRUBAH DISINI: Pakai asset() agar browser bisa me-load gambar -->
                            <img src="{{ asset('uploads/nota/' . $trx->nota) }}">
                        </div>

                        <div class="desc">
                            <b>Keterangan:</b> {{ $trx->keterangan }} <br>
                            <span class="nominal">Rp {{ number_format($trx->kredit, 0, ',', '.') }}</span>
                        </div>
                    </td>

                    @php $counter++; @endphp
                    @if($counter % 2 == 0)
                        </tr>
                        <tr>
                    @endif
                @endforeach
            </tr>
        </table>
    @endif

</body>

</html>