<?php

namespace App\Services;

use App\Models\KurvaSWorkGroup;
use App\Models\LaporanBulanan;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\TemplateProcessor;

class LaporanBulananWordService
{
    private const TEMPLATE_PATH = 'templates/laporan-bulanan/template-laporan-bulanan.docx';

    public function __construct(
        private LaporanBulananService $bulananService,
        private LaporanNcService $ncService,
        private KurvaSService $kurvaSService,
        private KontrakPeriodeService $periodeService,
    ) {}

    /**
     * Generate Word document dari template bulanan.
     * Returns storage-relative path.
     */
    public function generate(LaporanBulanan $laporan): string
    {
        Settings::setOutputEscapingEnabled(true);

        $laporan->loadMissing([
            'user',
            'dataKapal.company',
            'dataKapal.galangan',
            'laporanExternal',
        ]);

        $templateFullPath = $this->resolveTemplate($laporan);
        $processor = new TemplateProcessor($templateFullPath);

        $this->setSingleValues($processor, $laporan);
        $this->fillMingguanTable($processor, $laporan);
        $this->fillNcSection($processor, $laporan);
        $this->fillExternalTable($processor, $laporan);

        return $this->saveDocument($processor, $laporan);
    }

    // ──────────────────────────────────────────────────────────────────
    // TEMPLATE RESOLUTION
    // ──────────────────────────────────────────────────────────────────
    private function resolveTemplate(LaporanBulanan $laporan): string
    {
        if ($laporan->dataKapal && $laporan->dataKapal->hasTemplate('bulanan')) {
            $path = $laporan->dataKapal->getTemplateFullPath('bulanan');
            if ($path && file_exists($path)) {
                return $path;
            }
        }

        $default = storage_path('app/'.self::TEMPLATE_PATH);
        if (! file_exists($default)) {
            throw new \RuntimeException(
                'Template Word bulanan tidak ditemukan. Upload template pada master Data Kapal atau sediakan default di storage/app/'.self::TEMPLATE_PATH
            );
        }

        return $default;
    }

    // ──────────────────────────────────────────────────────────────────
    // SINGLE VALUES
    // ──────────────────────────────────────────────────────────────────
    private function setSingleValues(TemplateProcessor $processor, LaporanBulanan $laporan): void
    {
        $periodeMulai = $laporan->periode_mulai?->translatedFormat('d F Y') ?? '-';
        $periodeSelesai = $laporan->periode_selesai?->translatedFormat('d F Y') ?? '-';
        $periodeLabel = ($laporan->periode_mulai && $laporan->periode_selesai)
            ? $periodeMulai.' – '.$periodeSelesai
            : '-';

        [$rencana, $aktual] = $this->progressTotals($laporan);
        $selisih = ($rencana !== null && $aktual !== null)
            ? number_format($aktual - $rencana, 2).'%'
            : '-';

        $processor->setValues([
            'judul' => $laporan->judul,
            'perusahaan' => $laporan->dataKapal?->company?->name ?? '-',
            'lokasi' => $laporan->dataKapal?->galangan?->nama ?? '-',
            'no_kapal' => $laporan->dataKapal?->nama ?? '-',
            'bulan_ke' => $laporan->bulan_ke ? (string) $laporan->bulan_ke : '-',
            'periode_mulai' => $periodeMulai,
            'periode_selesai' => $periodeSelesai,
            'periode_label' => $periodeLabel,
            'tanggal' => $laporan->tanggal_laporan?->translatedFormat('d F Y') ?? '-',
            'dibuat_oleh' => $laporan->user?->name ?? '-',
            'ringkasan' => $laporan->ringkasan ?: '-',
            'total_rencana' => $rencana !== null ? number_format($rencana, 2).'%' : '-',
            'total_aktual' => $aktual !== null ? number_format($aktual, 2).'%' : '-',
            'selisih' => $selisih,
            'ttd_kota' => $laporan->dataKapal?->galangan?->kota ?? '-',
            'ttd_tanggal' => $laporan->tanggal_laporan?->translatedFormat('d F Y') ?? '-',
            'ttd_nama' => $laporan->user?->name ?? '-',
        ]);
    }

    /**
     * Total rencana & aktual Kurva-S sampai akhir bulan ini (null bila belum ada data).
     *
     * @return array{0: ?float, 1: ?float}
     */
    private function progressTotals(LaporanBulanan $laporan): array
    {
        if (! $laporan->dataKapal) {
            return [null, null];
        }

        $history = $this->kurvaSService->getMonthlyProgressHistory($laporan->dataKapal);
        $workGroups = KurvaSWorkGroup::where('data_kapal_id', $laporan->data_kapal_id)
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($wg) => ['work_group_id' => $wg->id, 'nama' => $wg->nama, 'bobot' => $wg->bobot])
            ->all();

        if (empty($history) || empty($workGroups)) {
            return [null, null];
        }

        $totals = $this->kurvaSService->calculateTotalsFromHistory($history, $workGroups);

