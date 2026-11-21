<?php

namespace App\Services;

use App\Enums\NcStatus;
use App\Jobs\GenerateLaporanMingguanJob;
use App\Models\DataKapal;
use App\Models\LaporanBulanan;
use App\Models\LaporanMingguan;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\SimpleType\JcTable;
use PhpOffice\PhpWord\SimpleType\TblWidth;
use PhpOffice\PhpWord\TemplateProcessor;

/**
 * Generate dokumen Word Laporan Bulanan (struktur ASDP):
 * Cover → Pengesahan → TOC → Kata Pengantar → Executive Summary →
 * BAB 1–4. Tabel dibangun via setComplexBlock; laporan mingguan
 * disisipkan utuh sebagai gambar halaman (docx → pdf → webp)
 * sehingga rantai lampiran harian→mingguan terbawa tanpa altChunk.
 */
class LaporanBulananWordService
{
    private const TEMPLATE_PATH = 'templates/laporan-bulanan/template-laporan-bulanan.docx';

    private const NAVY = '1F3864';

    private const LIGHT = 'D9E2F3';

    private const BORDER = '8EAADB';

    private const CONTENT_WIDTH = 9026;

    public function __construct(
        private LaporanBulananService $bulananService,
        private LaporanNcService $ncService,
        private KurvaSService $kurvaSService,
        private KontrakPeriodeService $periodeService,
        private DataProyekService $proyekService,
    ) {}

    public function generate(LaporanBulanan $laporan): string
    {
        Settings::setOutputEscapingEnabled(true);

        $laporan->loadMissing([
            'user',
            'dataKapal.owner',
            'dataKapal.galangan',
            'laporanExternal',
        ]);

        $processor = new TemplateProcessor($this->resolveTemplate($laporan));

        $this->setSingleValues($processor, $laporan);
        $this->fillExecProgress($processor, $laporan);
        $this->fillInfoUmum($processor, $laporan);
        $this->fillSpesifikasi($processor, $laporan);
        $this->fillDrawing($processor, $laporan);
        $this->fillVendor($processor, $laporan);
        $this->fillMaterial($processor, $laporan);
        $this->fillRealisasi($processor, $laporan);
        $this->fillProgressMingguan($processor, $laporan);
        $this->fillPengujian($processor, $laporan);
        $this->fillRapat($processor, $laporan);
        $this->fillNc($processor, $laporan);
        $this->fillKesimpulan($processor, $laporan);
        $this->fillExternal($processor, $laporan);
        $this->fillLampiranMingguan($processor, $laporan);

        $files = app(FilePathService::class);
        [$relativePath, $localPath] = $this->saveDocument($processor, $laporan);
        try {
            app(WordAttachmentService::class)->embedExternals($localPath, $laporan->laporanExternal, 'DOKUMEN PENDUKUNG');
            $this->embedMingguanDocs($localPath, $this->resolveMingguanDocs($laporan));
            $files->commitLocalFile($localPath, $relativePath);
        } catch (\Throwable $e) {
            @unlink($localPath);
            throw $e;
        }

        return $relativePath;
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

        $files = app(FilePathService::class);
        if (! $files->exists(self::TEMPLATE_PATH)) {
            throw new \RuntimeException(
                'Template Word bulanan tidak ditemukan. Upload template pada master Data Kapal atau sediakan default di penyimpanan: '.self::TEMPLATE_PATH
            );
        }

        return $files->localPath(self::TEMPLATE_PATH);
    }

    // ──────────────────────────────────────────────────────────────────
    // SINGLE VALUES
    // ──────────────────────────────────────────────────────────────────
    private function setSingleValues(TemplateProcessor $processor, LaporanBulanan $laporan): void
    {
        $dk = $laporan->dataKapal;
        $ref = $laporan->periode_selesai ?? $laporan->tanggal_laporan;

        $processor->setValues([
            'bulan_label' => $ref ? strtoupper($ref->translatedFormat('F Y')) : '-',
            'cutoff' => $this->tanggal($laporan->periode_selesai ?? $laporan->tanggal_laporan),
            'nama_pekerjaan' => $dk?->nama_pekerjaan ?: ($dk?->nama ?? '-'),
            'nama_kapal' => $dk?->nama ?? '-',
            'owner' => $dk?->owner?->name ?? '-',
            'owner_alamat' => $dk?->owner?->address ?? '',
            'galangan' => $dk?->galangan?->nama ?? '-',
            'nomor_kontrak_pengawas' => $dk?->nomor_kontrak_pengawas ?: '-',
            'periode_label' => ($laporan->periode_mulai && $laporan->periode_selesai)
                ? $this->tanggal($laporan->periode_mulai).' s/d '.$this->tanggal($laporan->periode_selesai)
                : '-',
            'exec_summary' => $laporan->ringkasan ?: '-',
            'ttd_kota' => $dk?->galangan?->kota ?? '-',
            'ttd_tanggal' => $this->tanggal($laporan->tanggal_laporan),
        ]);

        $ttd1 = $dk?->getPenandatangan(1) ?? [];
        $ttd2 = $dk?->getPenandatangan(2) ?? [];
        $processor->setValues([
            'ttd1_label' => ($ttd1['label'] ?? '') ?: 'Dipersiapkan',
            'ttd1_nama' => ($ttd1['nama'] ?? '') ?: ($laporan->user?->name ?? '-'),
            'ttd1_jabatan' => ($ttd1['jabatan'] ?? '') ?: '',
            'ttd1_instansi' => ($ttd1['instansi'] ?? '') ?: '',
            'ttd2_label' => ($ttd2['label'] ?? '') ?: 'Diketahui',
            'ttd2_nama' => ($ttd2['nama'] ?? '') ?: '-',
            'ttd2_jabatan' => ($ttd2['jabatan'] ?? '') ?: '',
            'ttd2_instansi' => ($ttd2['instansi'] ?? '') ?: '',
        ]);
    }

