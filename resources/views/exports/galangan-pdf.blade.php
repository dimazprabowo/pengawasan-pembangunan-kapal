<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Data Galangan</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 10px;
        }
        h1 {
            text-align: center;
            font-size: 16px;
            margin-bottom: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 6px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        .text-center {
            text-align: center;
        }
        .badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 9px;
        }
        .badge-success {
            background-color: #d4edda;
            color: #155724;
        }
        .badge-danger {
            background-color: #f8d7da;
            color: #721c24;
        }
        .footer {
            margin-top: 20px;
            font-size: 9px;
            text-align: right;
        }
    </style>
</head>
<body>
    <h1>Data Galangan</h1>
    <p style="text-align: right; font-size: 9px;">Dicetak pada: {{ now()->format('d/m/Y H:i') }}</p>
    
    <table>
        <thead>
            <tr>
                <th width="5%" class="text-center">No</th>
                <th width="12%">Kode</th>
                <th width="20%">Nama Galangan</th>
                <th width="15%">Lokasi</th>
                <th width="15%">Kontak</th>
                <th width="10%" class="text-center">Jenis Kapal</th>
                <th width="10%" class="text-center">Status</th>
                <th width="13%">Dibuat</th>
            </tr>
        </thead>
        <tbody>
            @forelse($galanganList as $index => $galangan)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td>{{ $galangan->kode }}</td>
                    <td>{{ $galangan->nama }}</td>
                    <td>
                        {{ $galangan->kota ?? '-' }}<br>
                        <small style="color: #666;">{{ $galangan->provinsi ?? '' }}</small>
                    </td>
                    <td>
                        {{ $galangan->pic_name ?? '-' }}<br>
                        <small style="color: #666;">{{ $galangan->telepon ?? '-' }}</small>
                    </td>
                    <td class="text-center">{{ $galangan->jenis_kapal_count }}</td>
                    <td class="text-center">
                        <span class="badge {{ $galangan->status->value === 'active' ? 'badge-success' : 'badge-danger' }}">
                            {{ $galangan->status->label() }}
                        </span>
                    </td>
                    <td>{{ $galangan->created_at->format('d/m/Y') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="text-center">Tidak ada data galangan</td>
                </tr>
            @endforelse
        </tbody>
    </table>
    
    <div class="footer">
        <p>Total: {{ $galanganList->count() }} galangan</p>
    </div>
</body>
</html>
