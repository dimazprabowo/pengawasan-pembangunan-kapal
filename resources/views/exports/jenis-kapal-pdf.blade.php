<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Data Jenis Kapal</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; }
        h1 { text-align: center; font-size: 18px; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; font-weight: bold; }
        .text-center { text-align: center; }
        .badge { padding: 2px 8px; border-radius: 4px; font-size: 10px; }
        .badge-success { background-color: #d4edda; color: #155724; }
        .badge-secondary { background-color: #e2e3e5; color: #383d41; }
    </style>
</head>
<body>
    <h1>Data Jenis Kapal</h1>
    <p>Dicetak pada: {{ now()->format('d/m/Y H:i') }}</p>
    
    <table>
        <thead>
            <tr>
                <th width="5%">No</th>
                <th width="20%">Jenis Kapal</th>
                <th width="20%">Perusahaan</th>
                <th width="30%">Deskripsi</th>
                <th width="5%" class="text-center">Laporan Harian</th>
                <th width="5%" class="text-center">Laporan Mingguan</th>
                <th width="5%" class="text-center">Laporan Bulanan</th>
                <th width="10%" class="text-center">Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($jenisKapalList as $index => $jenisKapal)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td>{{ $jenisKapal->nama }}</td>
                    <td>
                        {{ $jenisKapal->company->name }}<br>
                        <small>{{ $jenisKapal->company->code }}</small>
                    </td>
                    <td>{{ $jenisKapal->deskripsi ?? '-' }}</td>
                    <td class="text-center">{{ $jenisKapal->getLaporanCount('harian') }}</td>
                    <td class="text-center">{{ $jenisKapal->getLaporanCount('mingguan') }}</td>
                    <td class="text-center">{{ $jenisKapal->getLaporanCount('bulanan') }}</td>
                    <td class="text-center">
                        <span class="badge {{ $jenisKapal->status->value === 'active' ? 'badge-success' : 'badge-secondary' }}">
                            {{ $jenisKapal->status->label() }}
                        </span>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="text-center">Tidak ada data</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