    // ──────────────────────────────────────────────────────────────────
    // EXEC SUMMARY — tabel progress 3 kolom + deviasi
    // ──────────────────────────────────────────────────────────────────
    private function fillExecProgress(TemplateProcessor $processor, LaporanBulanan $laporan): void
    {
        $dk = $laporan->dataKapal;
        if (! $dk || ! $laporan->bulan_ke) {
            $processor->setValue('tabel_exec_progress', 'Kurva-S belum dikonfigurasi.');

            return;
        }

        $planTotals = $this->kurvaSService->getMonthlyPlanTotals($dk);
        $history = collect($this->kurvaSService->getMonthlyProgressHistory($dk));

        $curr = (int) $laporan->bulan_ke;
        $prevPlan = collect($planTotals)->filter(fn ($v, $b) => $b < $curr)->sum();
        $currPlan = (float) ($planTotals[$curr] ?? 0);
        $cumPlan = $prevPlan + $currPlan;

        $prevAct = $history->where('bulan_ke', '<', $curr)->sum('week_actual');
        $currAct = (float) $history->where('bulan_ke', $curr)->sum('week_actual');
        $cumAct = $prevAct + $currAct;

        $deviasi = round($cumAct - $cumPlan, 2);
        $ket = $deviasi >= 0 ? 'Progress Maju' : 'Progress Mundur';

        $t = $this->newTable();
        // Header 2 baris: grup periode (gridSpan) + sub-kolom
        $t->addRow(null, ['tblHeader' => true]);
        foreach (['AKUMULATIF S/D BULAN SEBELUMNYA', 'BULAN INI', 'AKUMULATIF S/D BULAN INI'] as $grp) {
            $t->addCell(3066, ['bgColor' => self::NAVY, 'valign' => 'center', 'gridSpan' => 2])
                ->addText($grp, $this->headFont(), $this->center());
        }
        $t->addCell(1200, ['bgColor' => self::NAVY, 'valign' => 'center', 'vMerge' => 'restart'])
            ->addText('DEVIASI', $this->headFont(), $this->center());
        $t->addCell(1428, ['bgColor' => self::NAVY, 'valign' => 'center', 'vMerge' => 'restart'])
            ->addText('Ket.', $this->headFont(), $this->center());

        $t->addRow(null, ['tblHeader' => true]);
        foreach (['Rencana', 'Realisasi', 'Rencana', 'Realisasi', 'Rencana', 'Realisasi'] as $sub) {
            $t->addCell(1533, ['bgColor' => self::NAVY, 'valign' => 'center'])->addText($sub, $this->headFont(), $this->center());
        }
        $t->addCell(1200, ['vMerge' => 'continue']);
        $t->addCell(1428, ['vMerge' => 'continue']);

        $t->addRow();
        foreach ([$prevPlan, $prevAct, $currPlan, $currAct, $cumPlan, $cumAct] as $v) {
            $t->addCell(1533, $this->bodyCell())->addText($this->pct($v), $this->smallFont(), $this->center());
        }
        $t->addCell(1200, $this->bodyCell())->addText($this->pct($deviasi, true), $this->smallFont(), $this->center());
        $t->addCell(1428, $this->bodyCell())->addText($ket, $this->smallFont(), $this->center());

        WordStyleService::setComplexBlock($processor, 'tabel_exec_progress', $t);
    }

    // ──────────────────────────────────────────────────────────────────
    // INFORMASI UMUM (label : nilai)
    // ──────────────────────────────────────────────────────────────────
    private function fillInfoUmum(TemplateProcessor $processor, LaporanBulanan $laporan): void
    {
        WordStyleService::setComplexBlock($processor, 'tabel_info_umum', WordStyleService::labelValueTable(
            DataKapal::infoUmumRows($laporan->dataKapal, $laporan->periode_selesai ?? $laporan->tanggal_laporan)
        ));
    }

