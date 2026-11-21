<?php

namespace App\Services;

use App\Jobs\GenerateLaporanHarianJob;
use App\Models\DataKapal;
use App\Models\LaporanHarian;
use App\Models\LaporanLampiran;
use App\Models\LaporanMingguan;
use App\Models\LaporanNc;
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
 * Generate Laporan Mingguan (.docx) dari template.
 *
 * Tabel tanpa gambar dibangun di sini (setComplexBlock) agar header & isi
 * selalu sinkron dan bergaya seragam (header biru tua). Tabel bergambar
 * memakai cloneBlock/cloneRow + setImageValue di template.
 */
class LaporanMingguanWordService
{
    private const TEMPLATE_PATH = 'templates/laporan-mingguan/template-laporan-mingguan.docx';

    private const NAVY = '1F3864';

    private const LIGHT = 'D9E2F3';

    private const ZEBRA = 'F2F5FB';

    private const BORDER = '8EAADB';

    private const CONTENT_WIDTH = 9026;

    /**
     * Kotak gambar (px @96dpi) = area dalam sel "Dokumentasi" di template:
     * lebar kolom 5326 twip & tinggi baris tetap 3600 twip, dikurangi margin sel.
     * Gambar di-fit ke dalam kotak dengan rasio terjaga.
     */
    private const FOTO_BOX_WIDTH = 336;

    private const FOTO_BOX_HEIGHT = 228;

    private const HEAD_FONT = ['bold' => true, 'color' => 'FFFFFF', 'size' => 9];

    private const BODY_FONT = ['size' => 9];

    public function __construct(
        private WordImageService $imageService,
        private LaporanNcService $ncService,
        private KurvaSService $kurvaSService,
        private LaporanHarianService $harianService,
    ) {}

