<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>List NC</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 10px; margin: 20px; }
        h1 { text-align: center; font-size: 16px; margin-bottom: 5px; }
        .subtitle { text-align: center; font-size: 11px; margin-bottom: 20px; color: #666; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { background-color: #2563EB; color: white; padding: 8px; text-align: left; font-weight: bold; font-size: 9px; }
        td { padding: 6px 8px; border-bottom: 1px solid #ddd; font-size: 9px; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        .text-center { text-align: center; }
        .badge-open { color: #b91c1c; font-weight: bold; }
        .badge-closed { color: #047857; font-weight: bold; }
        .footer { margin-top: 20px; text-align: right; font-size: 8px; color: #666; }
    </style>
</head>
<body>
    <h1>List Non-Conformity (NC)</h1>
    <div class="subtitle">{{ $dataKapalNama }} — Dicetak pada {{ now()->translatedFormat('d F Y H:i') }}</div>

    <table>
        <thead>
            <tr>
                <th class="text-center" style="width: 4%">No</th>
                <th style="width: 8%">Nomor</th>
                <th style="width: 9%">Tgl Laporan</th>
                <th style="width: 25%">Kendala</th>
                <th style="width: 22%">Tindakan</th>
                <th style="width: 10%">PIC</th>
                <th style="width: 7%">Status</th>
                <th style="width: 15%">Closed</th>
            </tr>
        </thead>
        <tbody>
            @forelse($ncList as $i => $nc)
                <tr>
                    <td class="text-center">{{ $i + 1 }}</td>
                    <td>{{ $nc->nomor ?? '-' }}</td>
                    <td>{{ $nc->laporanHarian?->tanggal_laporan?->format('d/m/Y') ?? '-' }}</td>
                    <td>{{ $nc->kendala }}</td>
                    <td>{{ $nc->tindakan ?? '-' }}</td>
                    <td>{{ $nc->pic ?? '-' }}</td>
                    <td class="{{ $nc->isClosed() ? 'badge-closed' : 'badge-open' }}">{{ $nc->status->label() }}</td>
                    <td>{{ $nc->closed_at ? $nc->closed_at->format('d/m/Y').($nc->closedBy ? ' - '.$nc->closedBy->name : '') : '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center">Tidak ada data NC.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">Total: {{ $ncList->count() }} NC</div>
</body>
</html>
