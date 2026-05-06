<?php

namespace App\Services;

use App\Models\LaporanMingguan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\TemplateProcessor;
use App\Services\WordImageService;

class LaporanMingguanWordService
{
    private const TEMPLATE_PATH = 'templates/laporan-mingguan/template-laporan-mingguan.docx';

    private const INDONESIAN_DAYS = [
        'Sunday'    => 'Minggu',
        'Monday'    => 'Senin',
        'Tuesday'   => 'Selasa',
        'Wednesday' => 'Rabu',
        'Thursday'  => 'Kamis',
        'Friday'    => 'Jumat',
        'Saturday'  => 'Sabtu',
    ];

    public function __construct(
        private WordImageService $imageService
    ) {}

    /**
     * Generate Word document dari template mingguan.
     * Returns storage-relative path.
     */
    public function generate(LaporanMingguan $laporan): string
    {
        $laporan->loadMissing([
            'user',
            'jenisKapal.company',
            'jenisKapal.galangan',
            'laporanHarian' => fn ($q) => $q->orderBy('tanggal_laporan', 'asc'),
            'laporanHarian.aktivitas',
            'laporanHarian.personel',
            'laporanHarian.peralatan',
            'laporanHarian.consumable',
            'laporanHarian.lampiran',
            'lampiran.laporanHarian',
        ]);

        $templateFullPath = $this->resolveTemplate($laporan);
        $processor        = new TemplateProcessor($templateFullPath);

        $this->setSingleValues($processor, $laporan);
        $this->fillHarianTable($processor, $laporan);
        $this->fillAktivitasTable($processor, $laporan);
        $this->fillLampiranTable($processor, $laporan);

        return $this->saveDocument($processor, $laporan);
    }

    // ──────────────────────────────────────────────────────────────────
    // TEMPLATE RESOLUTION
    // ──────────────────────────────────────────────────────────────────
    private function resolveTemplate(LaporanMingguan $laporan): string
    {
        if ($laporan->jenisKapal && $laporan->jenisKapal->hasTemplate('mingguan')) {
            $path = $laporan->jenisKapal->getTemplateFullPath('mingguan');
            if ($path && file_exists($path)) {
                return $path;
            }
        }

        $default = storage_path('app/' . self::TEMPLATE_PATH);
        if (!file_exists($default)) {
            throw new \RuntimeException(
                'Template Word mingguan tidak ditemukan. Upload template pada master Jenis Kapal atau sediakan default di storage/app/' . self::TEMPLATE_PATH
            );
        }

        return $default;
    }

    // ──────────────────────────────────────────────────────────────────
    // SINGLE VALUES
    // ──────────────────────────────────────────────────────────────────
    private function setSingleValues(TemplateProcessor $processor, LaporanMingguan $laporan): void
    {
        $periodeMulai   = $laporan->periode_mulai?->translatedFormat('d F Y') ?? '-';
        $periodeSelesai = $laporan->periode_selesai?->translatedFormat('d F Y') ?? '-';
        $periodeLabel   = ($laporan->periode_mulai && $laporan->periode_selesai)
            ? $periodeMulai . ' – ' . $periodeSelesai
            : '-';

        $values = [
            'judul'            => $laporan->judul ?? '-',
            'no_laporan'       => (string) $laporan->id,
            'perusahaan'       => $laporan->jenisKapal?->company?->name ?? '-',
            'lokasi'           => $laporan->jenisKapal?->galangan?->nama ?? '-',
            'no_kapal'         => $laporan->jenisKapal?->nama ?? '-',
            'tanggal'          => $laporan->tanggal_laporan?->translatedFormat('d F Y') ?? '-',
            'periode_mulai'    => $periodeMulai,
            'periode_selesai'  => $periodeSelesai,
            'periode_label'    => $periodeLabel,
            'dibuat_oleh'      => $laporan->user?->name ?? '-',
            'ringkasan'        => $laporan->ringkasan ?: '-',
            'total_hari'       => (string) $laporan->laporanHarian->count(),
            'total_lampiran'   => (string) $laporan->lampiran->count(),
            'ttd_kota'         => $laporan->jenisKapal?->galangan?->kota ?? '-',
            'ttd_tanggal'      => $laporan->tanggal_laporan?->translatedFormat('d F Y') ?? '-',
            'ttd_nama'         => $laporan->user?->name ?? '-',
        ];

        $processor->setValues($values);
    }

    // ──────────────────────────────────────────────────────────────────
    // TABEL DAFTAR LAPORAN HARIAN
    // ──────────────────────────────────────────────────────────────────
    private function fillHarianTable(TemplateProcessor $processor, LaporanMingguan $laporan): void
    {
        $rows = $laporan->laporanHarian->values();

        if ($rows->isEmpty()) {
            $processor->setValue('harian_no', '1');
            $processor->setValue('harian_tanggal', '-');
            $processor->setValue('harian_hari', '-');
            $processor->setValue('harian_judul', '-');
            $processor->setValue('harian_personel_total', '-');
            $processor->setValue('harian_peralatan_total', '-');
            $processor->setValue('harian_consumable_total', '-');
            $processor->setValue('harian_aktivitas_total', '-');
            $processor->setValue('harian_lampiran_total', '-');
            return;
        }

        try {
            $processor->cloneRow('harian_no', $rows->count());

            foreach ($rows as $index => $item) {
                $i = $index + 1;
                $tanggal = $item->tanggal_laporan;
                $hari    = $tanggal
                    ? (self::INDONESIAN_DAYS[$tanggal->format('l')] ?? $tanggal->format('l'))
                    : '-';

                $processor->setValue('harian_no#' . $i, (string) $i);
                $processor->setValue('harian_tanggal#' . $i, $tanggal?->translatedFormat('d M Y') ?? '-');
                $processor->setValue('harian_hari#' . $i, $hari);
                $processor->setValue('harian_judul#' . $i, $item->judul ?? '-');
                $processor->setValue('harian_personel_total#' . $i, (string) $item->personel->count());
                $processor->setValue('harian_peralatan_total#' . $i, (string) $item->peralatan->count());
                $processor->setValue('harian_consumable_total#' . $i, (string) $item->consumable->count());
                $processor->setValue('harian_aktivitas_total#' . $i, (string) $item->aktivitas->count());
                $processor->setValue('harian_lampiran_total#' . $i, (string) $item->lampiran->filter(fn ($l) => $l->isFileCompleted())->count());
            }
        } catch (\Exception $e) {
            Log::warning('LaporanMingguanWordService: skip harian table', ['error' => $e->getMessage()]);
        }
    }

