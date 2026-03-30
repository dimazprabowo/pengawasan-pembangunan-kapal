<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Data Cuaca</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f3f4f6; font-weight: bold; }
        h1 { text-align: center; margin-bottom: 5px; }
        .meta { text-align: center; color: #666; margin-bottom: 20px; }
    </style>
</head>
<body>
    <h1>Data Cuaca</h1>
    <div class="meta">Dicetak pada: {{ now()->format('d/m/Y H:i') }}</div>
    
    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>Nama Cuaca</th>
                <th>Keterangan</th>
                <th>Status</th>
                <th>Dibuat Pada</th>
            </tr>
        </thead>
        <tbody>
            @foreach($cuacaList as $index => $cuaca)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $cuaca->nama }}</td>
                    <td>{{ $cuaca->keterangan ?? '-' }}</td>
                    <td>{{ $cuaca->status->label() }}</td>
                    <td>{{ $cuaca->created_at->format('d/m/Y H:i') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
