<?php

namespace Database\Seeders;

use App\Enums\NcFileKategori;
use App\Enums\NcFormJenis;
use App\Enums\NcStatus;
use App\Models\LaporanHarian;
use App\Models\LaporanNc;
use App\Models\LaporanNcForm;
use App\Models\User;
use Database\Seeders\Support\SeedFileFactory;
use Illuminate\Database\Seeder;

class LaporanNcSeeder extends Seeder
{
    /**
     * Contoh NC per laporan harian: nomor berurutan per kapal (NC-001, NC-002, ...),
     * sebagian closed dengan closed_by agar List NC terlihat variatif.
     */
    public function run(): void
    {
        // Skip saat testing: test membuat fixture sendiri, data contoh hanya mengganggu.
        if (app()->environment('testing')) {
            return;
        }

        $users = User::all();
        if ($users->isEmpty()) {
            $this->command->warn('No users found. Please run UserSeeder first.');

            return;
        }

        $pool = [
            ['kendala' => 'Las sambungan pelat lambung tidak rata dan terdapat porositas', 'tindakan' => 'Gouging dan pengelasan ulang sesuai WPS', 'pic' => 'Welder Foreman'],
            ['kendala' => 'Jarak spasi frame tidak sesuai gambar kerja', 'tindakan' => 'Penyesuaian ulang posisi frame sesuai marking', 'pic' => 'Fitter Leader'],
            ['kendala' => 'Coating area tangki terkelupas karena persiapan permukaan kurang baik', 'tindakan' => 'Blasting ulang dan pengecatan ulang sesuai spesifikasi', 'pic' => 'Painting Supervisor'],
            ['kendala' => 'Pipa bilga bocor pada sambungan flange saat uji tekan', 'tindakan' => 'Ganti gasket dan torque ulang sesuai tabel', 'pic' => 'Pipe Foreman'],
            ['kendala' => 'Kabel tray tertabrak pekerjaan piping sehingga jalur bergeser', 'tindakan' => 'Relayout tray dan cek kekuatan tumpuan', 'pic' => 'Electrical Foreman'],
        ];

        $count = 0;
        $i = 0;

        // NC disebar sepanjang kontrak: 1 NC per ~15 hari laporan + 1 NC
        // ekstra sesekali (grup multi-NC) agar rekap NC mingguan/bulanan
        // terisi realistis, bukan hanya menumpuk di akhir proyek.
        LaporanHarian::orderBy('tanggal_laporan')->get()
            ->groupBy('data_kapal_id')
            ->each(function ($perKapal) use ($users, $pool, &$count, &$i) {
                $perKapal->values()->each(function ($laporan, $n) use ($users, $pool, &$count, &$i) {
                    if ($n % 15 !== 0) {
                        return;
                    }
                    $jumlah = $n % 45 === 0 ? 2 : 1;
                    $creator = $users->get($i % $users->count());
                    $key = $laporan->data_kapal_id;
                    $counter = (int) LaporanNc::where('data_kapal_id', $key)->count();
                    $i++;

                    for ($k = 0; $k < $jumlah; $k++) {
                        $counter++;
                        $nomor = 'NC-'.str_pad((string) $counter, 3, '0', STR_PAD_LEFT);
                        $data = $pool[($i + $k) % count($pool)];
                        $closed = ($counter % 4 === 0);

                        $nc = LaporanNc::firstOrCreate(
                            ['laporan_harian_id' => $laporan->id, 'nomor' => $nomor],
                            [
                                'data_kapal_id' => $laporan->data_kapal_id,
                                'kendala' => $data['kendala'],
                                'tindakan' => $data['tindakan'],
                                'pic' => $data['pic'],
                                'status' => $closed ? NcStatus::Closed : NcStatus::Open,
                                'closed_at' => $closed ? $laporan->tanggal_laporan->copy()->addDays(2) : null,
                                'closed_by' => $closed ? $creator->id : null,
                                'created_by' => $creator->id,
                                'updated_by' => $creator->id,
                                // rekapNC memfilter created_at <= periode_selesai —
                                // wajib tanggal laporan, bukan waktu seed.
                                'created_at' => $laporan->tanggal_laporan,
                                'updated_at' => $laporan->tanggal_laporan,
                            ]
                        );

                        // NC closed selalu punya file Close; sebagian NC Open juga
                        // (bukti perbaikan sudah ada, menunggu Form Penutupan)
                        // agar checklist Form Penutupan bisa didemokan.
                        $this->seedFoto($nc, $creator, $closed || $counter % 3 === 0);
                        $count++;
                    }
                });
            });

        $this->seedForms($users);

        $this->command->info("Laporan NC seeded successfully ({$count} records).");
    }