    // ──────────────────────────────────────────────────────────────────
    // TABEL REKAP AKTIVITAS
    // ──────────────────────────────────────────────────────────────────
    private function fillAktivitasTable(TemplateProcessor $processor, LaporanMingguan $laporan): void
    {
        $rows = collect();
        foreach ($laporan->laporanHarian as $harian) {
            foreach ($harian->aktivitas as $a) {
                $rows->push([
                    'tanggal'   => $harian->tanggal_laporan,
                    'kategori'  => $a->kategori,
                    'aktivitas' => $a->aktivitas,
                    'pic'       => $a->pic,
                ]);
            }
        }

        if ($rows->isEmpty()) {
            $processor->setValue('aktivitas_no', '1');
            $processor->setValue('aktivitas_tanggal', '-');
            $processor->setValue('aktivitas_kategori', '-');
            $processor->setValue('aktivitas_aktivitas', '-');
            $processor->setValue('aktivitas_pic', '-');
            return;
        }

        try {
            $processor->cloneRow('aktivitas_no', $rows->count());
            foreach ($rows->values() as $index => $row) {
                $i = $index + 1;
                $processor->setValue('aktivitas_no#' . $i, (string) $i);
                $processor->setValue('aktivitas_tanggal#' . $i, $row['tanggal']?->translatedFormat('d M Y') ?? '-');
                $processor->setValue('aktivitas_kategori#' . $i, $row['kategori'] ?? '-');
                $processor->setValue('aktivitas_aktivitas#' . $i, $row['aktivitas'] ?? '-');
                $processor->setValue('aktivitas_pic#' . $i, $row['pic'] ?? '-');
            }
        } catch (\Exception $e) {
            Log::warning('LaporanMingguanWordService: skip aktivitas table', ['error' => $e->getMessage()]);
        }
    }

    // ──────────────────────────────────────────────────────────────────
    // TABEL LAMPIRAN
    // ──────────────────────────────────────────────────────────────────
    private function fillLampiranTable(TemplateProcessor $processor, LaporanMingguan $laporan): void
    {
        $rows = $laporan->lampiran->filter(fn ($l) => $l->isFileCompleted())->values();

        if ($rows->isEmpty()) {
            $processor->setValue('lampiran_no', '1');
            $processor->setValue('lampiran_tanggal', '-');
            $processor->setValue('lampiran_gambar', '-');
            $processor->setValue('lampiran_ket', '-');
            return;
        }

        try {
            $processor->cloneRow('lampiran_no', $rows->count());

            foreach ($rows as $index => $item) {
                $i = $index + 1;

                $processor->setValue('lampiran_no#' . $i, (string) $i);
                $processor->setValue('lampiran_tanggal#' . $i, $item->laporanHarian?->tanggal_laporan?->translatedFormat('d M Y') ?? '-');

                if ($this->imageService->isImageFile($item->file_path)) {
                    $imagePath = $this->imageService->resolveLampiranPath($item->file_path);
                    if ($imagePath && file_exists($imagePath)) {
                        try {
                            $finalImage = $this->imageService->prepareImageForWord($imagePath);
                            $processor->setImageValue('lampiran_gambar#' . $i, [
                                'path'   => $finalImage,
                                'width'  => WordImageService::DEFAULT_WIDTH,
                                'height' => WordImageService::DEFAULT_HEIGHT,
                                'ratio'  => true,
                            ]);
                            if ($finalImage !== $imagePath && file_exists($finalImage)) {
                                @unlink($finalImage);
                            }
                        } catch (\Exception $e) {
                            Log::error('LaporanMingguanWordService: failed insert image', [
                                'lampiran_id' => $item->id,
                                'error'       => $e->getMessage(),
                            ]);
                            $processor->setValue('lampiran_gambar#' . $i, $item->file_name ?? '-');
                        }
                    } else {
                        $processor->setValue('lampiran_gambar#' . $i, $item->file_name ?? '-');
                    }
                } else {
                    $processor->setValue('lampiran_gambar#' . $i, $item->file_name ?? '-');
                }

                $processor->setValue('lampiran_ket#' . $i, $item->keterangan ?? '-');
            }
        } catch (\Exception $e) {
            Log::warning('LaporanMingguanWordService: skip lampiran table', ['error' => $e->getMessage()]);
        }
    }

    // ──────────────────────────────────────────────────────────────────
    // SAVE DOCUMENT
    // ──────────────────────────────────────────────────────────────────
    private function saveDocument(TemplateProcessor $processor, LaporanMingguan $laporan): string
    {
        $dir = storage_path('app/laporan/word');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filename = sprintf(
            'laporan-mingguan-%d-%s.docx',
            $laporan->id,
            now()->format('YmdHis')
        );

        $processor->saveAs($dir . '/' . $filename);

        return 'laporan/word/' . $filename;
    }
}
