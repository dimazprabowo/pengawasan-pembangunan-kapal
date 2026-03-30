<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Laporan {{ $tipeLabel }}</title>
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
    <h1>Laporan {{ $tipeLabel }}</h1>
    <div class="subtitle">Dicetak pada {{ now()->translatedFormat('d F Y H:i') }}</div>

    <table>
        <thead>
            <tr>
                <th width="5%" class="text-center">No</th>
                <th width="20%">Judul</th>
                <th width="10%" class="text-center">Tanggal</th>
                <th width="15%">Pembuat</th>
                <th width="30%">Isi Laporan</th>
                <th width="10%" class="text-center">File</th>
                <th width="10%" class="text-center">Dibuat</th>
            </tr>
        </thead>
        <tbody>
            @forelse($laporanList as $index => $laporan)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td>{{ $laporan->judul }}</td>
                    <td class="text-center">{{ $laporan->tanggal_laporan->format('d/m/Y') }}</td>
                    <td>{{ $laporan->user->name ?? '-' }}</td>
                    <td>{{ Str::limit($laporan->isi ?? '-', 100) }}</td>
                    <td class="text-center">{{ $laporan->file_name ?? '-' }}</td>
                    <td class="text-center">{{ $laporan->created_at->format('d/m/Y H:i') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="text-center">Tidak ada data</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        Total: {{ $laporanList->count() }} laporan
    </div>
</body>
</html>