    // ──────────────────────────────────────────────────────────────────
    // sub-bab 2.1 — SPESIFIKASI KAPAL
    // ──────────────────────────────────────────────────────────────────
    private function fillSpesifikasi(TemplateProcessor $processor, LaporanBulanan $laporan): void
    {
        $specs = collect($laporan->dataKapal?->spesifikasi ?? []);

        if ($specs->isEmpty()) {
            $processor->setValue('tabel_spesifikasi', 'Spesifikasi kapal belum diisi pada master Data Kapal.');

            return;
        }

        $t = $this->newTable();
        $this->headerRow($t, [800, 3600, 4626], ['No', 'Item', 'Keterangan']);
        foreach ($specs->values() as $i => $row) {
            $t->addRow();
            $t->addCell(800, $this->bodyCell())->addText((string) ($i + 1), $this->smallFont(), $this->center());
            $t->addCell(3600, $this->bodyCell())->addText($row['item'] ?? '-', $this->smallFont());
            $t->addCell(4626, $this->bodyCell())->addText($row['keterangan'] ?? '-', $this->smallFont());
        }

        WordStyleService::setComplexBlock($processor, 'tabel_spesifikasi', $t);
    }

    // ──────────────────────────────────────────────────────────────────
    // sub-bab 2.2 — STATUS DESIGN & APPROVAL DRAWING (riwayat kumulatif)
    // ──────────────────────────────────────────────────────────────────
    private function fillDrawing(TemplateProcessor $processor, LaporanBulanan $laporan): void
    {
        $dk = $laporan->dataKapal;
        $rows = $dk ? app(DrawingRegisterService::class)->historyUpTo($dk, $laporan->periode_selesai ?? $laporan->tanggal_laporan) : collect();
        $processor->setValue('nomor_kontrak_gambar', $dk?->nomor_kontrak_gambar ?? '-');
        if ($rows->isEmpty()) {
            $processor->setValue('tabel_drawing', 'Belum ada riwayat gambar sampai periode ini.');

            return;
        }
        $table = $this->newTable();
        $widths = [700, 3626, 700, 2000, 2026];
        $this->headerRow($table, $widths, ['No', 'Nama Gambar', 'Rev', 'Jenis Gambar', 'Status']);
        foreach ($rows->values() as $index => $row) {
            $table->addRow();
            $values = [(string) ($index + 1), $row->drawing->nama, (string) $row->revisi, $row->drawing->type->nama, $row->status->label()];
            foreach ($values as $column => $value) {
                $table->addCell($widths[$column], $this->bodyCell())->addText($value, $this->smallFont());
            }
        }
        WordStyleService::setComplexBlock($processor, 'tabel_drawing', $table);
    }

    // ──────────────────────────────────────────────────────────────────
    // sub-bab 2.5 — REALISASI PROGRESS PEKERJAAN
    // Tarikan ringkasan laporan mingguan pada bulan ini (Minggu ke-N | realisasi).
    // ──────────────────────────────────────────────────────────────────
    private function fillRealisasi(TemplateProcessor $processor, LaporanBulanan $laporan): void
    {
        $rows = $this->bulananService->getMingguanBulan($laporan);

        if ($rows->isEmpty()) {
            $processor->setValue('tabel_realisasi', 'Belum ada realisasi progress pada bulan ini.');

            return;
        }

        $t = $this->newTable();
        $this->headerRow($t, [1426, 7600], ['Minggu', 'Realisasi Pekerjaan']);
        foreach ($rows as $m) {
            $t->addRow();
            $t->addCell(1426, $this->bodyCell())->addText('Minggu '.$m->minggu_ke, $this->smallFont());
            $cell = $t->addCell(7600, $this->bodyCell());
            $lines = preg_split('/\r\n|\r|\n/', trim((string) ($m->ringkasan ?? ''))) ?: [];
            $lines = array_values(array_filter($lines, fn ($l) => trim($l) !== ''));
            if ($lines === []) {
                $cell->addText('-', $this->smallFont());
            } else {
                foreach ($lines as $i => $line) {
                    if ($i > 0) {
                        $cell->addTextBreak();
                    }
                    $cell->addText(trim($line), $this->smallFont());
                }
            }
        }

        WordStyleService::setComplexBlock($processor, 'tabel_realisasi', $t);
    }

    // ──────────────────────────────────────────────────────────────────
    // sub-bab 2.3 — VENDOR / SUB-CONTRACTOR
    // ──────────────────────────────────────────────────────────────────
    private function fillVendor(TemplateProcessor $processor, LaporanBulanan $laporan): void
    {
        $groups = $laporan->dataKapal
            ? $this->proyekService->getVendorsUpToBulanGrouped($laporan->dataKapal, $laporan->bulan_ke)
            : collect();

        if ($groups->isEmpty()) {
            $processor->setValue('tabel_vendor', 'Belum ada daftar sub-contractor / vendor.');

            return;
        }

        $t = $this->newTable();
        $this->headerRow($t, [800, 8226], ['No', 'Nama Vendor / Sub-Contractor']);

        $no = 0;
        foreach ($groups as $items) {
            // Baris judul lingkup pekerjaan (span semua kolom)
            $t->addRow();
            $t->addCell(9026, ['bgColor' => self::LIGHT, 'valign' => 'center', 'gridSpan' => 2])
                ->addText(strtoupper($items->first()->lingkup?->nama ?? 'LAINNYA'), ['bold' => true, 'size' => 8, 'color' => self::NAVY]);

            foreach ($items as $v) {
                $no++;
                $t->addRow();
                $t->addCell(800, $this->bodyCell())->addText((string) $no, $this->smallFont(), $this->center());
                $t->addCell(8226, $this->bodyCell())->addText($v->nama, $this->smallFont());
            }
        }

        WordStyleService::setComplexBlock($processor, 'tabel_vendor', $t);
    }

