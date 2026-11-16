<?php

namespace App\Services;

use App\Enums\MaterialKategori;
use App\Enums\NcStatus;
use App\Jobs\GenerateLaporanMingguanJob;
use App\Models\LaporanBulanan;
use App\Models\LaporanMingguan;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
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
 * disisipkan utuh via w:altChunk (pola harian→mingguan).
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
        private WordChunkService $chunkService,
    ) {}

    public function generate(LaporanBulanan $laporan): string
    {
        Settings::setOutputEscapingEnabled(true);

        $laporan->loadMissing([
            'user',
            'dataKapal.company',
            'dataKapal.galangan',
            'laporanExternal',
        ]);

        $processor = new TemplateProcessor($this->resolveTemplate($laporan));

        $this->setSingleValues($processor, $laporan);
        $this->fillExecProgress($processor, $laporan);
        $this->fillInfoUmum($processor, $laporan);
        $this->fillSpesifikasi($processor, $laporan);
        $this->fillDrawing($processor, $laporan);
        $this->fillRealisasi($processor, $laporan);
        $this->fillVendor($processor, $laporan);
        $this->fillMaterial($processor, $laporan);
        $this->fillWaktu($processor, $laporan);
        $this->fillProgressMingguan($processor, $laporan);
        $this->fillPengujian($processor, $laporan);
        $this->fillRapat($processor, $laporan);
        $this->fillNc($processor, $laporan);
        $this->fillExternal($processor, $laporan);

        $relativePath = $this->saveDocument($processor, $laporan);
        $path = storage_path('app/'.$relativePath);
        try {
            $this->embedMingguanDocs($path, $this->resolveMingguanDocs($laporan));
        } catch (\Throwable $e) {
            unlink($path);
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
        $dk = $laporan->dataKapal;
        $ref = $laporan->periode_selesai ?? $laporan->tanggal_laporan;

        $processor->setValues([
            'bulan_label' => $ref ? strtoupper($ref->translatedFormat('F Y')) : '-',
            'cutoff' => $this->tanggal($laporan->periode_selesai ?? $laporan->tanggal_laporan),
            'nama_pekerjaan' => $dk?->nama_pekerjaan ?: ($dk?->nama ?? '-'),
            'nama_kapal' => $dk?->nama ?? '-',
            'perusahaan' => $dk?->company?->name ?? '-',
            'perusahaan_alamat' => $dk?->company?->address ?? '',
            'galangan' => $dk?->galangan?->nama ?? '-',
            'nomor_kontrak' => $dk?->nomor_kontrak ?: '-',
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
        $dk = $laporan->dataKapal;

        $rows = [
            'NAMA PEKERJAAN' => $dk?->nama_pekerjaan ?: ($dk?->nama ?? '-'),
            'KAPAL YANG DISUPERVISI' => $dk?->nama ?? '-',
            'NOMOR PEMBANGUNAN' => $dk?->nomor_pembangunan ?: '-',
            'PEMILIK' => trim(($dk?->company?->name ?? '-').' '.($dk?->company?->address ?? '')),
            'KONSULTANSI' => 'PT. BIRO KLASIFIKASI INDONESIA (PERSERO) SBU Marine & Offshore Migas',
            'KONTRAKTOR / GALANGAN' => trim(($dk?->galangan?->nama ?? '-').' '.($dk?->galangan?->alamat ?? '')),
            'NO. KONTRAK' => $dk?->nomor_kontrak ?: '-',
            'WAKTU PELAKSANAAN' => $dk?->durasi_kontrak_hari ? $dk->durasi_kontrak_hari.' Hari Kalender' : '-',
            'LOKASI PENGAWASAN' => trim(($dk?->galangan?->nama ?? '-').', '.($dk?->galangan?->kota ?? '')),
        ];

        WordStyleService::setComplexBlock($processor, 'tabel_info_umum', $this->labelValueTable($rows));
    }

    // ──────────────────────────────────────────────────────────────────
    // BAB 2.1 — SPESIFIKASI KAPAL
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
    // BAB 2.2 — STATUS DESIGN & APPROVAL DRAWING (3 blok agregat)
    // ──────────────────────────────────────────────────────────────────
    private function fillDrawing(TemplateProcessor $processor, LaporanBulanan $laporan): void
    {
        $dk = $laporan->dataKapal;
        $aggr = $dk ? $this->proyekService->getDrawingAggregates($dk) : null;

        if (! $aggr || $aggr['total'] === 0) {
            foreach (['tabel_drawing_galangan', 'tabel_drawing_mno', 'tabel_drawing_klas'] as $macro) {
                $processor->setValue($macro, 'Belum ada data drawing register.');
            }

            return;
        }

        $pct = fn (?float $v) => $v === null ? '-' : number_format($v, 2, ',', '.').' %';

        WordStyleService::setComplexBlock($processor, 'tabel_drawing_galangan', $this->labelValueTable([
            'Total Gambar dari Galangan' => (string) $aggr['total'],
            'Total Gambar yang sudah dibuat oleh Galangan' => (string) $aggr['produksi_selesai'],
            'Progress Penyelesaian Gambar oleh Galangan' => $pct($aggr['produksi_pct']),
        ]));

        WordStyleService::setComplexBlock($processor, 'tabel_drawing_mno', $this->labelValueTable([
            'Total Gambar yang sudah diterima MNO' => (string) $aggr['mno_diterima'],
            'Total Gambar yang telah direview MNO' => (string) $aggr['mno_selesai'],
            'Progress Penyelesaian Review Gambar oleh MNO' => $pct($aggr['mno_pct']),
            'Jumlah "comment"' => (string) $aggr['mno_comment'],
            'Jumlah "comment" dengan status "Open"' => (string) $aggr['mno_open'],
            'Jumlah "comment" dengan status "Closed"' => (string) $aggr['mno_closed'],
            'Jumlah "comment" dengan status "Informasi"' => (string) $aggr['mno_informasi'],
        ]));

        WordStyleService::setComplexBlock($processor, 'tabel_drawing_klas', $this->labelValueTable([
            'Total Gambar yang sudah dikirim ke Klas' => (string) $aggr['klas_dikirim'],
            'Total Gambar yang telah direview / disetujui Klas' => (string) $aggr['klas_disetujui'],
            'Progress Penyelesaian Review / Approval oleh Klas' => $pct($aggr['klas_pct']),
            'Jumlah "comment"' => (string) $aggr['klas_comment'],
            'Jumlah "comment" dengan status "Open"' => (string) $aggr['klas_open'],
            'Jumlah "comment" dengan status "Closed"' => (string) $aggr['klas_closed'],
            'Jumlah "comment" dengan status "Informasi"' => (string) $aggr['klas_informasi'],
        ]));
    }

    // ──────────────────────────────────────────────────────────────────
    // BAB 2.3 — REALISASI PROGRESS (milestone)
    // ──────────────────────────────────────────────────────────────────
    private function fillRealisasi(TemplateProcessor $processor, LaporanBulanan $laporan): void
    {
        $rows = collect($laporan->realisasi_progress ?? []);

        if ($rows->isEmpty()) {
            $processor->setValue('tabel_realisasi', 'Belum ada realisasi progress pada bulan ini.');

            return;
        }

        $t = $this->newTable();
        $this->headerRow($t, [800, 4426, 2200, 1600], ['No', 'Realisasi Pekerjaan', 'Tanggal', 'Ket.']);
        foreach ($rows->values() as $i => $r) {
            $tanggal = ! empty($r['tanggal']) ? $this->tanggal(Carbon::parse($r['tanggal']), 'd M Y') : '-';
            $t->addRow();
            $t->addCell(800, $this->bodyCell())->addText((string) ($i + 1), $this->smallFont(), $this->center());
            $t->addCell(4426, $this->bodyCell())->addText($r['uraian'] ?? '-', $this->smallFont());
            $t->addCell(2200, $this->bodyCell())->addText($tanggal, $this->smallFont(), $this->center());
            $t->addCell(1600, $this->bodyCell())->addText($r['keterangan'] ?? '-', $this->smallFont(), $this->center());
        }

        WordStyleService::setComplexBlock($processor, 'tabel_realisasi', $t);
    }

    // ──────────────────────────────────────────────────────────────────
    // BAB 2.4 — VENDOR / SUB-CONTRACTOR
    // ──────────────────────────────────────────────────────────────────
    private function fillVendor(TemplateProcessor $processor, LaporanBulanan $laporan): void
    {
        $rows = $laporan->dataKapal?->projectVendors ?? collect();

        if ($rows->isEmpty()) {
            $processor->setValue('tabel_vendor', 'Belum ada daftar sub-contractor / vendor.');

            return;
        }

        $t = $this->newTable();
        $this->headerRow($t, [800, 3000, 3226, 2000], ['No', 'Nama', 'Lingkup Pekerjaan', 'Kontak']);
        foreach ($rows->values() as $i => $v) {
            $t->addRow();
            $t->addCell(800, $this->bodyCell())->addText((string) ($i + 1), $this->smallFont(), $this->center());
            $t->addCell(3000, $this->bodyCell())->addText($v->nama, $this->smallFont());
            $t->addCell(3226, $this->bodyCell())->addText($v->lingkup_pekerjaan ?? '-', $this->smallFont());
            $t->addCell(2000, $this->bodyCell())->addText($v->kontak ?? '-', $this->smallFont());
        }

        WordStyleService::setComplexBlock($processor, 'tabel_vendor', $t);
    }

    // ──────────────────────────────────────────────────────────────────
    // BAB 2.5 — MATERIAL & KOMPONEN (dikelompokkan per kategori)
    // ──────────────────────────────────────────────────────────────────
    private function fillMaterial(TemplateProcessor $processor, LaporanBulanan $laporan): void
    {
        $groups = $laporan->dataKapal
            ? $this->proyekService->getMaterialsGrouped($laporan->dataKapal)
            : collect();

        if ($groups->isEmpty()) {
            $processor->setValue('tabel_material', 'Belum ada pengadaan material / komponen.');

            return;
        }

        $t = $this->newTable();
        $this->headerRow($t, [700, 2700, 2226, 1400, 1400, 600], ['No', 'Item', 'Spesifikasi', 'Jumlah', 'Status', 'Tgl']);

        $no = 0;
        foreach (MaterialKategori::cases() as $kat) {
            $items = $groups->get($kat->value);
            if (! $items || $items->isEmpty()) {
                continue;
            }

            // Baris judul kategori (span semua kolom)
            $t->addRow();
            $t->addCell(9026, ['bgColor' => self::LIGHT, 'valign' => 'center', 'gridSpan' => 6])
                ->addText(strtoupper($kat->label()), ['bold' => true, 'size' => 8, 'color' => self::NAVY]);

            foreach ($items as $m) {
                $no++;
                $jumlah = $m->jumlah !== null
                    ? rtrim(rtrim(number_format((float) $m->jumlah, 2, ',', '.'), '0'), ',').' '.($m->satuan ?? '')
                    : '-';
                $t->addRow();
                $t->addCell(700, $this->bodyCell())->addText((string) $no, $this->smallFont(), $this->center());
                $t->addCell(2700, $this->bodyCell())->addText($m->nama_item, $this->smallFont());
                $t->addCell(2226, $this->bodyCell())->addText($m->spesifikasi ?? '-', $this->smallFont());
                $t->addCell(1400, $this->bodyCell())->addText(trim($jumlah), $this->smallFont(), $this->center());
                $t->addCell(1400, $this->bodyCell())->addText($m->status->label(), $this->smallFont(), $this->center());
                $t->addCell(600, $this->bodyCell())->addText($m->tanggal?->format('d/m/y') ?? '-', $this->smallFont(), $this->center());
            }
        }

        WordStyleService::setComplexBlock($processor, 'tabel_material', $t);
    }

    // ──────────────────────────────────────────────────────────────────
    // BAB 2.6 — WAKTU PELAKSANAAN (hari kalender)
    // ──────────────────────────────────────────────────────────────────
    private function fillWaktu(TemplateProcessor $processor, LaporanBulanan $laporan): void
    {
        $dk = $laporan->dataKapal;

        if (! $dk || ! $dk->hasKontrak()) {
            $processor->setValue('tabel_waktu', 'Kontrak (tanggal mulai & durasi) belum diatur pada Data Kapal.');

            return;
        }

        $jangka = (int) $dk->durasi_kontrak_hari;
        $selesai = $laporan->periode_selesai ?? $laporan->tanggal_laporan;
        $realisasi = $selesai
            ? max(0, $dk->tanggal_mulai_kontrak->diffInDays($selesai) + 1)
            : 0;
        $realisasi = min($realisasi, $jangka);
        $sisa = max(0, $jangka - $realisasi);
        $pctWaktu = $jangka > 0 ? round($realisasi / $jangka * 100, 2) : 0;

        WordStyleService::setComplexBlock($processor, 'tabel_waktu', $this->labelValueTable([
            'Jangka waktu pelaksanaan pembangunan' => $jangka.' Hari Kalender',
            'Realisasi pekerjaan pembangunan' => $realisasi.' Hari Kalender',
            'Sisa waktu pembangunan' => $sisa.' Hari Kalender',
            'Progress waktu pelaksanaan pembangunan' => number_format($pctWaktu, 2, ',', '.').' %',
        ]));
    }

    // ──────────────────────────────────────────────────────────────────
    // BAB 2.6 — PROGRESS MINGGUAN per cutoff (realisasi | rencana | deviasi)
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

        $mingguList = collect($this->periodeService->getMingguList($dk))->keyBy('minggu_ke');
        $history = collect($this->kurvaSService->getProgressHistory($dk))
            ->filter(fn ($e) => $e['minggu_ke'] >= $bulan['minggu_dari'] && $e['minggu_ke'] <= $bulan['minggu_sampai'])
            ->values();

        if ($history->isEmpty()) {
            $processor->setValue('tabel_progress_mingguan', 'Belum ada progress mingguan pada bulan ini.');

            return;
        }

        $t = $this->newTable();
        $this->headerRow($t, [3826, 1800, 1800, 1600],
            ['Jangka Waktu Mingguan', 'Progres Fisik Mingguan', 'Rencana (Akum.)', 'Deviasi (Akum.)']);

        foreach ($history as $e) {
            $minggu = $mingguList->get($e['minggu_ke']);
            $label = $minggu ? 'S.d. '.$minggu['selesai']->translatedFormat('d F Y') : 'Minggu '.$e['minggu_ke'];
            $t->addRow();
            $t->addCell(3826, $this->bodyCell())->addText($label, $this->smallFont());
            $t->addCell(1800, $this->bodyCell())->addText($this->pct($e['week_actual'] ?? 0), $this->smallFont(), $this->center());
            $t->addCell(1800, $this->bodyCell())->addText($this->pct($e['cumulative_plan'] ?? 0), $this->smallFont(), $this->center());
            $t->addCell(1600, $this->bodyCell())->addText($this->pct($e['cumulative_deviation'] ?? 0, true), $this->smallFont(), $this->center());
        }

        WordStyleService::setComplexBlock($processor, 'tabel_progress_mingguan', $t);
    }

    // ──────────────────────────────────────────────────────────────────
    // BAB 2.7 — PENGUJIAN
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
    // BAB 3.1 — RAPAT dalam periode bulan
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
        $this->headerRow($t, [1600, 1800, 2826, 2800], ['Tanggal', 'Jenis', 'Lokasi', 'Topik / Hasil']);
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
    // BAB 3.2 — PROBLEM & REKOMENDASI (tarikan otomatis rekap NC periode:
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
            ['No', 'Nomor', 'Tanggal', 'Problem', 'Rekomendasi', 'PIC', 'Status']);
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
    // BAB 4.1 — DOKUMEN EKSTERNAL
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
    // BAB 4.2 — LAMPIRAN LAPORAN MINGGUAN via w:altChunk
    // ──────────────────────────────────────────────────────────────────
    /**
     * Pakai docx mingguan yang sudah ada (doc_path); generate dulu via job
     * yang sama dengan halaman mingguan bila belum ada / filenya hilang.
     *
     * @return string[] path absolut docx mingguan, urut minggu_ke
     */
    private function resolveMingguanDocs(LaporanBulanan $laporan): array
    {
        $paths = [];

        foreach ($this->bulananService->getMingguanBulan($laporan) as $mingguan) {
            $harianCount = (int) $mingguan->laporan_harian_count;
            $path = $this->mingguanDocPath($mingguan);
            if (! $path || $this->chunkService->count($path) !== $harianCount
                || ! app(NcAttachmentWordService::class)->isCurrent($path, $this->ncService->rekapMingguan($mingguan->data_kapal_id, $mingguan->periode_mulai, $mingguan->periode_selesai))) {
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
            if (! $path || $this->chunkService->count($path) !== $harianCount
                || ! app(NcAttachmentWordService::class)->isCurrent($path, $this->ncService->rekapMingguan($mingguan->data_kapal_id, $mingguan->periode_mulai, $mingguan->periode_selesai))) {
                throw new \RuntimeException('Lampiran laporan harian pada dokumen mingguan belum lengkap.');
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

        $path = storage_path('app/'.$mingguan->doc_path);

        return file_exists($path) ? $path : null;
    }

    /**
     * Ganti paragraf ${lampiran_mingguan} dengan w:altChunk per docx mingguan.
     * Word mengimpor isi tiap chunk saat dokumen dibuka (tampilan identik).
     *
     * @param  string[]  $chunks
     */
    private function embedMingguanDocs(string $docxPath, array $chunks): void
    {
        $zip = new \ZipArchive;
        if ($zip->open($docxPath) !== true) {
            throw new \RuntimeException('Gagal membuka dokumen bulanan untuk menyisipkan laporan mingguan.');
        }

        $document = $zip->getFromName('word/document.xml');
        $rels = $zip->getFromName('word/_rels/document.xml.rels');
        $types = $zip->getFromName('[Content_Types].xml');

        $pageBreak = '<w:p><w:r><w:br w:type="page"/></w:r></w:p>';

        // Halaman sampul "LAPORAN MINGGUAN" terpusat dua sumbu (pola harian).
        $body = $pageBreak.'<w:tbl><w:tblPr><w:tblW w:w="5000" w:type="pct"/>'
            .'<w:tblBorders><w:top w:val="nil"/><w:left w:val="nil"/><w:bottom w:val="nil"/>'
            .'<w:right w:val="nil"/><w:insideH w:val="nil"/><w:insideV w:val="nil"/></w:tblBorders></w:tblPr>'
            .'<w:tblGrid><w:gridCol w:w="9026"/></w:tblGrid>'
            .'<w:tr w:rsidR="00C0FFEE"><w:trPr><w:trHeight w:val="13400" w:hRule="exact"/><w:cantSplit/></w:trPr>'
            .'<w:tc><w:tcPr><w:tcW w:w="5000" w:type="pct"/><w:vAlign w:val="center"/></w:tcPr>'
            .'<w:p><w:pPr><w:jc w:val="center"/></w:pPr>'
            .'<w:r><w:rPr><w:b/><w:color w:val="'.self::NAVY.'"/><w:sz w:val="48"/><w:szCs w:val="48"/></w:rPr>'
            .'<w:t>LAPORAN MINGGUAN</w:t></w:r></w:p></w:tc></w:tr></w:tbl>';
        $n = 0;
        try {
            foreach ($chunks as $chunk) {
                foreach ($this->chunkService->flatten(file_get_contents($chunk)) as $content) {
                    $n++;
                    $id = 'rIdAltChunk'.$n;
                    $zip->addFromString("word/afchunk{$n}.docx", $content);
                    $rels = str_replace('</Relationships>',
                        '<Relationship Id="'.$id.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/aFChunk" Target="afchunk'.$n.'.docx"/></Relationships>', $rels);
                    $body .= $pageBreak.'<w:altChunk r:id="'.$id.'"/>';
                }
            }
        } catch (\Throwable $e) {
            $zip->close();
            throw $e;
        }

        if ($chunks && ! str_contains($types, 'Extension="docx"')) {
            $types = str_replace('</Types>',
                '<Default Extension="docx" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>', $types);
        }

        $replacement = $chunks
            ? $body
            : '<w:p><w:r><w:rPr><w:i/><w:color w:val="7F7F7F"/></w:rPr><w:t>Tidak ada laporan mingguan pada periode ini.</w:t></w:r></w:p>';
        $document = preg_replace('/<w:p[ >](?:(?!<\/w:p>).)*?\$\{lampiran_mingguan\}.*?<\/w:p>/s', $replacement, $document, 1);

        $zip->addFromString('word/document.xml', $document);
        $zip->addFromString('word/_rels/document.xml.rels', $rels);
        $zip->addFromString('[Content_Types].xml', $types);
        $zip->close();
    }

    // ──────────────────────────────────────────────────────────────────
    // TABLE HELPERS
    // ──────────────────────────────────────────────────────────────────
    private function newTable(): Table
    {
        $table = new Table([
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

        return $table;
    }

    /**
     * Tabel label | : | nilai (pola blok info laporan — tanpa header).
     *
     * @param  array<string, string>  $rows
     */
    private function labelValueTable(array $rows): Table
    {
        $t = $this->newTable();
        foreach ($rows as $label => $value) {
            $t->addRow();
            $t->addCell(3200, ['bgColor' => self::LIGHT, 'valign' => 'center'])
                ->addText($label, ['bold' => true, 'size' => 9, 'color' => self::NAVY]);
            $t->addCell(300, $this->bodyCell())->addText(':', $this->smallFont(), $this->center());
            $t->addCell(5526, $this->bodyCell())->addText($value !== '' ? $value : '-', $this->smallFont());
        }

        return $t;
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