        return [$totals['total_rencana'] ?? null, $totals['total_aktual'] ?? null];
    }

    // ──────────────────────────────────────────────────────────────────
    // TABEL REKAP LAPORAN MINGGUAN
    // ──────────────────────────────────────────────────────────────────
    private function fillMingguanTable(TemplateProcessor $processor, LaporanBulanan $laporan): void
    {
        $rows = $this->bulananService->getMingguanBulan($laporan);

        if ($rows->isEmpty()) {
            $processor->setValue('minggu_no', '1');
            $processor->setValue('minggu_ke', '-');
            $processor->setValue('minggu_periode', '-');
            $processor->setValue('minggu_progress', '-');

            return;
        }

        try {
            $processor->cloneRow('minggu_no', $rows->count());

            foreach ($rows->values() as $index => $m) {
                $i = $index + 1;
                $periode = ($m->periode_mulai && $m->periode_selesai)
                    ? $m->periode_mulai->translatedFormat('d M Y').' – '.$m->periode_selesai->translatedFormat('d M Y')
                    : ($m->tanggal_laporan?->translatedFormat('d M Y') ?? '-');

                $processor->setValue('minggu_no#'.$i, (string) $i);
                $processor->setValue('minggu_ke#'.$i, 'Minggu ke-'.$m->minggu_ke);
                $processor->setValue('minggu_periode#'.$i, $periode);
                $processor->setValue('minggu_progress#'.$i, $m->total_progress !== null
                    ? '+'.number_format((float) $m->total_progress, 2).'%'
                    : '-');
            }
        } catch (\Exception $e) {
            Log::warning('LaporanBulananWordService: skip mingguan table', ['error' => $e->getMessage()]);
        }
    }

    // ──────────────────────────────────────────────────────────────────
    // TABEL DOKUMEN EKSTERNAL
    // ──────────────────────────────────────────────────────────────────
    private function fillExternalTable(TemplateProcessor $processor, LaporanBulanan $laporan): void
    {
        $rows = $laporan->laporanExternal->filter(fn ($e) => $e->hasFile())->values();

        if ($rows->isEmpty()) {
            $processor->setValue('ext_no', '1');
            $processor->setValue('ext_judul', '-');
            $processor->setValue('ext_file', '-');

            return;
        }

        try {
            $processor->cloneRow('ext_no', $rows->count());

            foreach ($rows as $index => $e) {
                $i = $index + 1;
                $processor->setValue('ext_no#'.$i, (string) $i);
                $processor->setValue('ext_judul#'.$i, $e->judul ?: '-');
                $processor->setValue('ext_file#'.$i, $e->file_name ?? '-');
            }
        } catch (\Exception $e) {
            Log::warning('LaporanBulananWordService: skip external table', ['error' => $e->getMessage()]);
        }
    }

    // ──────────────────────────────────────────────────────────────────
    // REKAP NC PERIODE BULANAN
    // ──────────────────────────────────────────────────────────────────
    private function fillNcSection(TemplateProcessor $processor, LaporanBulanan $laporan): void
    {
        try {
            $ncs = ($laporan->periode_mulai && $laporan->periode_selesai && $laporan->data_kapal_id)
                ? $this->ncService->rekapMingguan($laporan->data_kapal_id, $laporan->periode_mulai, $laporan->periode_selesai)
                : collect();

            if ($ncs->isEmpty()) {
                $processor->cloneBlock('blok_nc', 1, true, true);
                $processor->setValue('nc_kosong#1', 'Tidak ada NC pada periode ini.');
                $processor->setValue('nc_no#1', '-');
                foreach (['nc_nomor', 'nc_tanggal', 'nc_kendala', 'nc_tindakan', 'nc_pic', 'nc_status', 'nc_closed'] as $m) {
                    $processor->setValue("{$m}#1", '-');
                }

                return;
            }

            $processor->cloneBlock('blok_nc', 1, true, true);
            $processor->setValue('nc_kosong#1', '');
            $processor->cloneRow('nc_no#1', $ncs->count());

            foreach ($ncs as $index => $nc) {
                $i = $index + 1;
                // nc_no diisi nomor NC (NC-001); nc_nomor tetap diisi untuk
                // kompatibilitas template custom lama (kolomnya sudah dihapus
                // dari template bawaan — setValue ke macro tak ada = no-op).
                $processor->setValue("nc_no#1#{$i}", $nc->nomor ?? (string) $i);
                $processor->setValue("nc_nomor#1#{$i}", $nc->nomor ?? '-');
                $processor->setValue("nc_tanggal#1#{$i}", $nc->laporanHarian?->tanggal_laporan?->translatedFormat('d M Y') ?? '-');
                $processor->setValue("nc_kendala#1#{$i}", $nc->kendala ?? '-');
                $processor->setValue("nc_tindakan#1#{$i}", $nc->tindakan ?? '-');
                $processor->setValue("nc_pic#1#{$i}", $nc->pic ?? '-');
                $processor->setValue("nc_status#1#{$i}", $nc->status->label());
                $processor->setValue("nc_closed#1#{$i}", $nc->closed_at
                    ? $nc->closed_at->translatedFormat('d M Y').($nc->closedBy ? ' · '.$nc->closedBy->name : '')
                    : '-');
            }
        } catch (\Exception $e) {
            Log::warning('LaporanBulananWordService: skip NC section', ['error' => $e->getMessage()]);
        }
    }

    // ──────────────────────────────────────────────────────────────────
    // SAVE DOCUMENT
    // ──────────────────────────────────────────────────────────────────
    private function saveDocument(TemplateProcessor $processor, LaporanBulanan $laporan): string
    {
        $dir = storage_path('app/laporan/word');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filename = sprintf(
            'laporan-bulanan-%d-%s.docx',
            $laporan->id,
            now()->format('YmdHis')
        );

        $processor->saveAs($dir.'/'.$filename);

        return 'laporan/word/'.$filename;
    }
}