    // ──────────────────────────────────────────────────────────────────
    // sub-bab 2.4 — MATERIAL & KOMPONEN (dikelompokkan per kategori)
    // ──────────────────────────────────────────────────────────────────
    private function fillMaterial(TemplateProcessor $processor, LaporanBulanan $laporan): void
    {
        $groups = $laporan->dataKapal
            ? $this->proyekService->getMaterialsGrouped($laporan->dataKapal, $laporan->periode_selesai ?? $laporan->tanggal_laporan)
            : collect();

        if ($groups->isEmpty()) {
            $processor->setValue('tabel_material', 'Belum ada pengadaan material / komponen.');

            return;
        }

        $t = $this->newTable();
        $this->headerRow($t, [700, 2700, 2826, 1400, 1400], ['No', 'Item', 'Spesifikasi', 'Jumlah', 'Status']);

        $no = 0;
        foreach ($groups as $items) {
            // Baris judul kategori (span semua kolom)
            $t->addRow();
            $t->addCell(9026, ['bgColor' => self::LIGHT, 'valign' => 'center', 'gridSpan' => 5])
                ->addText(strtoupper($items->first()->category->label()), ['bold' => true, 'size' => 8, 'color' => self::NAVY]);

            foreach ($items as $m) {
                $no++;
                $jumlah = $m->jumlah !== null
                    ? rtrim(rtrim(number_format((float) $m->jumlah, 2, ',', '.'), '0'), ',').' '.($m->satuan ?? '')
                    : '-';
                $t->addRow();
                $t->addCell(700, $this->bodyCell())->addText((string) $no, $this->smallFont(), $this->center());
                $t->addCell(2700, $this->bodyCell())->addText($m->nama_item, $this->smallFont());
                $t->addCell(2826, $this->bodyCell())->addText($m->spesifikasi ?? '-', $this->smallFont());
                $t->addCell(1400, $this->bodyCell())->addText(trim($jumlah), $this->smallFont(), $this->center());
                $t->addCell(1400, $this->bodyCell())->addText($m->status->label(), $this->smallFont(), $this->center());
            }
        }

        WordStyleService::setComplexBlock($processor, 'tabel_material', $t);
    }

    // ──────────────────────────────────────────────────────────────────
    // sub-bab 2.6 — PROGRESS MINGGUAN per cutoff (minggu | fisik | rencana | realisasi | deviasi akum.)
    // ──────────────────────────────────────────────────────────────────
    private function fillProgressMingguan(TemplateProcessor $processor, LaporanBulanan $laporan): void
    {
        $dk = $laporan->dataKapal;
        $bulan = ($dk && $laporan->bulan_ke)
            ? $this->periodeService->getBulanPeriode($dk, (int) $laporan->bulan_ke)
            : null;

        if (! $bulan) {
            $processor->setValue('tabel_progress_mingguan', 'Periode bulan tidak tersedia.');

            return;
        }

        $history = collect($this->kurvaSService->getProgressHistory($dk))
            ->filter(fn ($e) => $e['minggu_ke'] >= $bulan['minggu_dari'] && $e['minggu_ke'] <= $bulan['minggu_sampai'])
            ->values();

        if ($history->isEmpty()) {
            $processor->setValue('tabel_progress_mingguan', 'Belum ada progress mingguan pada bulan ini.');

            return;
        }

        $t = $this->newTable();
        $this->headerRow($t, [1426, 2100, 1800, 1900, 1800],
            ['Minggu', 'Progres Fisik Mingguan', 'Rencana (Akum.)', 'Realisasi (Akum.)', 'Deviasi (Akum.)']);

        foreach ($history as $e) {
            $t->addRow();
            $t->addCell(1426, $this->bodyCell())->addText('Minggu '.$e['minggu_ke'], $this->smallFont());
            $t->addCell(2100, $this->bodyCell())->addText($this->pct($e['week_actual'] ?? 0), $this->smallFont(), $this->center());
            $t->addCell(1800, $this->bodyCell())->addText($this->pct($e['cumulative_plan'] ?? 0), $this->smallFont(), $this->center());
            $t->addCell(1900, $this->bodyCell())->addText($this->pct($e['cumulative_actual'] ?? 0), $this->smallFont(), $this->center());
            $t->addCell(1800, $this->bodyCell())->addText($this->pct($e['cumulative_deviation'] ?? 0, true), $this->smallFont(), $this->center());
        }

        WordStyleService::setComplexBlock($processor, 'tabel_progress_mingguan', $t);
    }