    /**
     * Foto NC nyata (WebP via Intervention): selalu 1 foto OPEN; NC dengan
     * $withClose mendapat foto CLOSE verifikasi perbaikan.
     */
    private function seedFoto(LaporanNc $nc, User $uploader, bool $withClose): void
    {
        $items = [['kategori' => NcFileKategori::Open, 'nama' => 'Foto Temuan '.$nc->nomor]];
        if ($withClose) {
            $items[] = ['kategori' => NcFileKategori::Close, 'nama' => 'Foto Perbaikan '.$nc->nomor];
        }

        foreach ($items as $item) {
            [$path, $size] = SeedFileFactory::storeImage('laporan-nc/foto', $nc->nomor.$item['kategori']->value);

            $nc->foto()->create([
                'kategori' => $item['kategori'],
                'file_path' => $path,
                'file_name' => $item['nama'],
                'file_size' => $size,
                'file_status' => 'completed',
                'file_processed_at' => now(),
                'uploaded_by' => $uploader->id,
            ]);
        }
    }

    /**
     * Form NC: per laporan harian yang punya NC dibuat form TEMUAN (sebagian
     * signed dengan file PDF scan nyata, sebagian draft menunggu TTD).
     */
    private function seedForms($users): void
    {
        $laporans = LaporanHarian::whereHas('ncs')->with(['ncs', 'dataKapal'])->get();

        foreach ($laporans as $laporan) {
            $kapal = $laporan->dataKapal;
            $nomor = sprintf('%s-NCR-%03d', $kapal->kode ?? 'NC', $laporan->id);

            $signed = (bool) ($laporan->id % 2);
            [$path, $size] = $signed
                ? SeedFileFactory::storePdf('laporan-nc/form', 'FORM NON CONFORMANCE REPORT - '.$nomor, [
                    ['heading' => 'Data Temuan', 'lines' => $laporan->ncs->map(fn ($nc) => "{$nc->nomor}: {$nc->kendala}")->all()],
                    ['heading' => 'Keterangan', 'lines' => ['Dokumen ini merupakan scan form NC yang telah ditandatangani.']],
                ])
                : [null, null];

            $form = LaporanNcForm::firstOrCreate(
                ['data_kapal_id' => $kapal->id, 'nomor' => $nomor],
                [
                    'laporan_harian_id' => $laporan->id,
                    'jenis' => NcFormJenis::Temuan,
                    'tanggal' => $laporan->tanggal_laporan,
                    'file_path' => $signed ? $path : null,
                    'file_name' => $signed ? 'scan-'.$nomor.'.pdf' : null,
                    'file_size' => $signed ? $size : null,
                    'file_status' => $signed ? 'completed' : 'pending',
                    'file_processed_at' => $signed ? now() : null,
                    'signed_at' => $signed ? $laporan->tanggal_laporan->copy()->addDay() : null,
                    'signed_by' => $signed ? $users->random()->id : null,
                    'created_by' => $users->random()->id,
                    'updated_by' => $users->random()->id,
                ]
            );

            $form->ncs()->syncWithoutDetaching($laporan->ncs->pluck('id')->all());
        }
    }
}