    /**
     * Generate Word document dari template mingguan.
     * Returns storage-relative path.
     */
    public function generate(LaporanMingguan $laporan): string
    {
        $laporan->loadMissing([
            'user',
            'dataKapal.owner',
            'dataKapal.galangan',
            'laporanHarian' => fn ($q) => $q->orderBy('tanggal_laporan'),
            'laporanHarian.dataKapal',
            'laporanHarian.aktivitas.workGroup',
            'laporanHarian.lampiran.aktivitas',
            'laporanExternal',
            'lampiran',
        ]);

        Settings::setOutputEscapingEnabled(true);

        $ncs = $this->ncService->rekapMingguan(
            $laporan->data_kapal_id,
            $laporan->periode_mulai,
            $laporan->periode_selesai,
        );

        $processor = new TemplateProcessor($this->resolveTemplate($laporan));

        $this->setSingleValues($processor, $laporan);
        $this->fillInfoUmum($processor, $laporan);
        $this->fillRingkasan($processor, $laporan);
        $this->fillKurvaS($processor, $laporan);
        $this->fillUraianKegiatan($processor, $laporan);
        $this->fillNc($processor, $laporan);
        $this->fillFoto($processor, $laporan);
        $this->fillExternal($processor, $laporan);
        $this->fillLampiranHarian($processor, $laporan);
        $this->fillLampiranNc($processor, $ncs);

        $files = app(FilePathService::class);
        [$relativePath, $localPath] = $this->saveDocument($processor, $laporan);
        try {
            $this->embedHarianDocs($localPath, $this->resolveHarianDocs($laporan));
            $attachmentService = app(WordAttachmentService::class);
            $attachmentService->append($localPath, $ncs, true);
            $attachmentService->embedExternals(
                $localPath,
                $laporan->laporanExternal,
            );
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
    private function resolveTemplate(LaporanMingguan $laporan): string
    {
        if ($laporan->dataKapal && $laporan->dataKapal->hasTemplate('mingguan')) {
            $path = $laporan->dataKapal->getTemplateFullPath('mingguan');
            if ($path && file_exists($path)) {
                return $path;
            }
        }

        $files = app(FilePathService::class);
        if (! $files->exists(self::TEMPLATE_PATH)) {
            throw new \RuntimeException(
                'Template Word mingguan tidak ditemukan. Upload template pada master Data Kapal atau sediakan default di penyimpanan: '.self::TEMPLATE_PATH
            );
        }

        return $files->localPath(self::TEMPLATE_PATH);
    }

    // ──────────────────────────────────────────────────────────────────
    // SINGLE VALUES
    // ──────────────────────────────────────────────────────────────────
    private function setSingleValues(TemplateProcessor $processor, LaporanMingguan $laporan): void
    {
        $periodeLabel = ($laporan->periode_mulai && $laporan->periode_selesai)
            ? $this->tanggal($laporan->periode_mulai).' – '.$this->tanggal($laporan->periode_selesai)
            : '-';

        $processor->setValues([
            'minggu_ke' => $laporan->minggu_ke !== null ? (string) $laporan->minggu_ke : '-',
            'periode_label' => $periodeLabel,
            'owner' => $laporan->dataKapal?->owner?->name ?? '-',
            'no_kapal' => $laporan->dataKapal?->nama ?? '-',
            'ttd_kota' => $laporan->dataKapal?->galangan?->kota ?? '-',
            'ttd_tanggal' => $this->tanggal($laporan->tanggal_laporan),
            'ttd_nama' => $laporan->user?->name ?? '-',
        ]);
    }

    // ──────────────────────────────────────────────────────────────────
    // INFORMASI UMUM (label : nilai) — field sama dengan laporan bulanan
    // ──────────────────────────────────────────────────────────────────
    private function fillInfoUmum(TemplateProcessor $processor, LaporanMingguan $laporan): void
    {
        WordStyleService::setComplexBlock($processor, 'tabel_info_umum', WordStyleService::labelValueTable(
            DataKapal::infoUmumRows($laporan->dataKapal, $laporan->periode_selesai ?? $laporan->tanggal_laporan)
        ));
    }

    // ──────────────────────────────────────────────────────────────────
    // A.1 RINGKASAN — satu <w:p> per baris input
    // ──────────────────────────────────────────────────────────────────
    /**
     * setValue mengubah newline menjadi <w:br/> dalam paragraf yang sama; pada
     * paragraf justify, baris yang diakhiri <w:br/> ikut di-stretch Word
     * selebar margin. ${blok_ringkasan} di-clone per baris agar tiap paragraf
     * logis menjadi <w:p> sendiri — baris terakhir tiap paragraf rata kiri.
     */
    private function fillRingkasan(TemplateProcessor $processor, LaporanMingguan $laporan): void
    {
        $raw = trim((string) $laporan->ringkasan);
        $baris = $raw === '' ? [] : preg_split('/\R/', $raw);

        // Template custom lama tanpa ${blok_ringkasan}: pertahankan perilaku lama
        if ($processor->cloneBlock('blok_ringkasan', max(count($baris), 1), true, true) === null) {
            $processor->setValue('ringkasan', $baris === [] ? '-' : implode("\n", $baris));

            return;
        }

        if ($baris === []) {
            $processor->setValue('ringkasan#1', '-');

            return;
        }

        foreach ($baris as $i => $teks) {
            $processor->setValue('ringkasan#'.($i + 1), $teks);
        }
    }

    // ──────────────────────────────────────────────────────────────────
    // A.2 KURVA-S — per work group (minggu ini + kumulatif)
    // ──────────────────────────────────────────────────────────────────
    private function fillKurvaS(TemplateProcessor $processor, LaporanMingguan $laporan): void
    {
        $workGroups = $laporan->dataKapal
            ? $this->kurvaSService->getWorkGroups($laporan->dataKapal)->values()
            : collect();

        if ($workGroups->isEmpty()) {
            $processor->setValue('tabel_kurva_s', 'Kurva-S belum dikonfigurasi untuk data kapal ini.');

            return;
        }

        // Ambil history s.d. minggu laporan ini
        $history = collect($this->kurvaSService->getProgressHistory($laporan->dataKapal))
            ->filter(fn ($h) => $laporan->minggu_ke === null || $h['minggu_ke'] <= $laporan->minggu_ke)
            ->values();
        $current = $history->firstWhere('minggu_ke', $laporan->minggu_ke) ?? [];

        $rows = $workGroups->map(fn ($wg) => [
            'nama' => $wg->nama,
            'bobot' => (float) $wg->bobot,
            'rencana_w' => (float) ($current['plans'][$wg->id] ?? 0),
            'aktual_w' => (float) ($current['progress'][$wg->id] ?? 0),
            'rencana_cum' => (float) $history->sum(fn ($h) => $h['plans'][$wg->id] ?? 0),
            'aktual_cum' => (float) $history->sum(fn ($h) => $h['progress'][$wg->id] ?? 0),
        ]);

        $w = [500, 2626, 900, 1000, 1000, 1000, 1000, 1000];
        $table = $this->newTable();

        // Header 2 tingkat: Minggu Ini & Kumulatif
        $table->addRow(null, ['tblHeader' => true]);
        $this->headCell($table, $w[0], 'No', ['vMerge' => 'restart']);
        $this->headCell($table, $w[1], 'Work Group', ['vMerge' => 'restart']);
        $this->headCell($table, $w[2], 'Bobot (%)', ['vMerge' => 'restart']);
        $this->headCell($table, $w[3] + $w[4], 'Minggu Ini (%)', ['gridSpan' => 2]);
        $this->headCell($table, $w[5] + $w[6], 'Kumulatif (%)', ['gridSpan' => 2]);
        $this->headCell($table, $w[7], 'Deviasi (%)', ['vMerge' => 'restart']);

        $table->addRow(null, ['tblHeader' => true]);
        foreach ([0, 1, 2] as $i) {
            $table->addCell($w[$i], ['vMerge' => 'continue', 'bgColor' => self::NAVY]);
        }
        foreach (['Rencana', 'Realisasi', 'Rencana', 'Realisasi'] as $i => $label) {
            $this->headCell($table, $w[$i + 3], $label);
        }
        $table->addCell($w[7], ['vMerge' => 'continue', 'bgColor' => self::NAVY]);

        foreach ($rows->values() as $i => $r) {
            $table->addRow(null, ['cantSplit' => true]);
            $bg = $i % 2 ? self::ZEBRA : null;
            $this->bodyCell($table, $w[0], (string) ($i + 1), $bg, Jc::CENTER);
            $this->bodyCell($table, $w[1], $r['nama'], $bg);
            $this->bodyCell($table, $w[2], $this->pct($r['bobot']), $bg, Jc::CENTER);
            $this->bodyCell($table, $w[3], $this->pct($r['rencana_w']), $bg, Jc::CENTER);
            $this->bodyCell($table, $w[4], $this->pct($r['aktual_w']), $bg, Jc::CENTER);
            $this->bodyCell($table, $w[5], $this->pct($r['rencana_cum']), $bg, Jc::CENTER);
            $this->bodyCell($table, $w[6], $this->pct($r['aktual_cum']), $bg, Jc::CENTER);
            $this->bodyCell($table, $w[7], $this->pct($r['aktual_cum'] - $r['rencana_cum'], true), $bg, Jc::CENTER);
        }

        // Baris total
        $table->addRow(null, ['cantSplit' => true]);
        $totalFont = ['bold' => true, 'size' => 9, 'color' => self::NAVY];
        $table->addCell($w[0] + $w[1], ['gridSpan' => 2, 'bgColor' => self::LIGHT, 'valign' => 'center'])
            ->addText('TOTAL', $totalFont, ['alignment' => Jc::CENTER]);
        $totals = [
            $rows->sum('bobot'), $rows->sum('rencana_w'), $rows->sum('aktual_w'),
            $rows->sum('rencana_cum'), $rows->sum('aktual_cum'),
        ];
        foreach ($totals as $i => $val) {
            $table->addCell($w[$i + 2], ['bgColor' => self::LIGHT, 'valign' => 'center'])
                ->addText($this->pct($val), $totalFont, ['alignment' => Jc::CENTER]);
        }
        $table->addCell($w[7], ['bgColor' => self::LIGHT, 'valign' => 'center'])
            ->addText($this->pct($totals[4] - $totals[3], true), $totalFont, ['alignment' => Jc::CENTER]);

        WordStyleService::setComplexBlock($processor, 'tabel_kurva_s', $table);
    }

    // ──────────────────────────────────────────────────────────────────
    // B. URAIAN KEGIATAN — per kategori (urut work group, "Lainnya" terakhir)
    // ──────────────────────────────────────────────────────────────────
    private function fillUraianKegiatan(TemplateProcessor $processor, LaporanMingguan $laporan): void
    {
        $groups = $this->harianService->groupAktivitasPerKategori(
            $laporan->laporanHarian->flatMap(fn ($h) => $h->aktivitas)
        );

        if ($groups->isEmpty()) {
            $processor->setValue('tabel_uraian', 'Tidak ada uraian kegiatan pada minggu ini.');

            return;
        }

        $w = [700, 5826, 2500];
        $table = $this->newTable();
        $this->headerRow($table, $w, ['No', 'Aktivitas', 'PIC']);

        $groupFont = ['bold' => true, 'size' => 9, 'color' => self::NAVY];
        foreach ($groups as $g => $group) {
            $n = $g + 1;
            $table->addRow(null, ['cantSplit' => true]);
            $table->addCell($w[0], ['bgColor' => self::LIGHT, 'valign' => 'center'])
                ->addText((string) $n, $groupFont, ['alignment' => Jc::CENTER]);
            $table->addCell($w[1] + $w[2], ['gridSpan' => 2, 'bgColor' => self::LIGHT, 'valign' => 'center'])
                ->addText($group['nama'], $groupFont);

            foreach ($group['aktivitas'] as $i => $a) {
                $table->addRow(null, ['cantSplit' => true]);
                $this->bodyCell($table, $w[0], $n.'.'.($i + 1), null, Jc::CENTER);
                $this->bodyCell($table, $w[1], $a->aktivitas ?: '-');
                $this->bodyCell($table, $w[2], $a->pic ?: '-');
            }
        }

        WordStyleService::setComplexBlock($processor, 'tabel_uraian', $table);
    }

    // ──────────────────────────────────────────────────────────────────
    // C. REKOMENDASI / NC — open terbawa terus, closed muncul di minggu closed
    // ──────────────────────────────────────────────────────────────────
    private function fillNc(TemplateProcessor $processor, LaporanMingguan $laporan): void
    {
        $ncs = ($laporan->periode_mulai && $laporan->periode_selesai)
            ? $this->ncService->rekapMingguan($laporan->data_kapal_id, $laporan->periode_mulai, $laporan->periode_selesai)
            : collect();

        if ($ncs->isEmpty()) {
            $processor->setValue('tabel_nc', 'Tidak ada rekomendasi / NC pada minggu ini.');

            return;
        }

        $w = [900, 2526, 2400, 1300, 1100, 800];
        $table = $this->newTable();
        $this->headerRow($table, $w, ['No', 'Kendala / Temuan', 'Tindakan', 'PIC', 'Tanggal Temuan', 'Status']);

        foreach ($ncs->values() as $i => $nc) {
            $table->addRow(null, ['cantSplit' => true]);
            $bg = $i % 2 ? self::ZEBRA : null;
            $this->bodyCell($table, $w[0], $nc->nomor ?? (string) ($i + 1), $bg, Jc::CENTER);
            $this->bodyCell($table, $w[1], $nc->kendala ?: '-', $bg);
            $this->bodyCell($table, $w[2], $nc->tindakan ?: '-', $bg);
            $this->bodyCell($table, $w[3], $nc->pic ?: '-', $bg);
            $this->bodyCell($table, $w[4], $this->tanggal($nc->laporanHarian?->tanggal_laporan ?? $nc->created_at, 'd/m/Y'), $bg, Jc::CENTER);
            $table->addCell($w[5], ['valign' => 'center', 'bgColor' => $bg])
                ->addText($nc->status->label(), ['bold' => true, 'size' => 9, 'color' => $nc->status->wordColor()], ['alignment' => Jc::CENTER]);
        }

        WordStyleService::setComplexBlock($processor, 'tabel_nc', $table);
    }

    // ──────────────────────────────────────────────────────────────────
    // D. FOTO / DOKUMENTASI — semua lampiran terpilih dalam satu tabel
    // (urut tanggal laporan harian, tanpa heading segmen per laporan)
    // ──────────────────────────────────────────────────────────────────
    private function fillFoto(TemplateProcessor $processor, LaporanMingguan $laporan): void
    {
        $selectedIds = $laporan->lampiran->pluck('id')->flip();
        $fotos = $laporan->laporanHarian
            ->flatMap(fn ($h) => $h->lampiran)
            ->filter(fn ($l) => $selectedIds->has($l->id) && $l->isFileCompleted())
            ->values();

        if ($fotos->isEmpty()) {
            $processor->cloneBlock('blok_foto', 0, true, true);
            $processor->setValue('foto_kosong', 'Tidak ada dokumentasi terpilih pada minggu ini.');

            return;
        }

        $processor->setValue('foto_kosong', '');
        $processor->cloneBlock('blok_foto', 1, true, true);
        $this->fillFotoRows($processor, 1, $fotos, ['no' => 'fh_no', 'gambar' => 'fh_gambar', 'ket' => 'fh_ket']);
    }

    // ──────────────────────────────────────────────────────────────────
    // E.1 DOKUMEN EKSTERNAL
    // ──────────────────────────────────────────────────────────────────
    private function fillExternal(TemplateProcessor $processor, LaporanMingguan $laporan): void
    {
        $rows = $laporan->laporanExternal->values();

        if ($rows->isEmpty()) {
            $processor->setValue('tabel_external', 'Tidak ada dokumen eksternal.');

            return;
        }

        $w = [500, 3326, 3000, 2200];
        $table = $this->newTable();
        $this->headerRow($table, $w, ['No', 'Judul Dokumen', 'Nama File', 'Keterangan']);

        foreach ($rows as $i => $ext) {
            $table->addRow(null, ['cantSplit' => true]);
            $bg = $i % 2 ? self::ZEBRA : null;
            $this->bodyCell($table, $w[0], (string) ($i + 1), $bg, Jc::CENTER);
            $this->bodyCell($table, $w[1], $ext->judul ?: '-', $bg);
            $this->bodyCell($table, $w[2], $ext->file_name ?: '-', $bg);
            $keterangan = match (true) {
                $ext->isFileCompleted() && $ext->hasFile() => 'Terlampir',
                $ext->isFileProcessing() => 'Sedang diproses',
                $ext->isFileFailed() => 'Gagal diproses',
                default => '-',
            };
            $this->bodyCell($table, $w[3], $keterangan, $bg);
        }

        WordStyleService::setComplexBlock($processor, 'tabel_external', $table);
    }

    // ──────────────────────────────────────────────────────────────────
    // E.2 LAPORAN HARIAN — daftar laporan yang dilampirkan
    // ──────────────────────────────────────────────────────────────────
    private function fillLampiranHarian(TemplateProcessor $processor, LaporanMingguan $laporan): void
    {
        $rows = $laporan->laporanHarian->values();

        if ($rows->isEmpty()) {
            $processor->setValue('tabel_lampiran_harian', 'Tidak ada laporan harian pada periode ini.');

            return;
        }

        $w = [500, 2200, 6326];
        $table = $this->newTable();
        $this->headerRow($table, $w, ['No', 'Tanggal', 'Judul']);

        foreach ($rows as $i => $harian) {
            $table->addRow(null, ['cantSplit' => true]);
            $bg = $i % 2 ? self::ZEBRA : null;
            $this->bodyCell($table, $w[0], (string) ($i + 1), $bg, Jc::CENTER);
            $this->bodyCell($table, $w[1], $this->tanggal($harian->tanggal_laporan), $bg);
            $this->bodyCell($table, $w[2], $harian->judul ?: '-', $bg);
        }

        WordStyleService::setComplexBlock($processor, 'tabel_lampiran_harian', $table);
    }

    // ──────────────────────────────────────────────────────────────────
    // E.3 LAMPIRAN NC — daftar NC yang dilampirkan
    // ──────────────────────────────────────────────────────────────────
    /**
     * @param  Collection<int, LaporanNc>  $ncs
     */
    private function fillLampiranNc(TemplateProcessor $processor, Collection $ncs): void
    {
        if ($ncs->isEmpty()) {
            $processor->setValue('tabel_lampiran_nc', 'Tidak ada non-conformance pada periode ini.');

            return;
        }

        $w = [500, 1800, 4726, 2000];
        $table = $this->newTable();
        $this->headerRow($table, $w, ['No', 'Nomor', 'Kendala', 'Status']);

        foreach ($ncs->values() as $i => $nc) {
            $table->addRow(null, ['cantSplit' => true]);
            $bg = $i % 2 ? self::ZEBRA : null;
            $this->bodyCell($table, $w[0], (string) ($i + 1), $bg, Jc::CENTER);
            $this->bodyCell($table, $w[1], $nc->nomor ?: '-', $bg);
            $this->bodyCell($table, $w[2], $nc->kendala ?: '-', $bg);
            $this->bodyCell($table, $w[3], $nc->status?->label() ?: '-', $bg, Jc::CENTER);
        }

        WordStyleService::setComplexBlock($processor, 'tabel_lampiran_nc', $table);
    }

    // ──────────────────────────────────────────────────────────────────
    // E.2 LAPORAN HARIAN — docx harian utuh disisipkan sebagai gambar halaman
    // ──────────────────────────────────────────────────────────────────
    /**
     * Pakai docx harian yang sudah ada (doc_path); generate dulu via job
     * yang sama dengan halaman harian bila belum ada / filenya hilang.
     * Kesegaran dijaga LaporanDocumentService (edit harian mengosongkan
     * doc_path). Harian yang gagal disiapkan menggagalkan mingguan.
     *
     * @return string[] doc_path (storage) docx harian, urut tanggal
     */
    private function resolveHarianDocs(LaporanMingguan $laporan): array
    {
        $paths = [];

        foreach ($laporan->laporanHarian as $harian) {
            if (! $this->harianDocPath($harian)) {
                try {
                    GenerateLaporanHarianJob::dispatchSync($harian);
                    $harian->refresh();
                } catch (\Throwable $e) {
                    Log::warning('LaporanMingguanWordService: gagal generate docx harian', [
                        'laporan_harian_id' => $harian->id,
                        'error' => $e->getMessage(),
                    ]);
                    throw new \RuntimeException('Gagal menyiapkan lampiran laporan harian.', 0, $e);
                }
            }

            $path = $this->harianDocPath($harian);
            if (! $path) {
                throw new \RuntimeException('Dokumen harian untuk lampiran mingguan belum tersedia.');
            }
            $paths[] = $path;
        }

        return $paths;
    }

    private function harianDocPath(LaporanHarian $harian): ?string
    {
        if (! $harian->isDocCompleted() || ! $harian->doc_path) {
            return null;
        }

        return app(FilePathService::class)->exists($harian->doc_path) ? $harian->doc_path : null;
    }

    /**
     * Ganti paragraf ${lampiran_harian} dengan gambar halaman per docx
     * harian (docx → pdf → webp). Isi tampil identik di semua viewer
     * dan heading laporan anak tidak bocor ke outline dokumen induk.
     *
     * @param  string[]  $chunks
     */
    private function embedHarianDocs(string $docxPath, array $chunks): void
    {
        $attachment = app(WordAttachmentService::class);
        $images = [];
        foreach ($chunks as $chunk) {
            $images = array_merge($images, $attachment->reportPageImages($chunk));
        }

        if ($images) {
            $attachment->embedReportPages($docxPath, $images, '${lampiran_harian}', 'LAPORAN HARIAN');

            return;
        }

        // Tanpa lampiran: ganti placeholder dengan keterangan kosong.
        $zip = new \ZipArchive;
        if ($zip->open($docxPath) !== true) {
            throw new \RuntimeException('Gagal membuka dokumen mingguan untuk menyisipkan laporan harian.');
        }
        $document = $zip->getFromName('word/document.xml');
        $document = preg_replace('/<w:p[ >](?:(?!<\/w:p>).)*?\$\{lampiran_harian\}.*?<\/w:p>/s',
            '<w:p><w:r><w:rPr><w:i/><w:color w:val="7F7F7F"/></w:rPr><w:t>Tidak ada laporan harian pada periode ini.</w:t></w:r></w:p>', $document, 1);
        $zip->addFromString('word/document.xml', $document);
        $zip->close();
    }

    // ──────────────────────────────────────────────────────────────────
    // FOTO ROWS (cloneRow + gambar)
    // ──────────────────────────────────────────────────────────────────
    /**
     * Isi baris foto pada blok ke-$block (macro hasil cloneBlock: name#block).
     *
     * @param  Collection<int, LaporanLampiran>  $fotos
     * @param  array{no: string, gambar: string, ket: string}  $macro
     */
    private function fillFotoRows(TemplateProcessor $processor, int $block, Collection $fotos, array $macro): void
    {
        $suffix = '#'.$block;

        if ($fotos->isEmpty()) {
            $processor->setValue($macro['no'].$suffix, '-');
            $processor->setValue($macro['gambar'].$suffix, 'Tidak ada dokumentasi.');
            $processor->setValue($macro['ket'].$suffix, '-');

            return;
        }

        $processor->cloneRow($macro['no'].$suffix, $fotos->count());
        foreach ($fotos as $i => $lamp) {
            $r = $suffix.'#'.($i + 1);
            $processor->setValue($macro['no'].$r, (string) ($i + 1));
            $this->setImage($processor, $macro['gambar'].$r, $lamp);
            $processor->setValue($macro['ket'].$r, $lamp->keterangan ?: '-');
        }
    }

    private function setImage(TemplateProcessor $processor, string $macro, LaporanLampiran $lampiran): void
    {
        $fallback = $lampiran->file_name ?? '-';
        $imagePath = $this->imageService->isImageFile($lampiran->file_path)
            ? $this->imageService->resolveLampiranPath($lampiran->file_path)
            : null;

        if (! $imagePath) {
            $processor->setValue($macro, $fallback);

            return;
        }

        try {
            // Resolusi file 3x kotak agar tetap tajam saat dicetak
            $finalImage = $this->imageService->prepareImageForWord($imagePath, self::FOTO_BOX_WIDTH * 3, self::FOTO_BOX_HEIGHT * 3, 75);
            $processor->setImageValue($macro, [
                'path' => $finalImage,
                'width' => self::FOTO_BOX_WIDTH,
                'height' => self::FOTO_BOX_HEIGHT,
                'ratio' => true,
            ]);
            if ($finalImage !== $imagePath && file_exists($finalImage)) {
                @unlink($finalImage);
            }
        } catch (\Exception $e) {
            Log::error('LaporanMingguanWordService: gagal sisipkan gambar', [
                'lampiran_id' => $lampiran->id,
                'error' => $e->getMessage(),
            ]);
            $processor->setValue($macro, $fallback);
        }
    }

    // ──────────────────────────────────────────────────────────────────
    // TABLE HELPERS
    // ──────────────────────────────────────────────────────────────────
    private function newTable(): Table
    {
        return new Table([
            'borderSize' => 4,
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
    }

    /**
     * @param  int[]  $widths
     * @param  string[]  $labels
     */
    private function headerRow(Table $table, array $widths, array $labels): void
    {
        $table->addRow(null, ['tblHeader' => true, 'cantSplit' => true]);
        foreach ($labels as $i => $label) {
            $this->headCell($table, $widths[$i], $label);
        }
    }

    private function headCell(Table $table, int $width, string $text, array $style = []): void
    {
        $table->addCell($width, $style + ['bgColor' => self::NAVY, 'valign' => 'center'])
            ->addText($text, self::HEAD_FONT, ['alignment' => Jc::CENTER]);
    }

    private function bodyCell(Table $table, int $width, string $text, ?string $bg = null, string $align = Jc::START): void
    {
        $cell = $table->addCell($width, array_filter(['valign' => 'center', 'bgColor' => $bg]));
        // Pertahankan baris baru dari input pengguna
        foreach (preg_split('/\R/', $text) as $line) {
            $cell->addText($line, self::BODY_FONT, ['alignment' => $align]);
        }
    }

    // ──────────────────────────────────────────────────────────────────
    // FORMATTERS
    // ──────────────────────────────────────────────────────────────────
    private function pct(float $value, bool $signed = false): string
    {
        $formatted = number_format(round($value, 2), 2, ',', '.');

        return $signed && $value > 0 ? '+'.$formatted : $formatted;
    }

    private function tanggal(?CarbonInterface $date, string $format = 'd F Y'): string
    {
        return $date ? $date->copy()->locale('id')->translatedFormat($format) : '-';
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
    private function saveDocument(TemplateProcessor $processor, LaporanMingguan $laporan): array
    {
        $filename = sprintf('laporan-mingguan-%d-%s.docx', $laporan->id, now()->format('YmdHis'));

        $local = app(FilePathService::class)->tempPath('docx');
        $processor->saveAs($local);

        return ['laporan/word/'.$filename, $local];
    }
}