    // ──────────────────────────────────────────────────────────────────
    // sub-bab 2.7 — PENGUJIAN
    // ──────────────────────────────────────────────────────────────────
    private function fillPengujian(TemplateProcessor $processor, LaporanBulanan $laporan): void
    {
        $rows = $laporan->dataKapal
            ? $this->proyekService->getPengujiansUpTo($laporan->dataKapal, $laporan->periode_selesai)
            : collect();

        if ($rows->isEmpty()) {
            $processor->setValue('tabel_pengujian', 'Belum ada pengujian.');

            return;
        }

        $t = $this->newTable();
        $this->headerRow($t, [700, 3326, 1600, 1800, 1600], ['No', 'Nama Pengujian', 'Tanggal', 'Status', 'Hasil']);
        foreach ($rows->values() as $i => $u) {
            $t->addRow();
            $t->addCell(700, $this->bodyCell())->addText((string) ($i + 1), $this->smallFont(), $this->center());
            $t->addCell(3326, $this->bodyCell())->addText($u->nama_pengujian, $this->smallFont());
            $t->addCell(1600, $this->bodyCell())->addText($u->tanggal?->translatedFormat('d M Y') ?? '-', $this->smallFont(), $this->center());
            $t->addCell(1800, $this->bodyCell())->addText($u->status->label(), $this->smallFont(), $this->center());
            $t->addCell(1600, $this->bodyCell())->addText($u->hasil ?? '-', $this->smallFont(), $this->center());
        }

        WordStyleService::setComplexBlock($processor, 'tabel_pengujian', $t);
    }

    // ──────────────────────────────────────────────────────────────────
    // sub-bab 2.8 — RAPAT dalam periode bulan
    // ──────────────────────────────────────────────────────────────────
    private function fillRapat(TemplateProcessor $processor, LaporanBulanan $laporan): void
    {
        $rows = ($laporan->dataKapal && $laporan->periode_mulai && $laporan->periode_selesai)
            ? $this->proyekService->getRapatsInPeriode($laporan->dataKapal, $laporan->periode_mulai, $laporan->periode_selesai)
            : collect();

        if ($rows->isEmpty()) {
            $processor->setValue('tabel_rapat', 'Tidak ada rapat pada periode bulan ini.');

            return;
        }

        $t = $this->newTable();
        $this->headerRow($t, [1600, 1800, 2826, 2800], ['Tanggal', 'Jenis', 'Lokasi', 'Pokok Pembahasan']);
        foreach ($rows as $r) {
            $t->addRow();
            $t->addCell(1600, $this->bodyCell())->addText($r->tanggal->translatedFormat('d M Y'), $this->smallFont(), $this->center());
            $t->addCell(1800, $this->bodyCell())->addText($r->jenis->label(), $this->smallFont(), $this->center());
            $t->addCell(2826, $this->bodyCell())->addText($r->lokasi ?? '-', $this->smallFont());
            $t->addCell(2800, $this->bodyCell())->addText($r->topik ?? '-', $this->smallFont());
        }

        WordStyleService::setComplexBlock($processor, 'tabel_rapat', $t);
    }

    // ──────────────────────────────────────────────────────────────────
    // sub-bab 2.9 — CATATAN & REKOMENDASI (tarikan otomatis rekap NC periode:
    // open terbawa + closed pada periode ini — sama dengan preview form).
    // ──────────────────────────────────────────────────────────────────
    private function fillNc(TemplateProcessor $processor, LaporanBulanan $laporan): void
    {
        $ncs = ($laporan->periode_mulai && $laporan->periode_selesai && $laporan->data_kapal_id)
            ? $this->ncService->rekapMingguan($laporan->data_kapal_id, $laporan->periode_mulai, $laporan->periode_selesai)
            : collect();

        if ($ncs->isEmpty()) {
            $processor->setValue('tabel_nc', 'Tidak ada problem / NC pada periode ini.');

            return;
        }

        $t = $this->newTable();
        $this->headerRow($t, [700, 1200, 1500, 1926, 1700, 1000, 1000],
            ['No', 'Nomor', 'Tanggal', 'Catatan', 'Rekomendasi', 'PIC', 'Status']);
        foreach ($ncs->values() as $i => $nc) {
            $t->addRow();
            $t->addCell(700, $this->bodyCell())->addText((string) ($i + 1), $this->smallFont(), $this->center());
            $t->addCell(1200, $this->bodyCell())->addText($nc->nomor ?? '-', $this->smallFont(), $this->center());
            $t->addCell(1500, $this->bodyCell())->addText($nc->laporanHarian?->tanggal_laporan?->translatedFormat('d M Y') ?? '-', $this->smallFont(), $this->center());
            $t->addCell(1926, $this->bodyCell())->addText($nc->kendala ?? '-', $this->smallFont());
            $t->addCell(1700, $this->bodyCell())->addText($nc->tindakan ?? '-', $this->smallFont());
            $t->addCell(1000, $this->bodyCell())->addText($nc->pic ?? '-', $this->smallFont(), $this->center());
            $statusCell = $t->addCell(1000, $this->bodyCell());
            $statusCell->addText($nc->status->label(), $this->smallFont(), $this->center());
            if ($nc->status === NcStatus::Closed && $nc->closed_at) {
                $statusCell->addText(
                    $nc->closed_at->translatedFormat('d M Y').($nc->closedBy ? ' · '.$nc->closedBy->name : ''),
                    ['size' => 7],
                    $this->center()
                );
            }
        }

        WordStyleService::setComplexBlock($processor, 'tabel_nc', $t);
    }

