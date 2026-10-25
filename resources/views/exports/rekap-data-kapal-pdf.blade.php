<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Rekap Laporan per Data Kapal</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 10px;
            margin: 20px;
        }
        h1 {
            text-align: center;
            font-size: 16px;
            margin-bottom: 5px;
        }
        .subtitle {
            text-align: center;
            font-size: 11px;
            margin-bottom: 20px;
            color: #666;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th {
            background-color: #2563EB;
            color: white;
            padding: 8px;
            text-align: left;
            font-weight: bold;
            font-size: 9px;
        }
        td {
            padding: 6px 8px;
            border-bottom: 1px solid #ddd;
            font-size: 9px;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .text-center {
            text-align: center;
        }
        .footer {
            margin-top: 20px;
            text-align: right;
            font-size: 8px;
            color: #666;
        }
    </style>
</head>
<body>
    <h1>Rekap Laporan per Data Kapal</h1>
    <div class="subtitle">Dicetak pada {{ now()->translatedFormat('d F Y H:i') }}</div>

    <table>
        <thead>
            <tr>
                <th width="5%" class="text-center">No</th>
                <th width="25%">Data Kapal</th>
                <th width="20%">Perusahaan</th>
                <th width="15%">Galangan</th>
                <th width="8%" class="text-center">Harian</th>
                <th width="8%" class="text-center">Mingguan</th>
                <th width="9%" class="text-center">Progress</th>
                <th width="10%" class="text-center">Laporan Terakhir</th>
            </tr>
        </thead>
        <tbody>
            @forelse($dataKapalList as $index => $dataKapal)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td>{{ $dataKapal->nama }}</td>
                    <td>{{ $dataKapal->company->name ?? '-' }}</td>
                    <td>{{ $dataKapal->galangan->nama ?? '-' }}</td>
                    <td class="text-center">{{ $dataKapal->laporan_harian_count }}</td>
                    <td class="text-center">{{ $dataKapal->laporan_mingguan_count }}</td>
                    <td class="text-center">{{ $dataKapal->progress_aktual !== null ? $dataKapal->progress_aktual . '%' : '-' }}</td>
                    <td class="text-center">
                        {{ $dataKapal->laporan_harian_max_tanggal_laporan
                            ? \Carbon\Carbon::parse($dataKapal->laporan_harian_max_tanggal_laporan)->format('d/m/Y')
                            : 'Belum ada' }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="text-center">Tidak ada data</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        Total: {{ $dataKapalList->count() }} data kapal
    </div>
</body>
</html>
