<?php

namespace Database\Seeders;

use App\Models\Cuaca;
use App\Models\DataKapal;
use App\Models\Kelembaban;
use App\Models\KurvaSWorkGroup;
use App\Models\LaporanHarian;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class LaporanHarianSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::all();
        $dataKapals = DataKapal::all();
        $cuacas = Cuaca::all();
        $kelembabans = Kelembaban::all();

        if ($users->isEmpty()) {
            $this->command->warn('No users found. Please run UserSeeder first.');

            return;
        }

        if ($dataKapals->isEmpty()) {
            $this->command->warn('No Data Kapal found. Please run DataKapalSeeder first.');

            return;
        }

        // Rentang tanggal: dari tanggal mulai kontrak tiap kapal s/d hari ini —
        // laporan harian tidak boleh melebihi tanggal berjalan.
        $today = Carbon::today();

        $count = 0;
        foreach ($dataKapals as $dataKapal) {
            $workGroups = KurvaSWorkGroup::where('data_kapal_id', $dataKapal->id)->orderBy('sort_order')->get();
            $tanggalMulai = $dataKapal->tanggal_mulai_kontrak?->copy()->startOfDay()
                ?? $today->copy()->subDays(14);

            for ($tanggal = $tanggalMulai->copy(); $tanggal->lte($today); $tanggal->addDay()) {
                $i = (int) $tanggalMulai->diffInDays($tanggal);

                $laporan = LaporanHarian::firstOrCreate(
                    [
                        'data_kapal_id' => $dataKapal->id,
                        'tanggal_laporan' => $tanggal->format('Y-m-d'),
                    ],
                    [
                        'user_id' => $users->get($i % $users->count())->id,
                        'suhu' => 28 + ($i % 5),
                        'cuaca_pagi_id' => $cuacas->isNotEmpty() ? $cuacas->get($i % $cuacas->count())->id : null,
                        'kelembaban_pagi_id' => $kelembabans->isNotEmpty() ? $kelembabans->get($i % $kelembabans->count())->id : null,
                        'cuaca_siang_id' => $cuacas->isNotEmpty() ? $cuacas->get(($i + 1) % $cuacas->count())->id : null,
                        'kelembaban_siang_id' => $kelembabans->isNotEmpty() ? $kelembabans->get(($i + 1) % $kelembabans->count())->id : null,
                        'cuaca_sore_id' => $cuacas->isNotEmpty() ? $cuacas->get(($i + 2) % $cuacas->count())->id : null,
                        'kelembaban_sore_id' => $kelembabans->isNotEmpty() ? $kelembabans->get(($i + 2) % $kelembabans->count())->id : null,
                    ]
                );

                if ($laporan->wasRecentlyCreated) {
                    $this->seedAktivitas($laporan, $workGroups, $i);
                    $this->seedPersonel($laporan, $i);
                    $this->seedPeralatan($laporan, $i);
                    $this->seedConsumable($laporan, $i);
                    $this->seedLampiran($laporan, $i);
                }
                $count++;
            }
        }

        $this->command->info("Laporan Harian seeded successfully ({$count} records).");
    }

    /**
     * Uraian aktivitas contoh: 2 work group bergiliran per hari (kategori lain sengaja
     * kosong agar tidak tergenerate di laporan) + sesekali segmen "Lainnya".
     */
    private function seedAktivitas(LaporanHarian $laporan, Collection $workGroups, int $hari): void
    {
        $contoh = [
            'Pemeriksaan pekerjaan sesuai gambar kerja dan spesifikasi',
            'Monitoring progres pekerjaan bersama pihak galangan',
            'Inspeksi visual hasil pekerjaan dan pencatatan temuan',
        ];

        foreach ($workGroups->isEmpty() ? [] : [$hari, $hari + 1] as $n) {
            $wg = $workGroups[$n % $workGroups->count()];
            foreach (array_slice($contoh, 0, 1 + ($n % 2)) as $k => $uraian) {
                $laporan->aktivitas()->create([
                    'kurva_s_work_group_id' => $wg->id,
                    'kategori' => $wg->nama,
                    'aktivitas' => $uraian,
                    'pic' => ['QC Galangan', 'Surveyor BKI', 'Owner Surveyor'][($n + $k) % 3],
                ]);
            }
        }

        if ($hari % 3 === 0) {
            $laporan->aktivitas()->create([
                'kurva_s_work_group_id' => null,
                'kategori' => null,
                'aktivitas' => 'Rapat koordinasi harian dan pembahasan K3',
                'pic' => 'Lead Inspector',
            ]);
        }
    }

    private function seedPersonel(LaporanHarian $laporan, int $hari): void
    {
        foreach ([
            ['Welders', 'Hadir', null],
            ['Fitters', 'Hadir', null],
            ['Supervisor', 'Hadir', $hari % 7 === 6 ? 'Hari libur bersama' : null],
        ] as [$jabatan, $status, $ket]) {
            $laporan->personel()->create(['jabatan' => $jabatan, 'status' => $status, 'keterangan' => $ket]);
        }
    }

    private function seedPeralatan(LaporanHarian $laporan, int $hari): void
    {
        foreach ([
            ['Welding Machine', 4 + ($hari % 3), 'Kondisi baik'],
            ['Grinding Machine', 2 + ($hari % 4), 'Kondisi baik'],
            ['Crane', 1, null],
        ] as [$jenis, $jumlah, $ket]) {
            $laporan->peralatan()->create(['jenis' => $jenis, 'jumlah' => $jumlah, 'keterangan' => $ket]);
        }
    }

    private function seedConsumable(LaporanHarian $laporan, int $hari): void
    {
        foreach ([
            ['Kawat Las (batang)', 8 + ($hari % 5)],
            ['Mata Gerinda (pcs)', 2 + ($hari % 3)],
        ] as [$jenis, $jumlah]) {
            $laporan->consumable()->create(['jenis' => $jenis, 'jumlah' => $jumlah, 'keterangan' => null]);
        }
    }

    /**
     * Lampiran foto nyata (WebP) digenerate via Intervention agar preview,
     * crop, dan export Word bisa disimulasikan end-to-end.
     */
    private function seedLampiran(LaporanHarian $laporan, int $hari): void
    {
        // ~1 dari 2 hari punya foto dokumentasi, tautkan ke aktivitas pertama.
        if ($hari % 2 !== 0) {
            return;
        }

        $aktivitas = $laporan->aktivitas()->first();
        $fileName = 'dokumentasi-'.$laporan->id.'-'.uniqid().'.webp';
        $path = 'laporan-lampiran/harian/'.$fileName;

        $colors = ['#3b82f6', '#10b981', '#f59e0b', '#6366f1'];
        $encoded = (string) \Intervention\Image\ImageManager::gd()
            ->create(800, 600)
            ->fill($colors[$hari % count($colors)])
            ->toWebp(85);
        \Illuminate\Support\Facades\Storage::disk('local')->put($path, $encoded);

        $laporan->lampiran()->create([
            'laporan_aktivitas_id' => $aktivitas?->id,
            'file_path' => $path,
            'file_name' => 'Dokumentasi Hari '.$hari,
            'file_size' => strlen($encoded),
            'file_status' => 'completed',
            'file_processed_at' => now(),
        ]);
    }
}