    // ──────────────────────────────────────────────────────────────────
    // sub-bab 3.1 — KESIMPULAN (poin bernomor yang di-derive dari data BAB 2)
    // ──────────────────────────────────────────────────────────────────
    private function fillKesimpulan(TemplateProcessor $processor, LaporanBulanan $laporan): void
    {
        $dk = $laporan->dataKapal;
        $selesai = $laporan->periode_selesai ?? $laporan->tanggal_laporan;
        $points = [];

        // 1. Progres fisik — entry terakhir progress history pada bulan ini.
        $last = null;
        if ($dk && $laporan->bulan_ke) {
            $bulan = $this->periodeService->getBulanPeriode($dk, (int) $laporan->bulan_ke);
            if ($bulan) {
                $last = collect($this->kurvaSService->getProgressHistory($dk))
                    ->filter(fn ($e) => $e['minggu_ke'] >= $bulan['minggu_dari'] && $e['minggu_ke'] <= $bulan['minggu_sampai'])
                    ->last();
            }
        }
        $points[] = $last
            ? 'Realisasi progres fisik pembangunan sampai dengan '.$this->tanggal($selesai).' mencapai '.$this->pct($last['cumulative_actual'] ?? 0).' terhadap rencana kumulatif '.$this->pct($last['cumulative_plan'] ?? 0).' dengan deviasi '.$this->pct($last['cumulative_deviation'] ?? 0, true).'.'
            : 'Belum terdapat data realisasi progres fisik pada periode pelaporan ini.';

        // 2. Realisasi waktu pelaksanaan (inklusif s.d. cutoff, clamp jangka).
        $jangka = (int) ($dk?->waktu_pelaksanaan_hari ?? 0);
        if ($jangka > 0) {
            [$realisasi, $sisa] = $this->realisasiWaktu($dk, $selesai);
            $points[] = 'Waktu pelaksanaan pembangunan telah berjalan '.$realisasi.' hari kalender dari total '.$jangka.' hari kalender, dengan sisa waktu '.$sisa.' hari kalender.';
        }

        // 3. Riwayat design & approval drawing per status (kumulatif s.d. cutoff).
        $drawings = $dk ? app(DrawingRegisterService::class)->historyUpTo($dk, $selesai) : collect();
        if ($drawings->isNotEmpty()) {
            $points[] = 'Tercatat '.$drawings->count().' revisi gambar dengan rincian status '.$this->countByLabel($drawings, fn ($d) => $d->status->label()).'.';
        }

        // 4. Sub-contractor/vendor per lingkup pekerjaan (kumulatif s.d. bulan ini).
        $vendorGroups = $dk ? $this->proyekService->getVendorsUpToBulanGrouped($dk, $laporan->bulan_ke) : collect();
        if ($vendorGroups->isNotEmpty()) {
            $detail = $vendorGroups
                ->map(fn ($items) => ($items->first()->lingkup?->nama ?? 'Lainnya').' ('.$items->count().')')
                ->implode(', ');
            $points[] = 'Tercatat '.$vendorGroups->flatten(1)->count().' sub-contractor/vendor yang terbagi dalam lingkup pekerjaan '.$detail.'.';
        }

        // 5. Material dan komponen per status (kumulatif s.d. cutoff).
        $materials = $dk ? $this->proyekService->getMaterialsGrouped($dk, $selesai)->flatten(1) : collect();
        if ($materials->isNotEmpty()) {
            $points[] = 'Tercatat '.$materials->count().' item material dan komponen dengan rincian status '.$this->countByLabel($materials, fn ($m) => $m->status->label()).'.';
        }

        // 6. Kegiatan pengujian per status (kumulatif s.d. cutoff).
        $pengujians = $dk ? $this->proyekService->getPengujiansUpTo($dk, $selesai) : collect();
        if ($pengujians->isNotEmpty()) {
            $points[] = 'Tercatat '.$pengujians->count().' kegiatan pengujian dengan rincian status '.$this->countByLabel($pengujians, fn ($u) => $u->status->label()).'.';
        }

        // 7. Rekap temuan Non-Conformity pada periode ini.
        $ncs = ($laporan->periode_mulai && $laporan->periode_selesai && $laporan->data_kapal_id)
            ? $this->ncService->rekapMingguan($laporan->data_kapal_id, $laporan->periode_mulai, $laporan->periode_selesai)
            : collect();
        $points[] = $ncs->isEmpty()
            ? 'Tidak terdapat temuan Non-Conformity pada periode pelaporan ini.'
            : 'Tercatat '.$ncs->count().' temuan Non-Conformity pada periode ini, terdiri dari '.$ncs->where('status', NcStatus::Open)->count().' temuan berstatus open dan '.$ncs->where('status', NcStatus::Closed)->count().' temuan berstatus closed.';

        $t = $this->newTable(false);
        foreach ($points as $i => $point) {
            $t->addRow();
            $t->addCell(500, $this->bodyCell())->addText(($i + 1).'.', $this->smallFont());
            $t->addCell(8526, $this->bodyCell())->addText($point, $this->smallFont(), ['alignment' => Jc::BOTH]);
        }

        WordStyleService::setComplexBlock($processor, 'poin_kesimpulan', $t);
    }

    // ──────────────────────────────────────────────────────────────────
    // sub-bab 4.1 — DOKUMEN EKSTERNAL
    // ──────────────────────────────────────────────────────────────────
    private function fillExternal(TemplateProcessor $processor, LaporanBulanan $laporan): void
    {
        $rows = $laporan->laporanExternal->filter(fn ($e) => $e->hasFile())->values();

        if ($rows->isEmpty()) {
            $processor->setValue('tabel_external', 'Tidak ada dokumen pendukung.');

            return;
        }

        $t = $this->newTable();
        $this->headerRow($t, [800, 5226, 3000], ['No', 'Judul', 'File']);
        foreach ($rows as $i => $e) {
            $t->addRow();
            $t->addCell(800, $this->bodyCell())->addText((string) ($i + 1), $this->smallFont(), $this->center());
            $t->addCell(5226, $this->bodyCell())->addText($e->judul ?: '-', $this->smallFont());
            $t->addCell(3000, $this->bodyCell())->addText($e->file_name ?? '-', $this->smallFont());
        }

        WordStyleService::setComplexBlock($processor, 'tabel_external', $t);
    }

    // ──────────────────────────────────────────────────────────────────
    // sub-bab 4.2 — daftar laporan mingguan yang dilampirkan
    // ──────────────────────────────────────────────────────────────────
    private function fillLampiranMingguan(TemplateProcessor $processor, LaporanBulanan $laporan): void
    {
        $rows = $this->bulananService->getMingguanBulan($laporan)->values();

        if ($rows->isEmpty()) {
            $processor->setValue('tabel_lampiran_mingguan', 'Tidak ada laporan mingguan pada periode ini.');

            return;
        }

        $t = $this->newTable();
        $this->headerRow($t, [800, 1800, 6426], ['No', 'Minggu Ke', 'Periode']);
        foreach ($rows as $i => $m) {
            $t->addRow();
            $t->addCell(800, $this->bodyCell())->addText((string) ($i + 1), $this->smallFont(), $this->center());
            $t->addCell(1800, $this->bodyCell())->addText((string) ($m->minggu_ke ?? '-'), $this->smallFont(), $this->center());
            $t->addCell(6426, $this->bodyCell())->addText(
                $this->tanggal($m->periode_mulai).' s.d. '.$this->tanggal($m->periode_selesai),
                $this->smallFont()
            );
        }

        WordStyleService::setComplexBlock($processor, 'tabel_lampiran_mingguan', $t);
    }

    // ──────────────────────────────────────────────────────────────────
    // sub-bab 4.2 — LAMPIRAN LAPORAN MINGGUAN (gambar halaman docx)
    // ──────────────────────────────────────────────────────────────────
    /**
     * Pakai docx mingguan yang sudah ada (doc_path); generate dulu via job
     * yang sama dengan halaman mingguan bila belum ada / filenya hilang.
     * Kesegaran dijaga LaporanDocumentService (perubahan mingguan, harian
     * atau NC di dalamnya mengosongkan doc_path).
     *
     * @return string[] doc_path (storage) docx mingguan, urut minggu_ke
     */
    private function resolveMingguanDocs(LaporanBulanan $laporan): array
    {
        $paths = [];

        foreach ($this->bulananService->getMingguanBulan($laporan) as $mingguan) {
            if (! $this->mingguanDocPath($mingguan)) {
                try {
                    GenerateLaporanMingguanJob::dispatchSync($mingguan);
                    $mingguan->refresh();
                } catch (\Throwable $e) {
                    Log::warning('LaporanBulananWordService: gagal generate docx mingguan', [
                        'laporan_mingguan_id' => $mingguan->id,
                        'error' => $e->getMessage(),
                    ]);
                    throw new \RuntimeException('Gagal menyiapkan lampiran mingguan beserta laporan harian.', 0, $e);
                }
            }

            $path = $this->mingguanDocPath($mingguan);
            if (! $path) {
                throw new \RuntimeException('Dokumen mingguan untuk lampiran bulanan belum tersedia.');
            }
            $paths[] = $path;
        }

        return $paths;
    }

    private function mingguanDocPath(LaporanMingguan $mingguan): ?string
    {
        if (! $mingguan->isDocCompleted() || ! $mingguan->doc_path) {
            return null;
        }

        return app(FilePathService::class)->exists($mingguan->doc_path) ? $mingguan->doc_path : null;
    }

    /**
     * Ganti paragraf ${lampiran_mingguan} dengan gambar halaman per docx
     * mingguan (docx → pdf → webp). Karena mingguan sendiri sudah
     * menyisipkan halaman harian sebagai gambar, rantai lampiran
     * terbawa utuh tanpa mekanisme altChunk bertingkat.
     *
     * @param  string[]  $chunks
     */
    private function embedMingguanDocs(string $docxPath, array $chunks): void
    {
        $attachment = app(WordAttachmentService::class);
        $images = [];
        foreach ($chunks as $chunk) {
            $images = array_merge($images, $attachment->reportPageImages($chunk));
        }

        if ($images) {
            $attachment->embedReportPages($docxPath, $images, '${lampiran_mingguan}', 'LAPORAN MINGGUAN');

            return;
        }

        // Tanpa lampiran: ganti placeholder dengan keterangan kosong.
        $zip = new \ZipArchive;
        if ($zip->open($docxPath) !== true) {
            throw new \RuntimeException('Gagal membuka dokumen bulanan untuk menyisipkan laporan mingguan.');
        }
        $document = $zip->getFromName('word/document.xml');
        $document = preg_replace('/<w:p[ >](?:(?!<\/w:p>).)*?\$\{lampiran_mingguan\}.*?<\/w:p>/s',
            '<w:p><w:r><w:rPr><w:i/><w:color w:val="7F7F7F"/></w:rPr><w:t>Tidak ada laporan mingguan pada periode ini.</w:t></w:r></w:p>', $document, 1);
        $zip->addFromString('word/document.xml', $document);
        $zip->close();
    }

    // ──────────────────────────────────────────────────────────────────
    // TABLE HELPERS
    // ──────────────────────────────────────────────────────────────────
    private function newTable(bool $bordered = true): Table
    {
        $table = new Table([
            'borderSize' => $bordered ? 4 : 0,
            'borderColor' => self::BORDER,
            'cellMarginLeft' => 80,
            'cellMarginRight' => 80,
            'cellMarginTop' => 40,
            'cellMarginBottom' => 40,
            'alignment' => JcTable::CENTER,
            'layout' => 'fixed',
            'unit' => TblWidth::TWIP,
            'width' => self::CONTENT_WIDTH,
        ]);

        return $table;
    }

    /** @param  int[]  $widths  @param  string[]  $labels */
    private function headerRow(Table $table, array $widths, array $labels): void
    {
        $table->addRow(null, ['tblHeader' => true]);
        foreach ($widths as $i => $w) {
            $table->addCell($w, ['bgColor' => self::NAVY, 'valign' => 'center'])
                ->addText($labels[$i], $this->headFont(), $this->center());
        }
    }

    private function headFont(): array
    {
        return ['bold' => true, 'color' => 'FFFFFF', 'size' => 9];
    }

    private function smallFont(): array
    {
        return ['size' => 9];
    }

    private function bodyCell(): array
    {
        return ['valign' => 'center'];
    }

    private function center(): array
    {
        return ['alignment' => Jc::CENTER];
    }

    /**
     * Format "Label (n), Label (n)" dari grouping koleksi per label.
     *
     * @param  callable(mixed): string  $labelOf
     */
    private function countByLabel(Collection $items, callable $labelOf): string
    {
        return $items
            ->groupBy(fn ($item) => $labelOf($item))
            ->map(fn ($group, $label) => $label.' ('.$group->count().')')
            ->implode(', ');
    }

    /**
     * Realisasi & sisa hari pelaksanaan (inklusif s.d. cutoff, clamp jangka).
     *
     * @return array{0: int, 1: int}
     */
    private function realisasiWaktu(?DataKapal $dk, ?CarbonInterface $selesai): array
    {
        return $dk?->progresWaktuPelaksanaan($selesai) ?? [0, 0];
    }

    private function pct(float $value, bool $signed = false): string
    {
        $formatted = number_format($value, 2, ',', '.').' %';

        return $signed && $value > 0 ? '+'.$formatted : $formatted;
    }

    private function tanggal(?CarbonInterface $date, string $format = 'd F Y'): string
    {
        return $date ? $date->translatedFormat($format) : '-';
    }

    // ──────────────────────────────────────────────────────────────────
    // SAVE DOCUMENT
    // ──────────────────────────────────────────────────────────────────
    /**
     * Simpan dokumen ke file lokal temporer; pemanggil menjalankan
     * post-processing (lampiran) lalu commitLocalFile() ke disk.
     *
     * @return array{0: string, 1: string} [path relatif di disk, path lokal]
     */
    private function saveDocument(TemplateProcessor $processor, LaporanBulanan $laporan): array
    {
        $filename = sprintf(
            'laporan-bulanan-%d-%s.docx',
            $laporan->id,
            now()->format('YmdHis')
        );

        $local = app(FilePathService::class)->tempPath('docx');
        $processor->saveAs($local);

        return ['laporan/word/'.$filename, $local];
    }
}
