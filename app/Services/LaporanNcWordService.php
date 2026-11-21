<?php

namespace App\Services;

use App\Enums\NcFileKategori;
use App\Enums\NcFormJenis;
use App\Models\LaporanNc;
use App\Models\LaporanNcForm;
use App\Models\LaporanNcFoto;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\TemplateProcessor;

/**
 * Dokumen Word Form NC (Non Conformance Report).
 *
 * Placeholder template (default: php build_laporan_nc_template.php; template
 * custom per Data Kapal wajib memakai placeholder yang sama):
 * - ${nama_proyek}, ${nomor_ncr}, ${judul_form}
 * - baris tabel NC (cloneRow): ${nc_no} (diisi nomor NC, mis. NC-001),
 *   ${nc_masalah}, ${nc_tanggal}, ${nc_pic}, ${nc_status}, ${nc_selesai}, ${nc_tindakan}
 * - blok lampiran ${blok_lampiran}…${/blok_lampiran} dengan baris (cloneRow)
 *   ${foto_no}, ${foto_gambar}, ${foto_keterangan} — blok dihapus bila tanpa foto.
 */
class LaporanNcWordService
{
    private const TEMPLATE_PATH = 'templates/laporan-nc/template-laporan-nc.docx';

    private const FOTO_SIZE = '6cm';

    public function __construct(
        private WordImageService $imageService,
    ) {}

    /**
     * Generate dokumen ke file sementara; pemanggil bertanggung jawab menghapus
     * (download memakai deleteFileAfterSend).
     */
    public function generate(LaporanNcForm $form): string
    {
        $form->loadMissing([
            'dataKapal',
            'ncs' => fn ($q) => $q->orderBy('laporan_nc.id'),
            'ncs.laporanHarian:id,tanggal_laporan',
            'ncs.foto',
        ]);

        Settings::setOutputEscapingEnabled(true);
        $processor = new TemplateProcessor($this->resolveTemplate($form));

        $processor->setValues([
            'nama_proyek' => $form->dataKapal?->deskripsi ?: ($form->dataKapal?->nama ?? '-'),
            'nomor_ncr' => $form->nomor,
            'judul_form' => $form->jenis->judulDokumen(),
        ]);

        $attachmentNcs = $form->ncs->map(fn (LaporanNc $nc) => (clone $nc)->setRelation(
            'foto',
            $nc->foto->filter(fn ($file) => $form->jenis !== NcFormJenis::Penutupan || $file->kategori === NcFileKategori::After)->values(),
        ));
        $this->fillNcTable($processor, $form);
        $this->fillLampiran($processor, $attachmentNcs);

        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.uniqid('ncform_', true).'.docx';
        $processor->saveAs($path);
        try {
            app(WordAttachmentService::class)->append($path, $attachmentNcs);
        } catch (\Throwable $e) {
            @unlink($path);
            throw $e;
        }

        return $path;
    }

    private function resolveTemplate(LaporanNcForm $form): string
    {
        $custom = $form->dataKapal?->getTemplateFullPath('nc');
        if ($custom && file_exists($custom)) {
            return $custom;
        }

        $files = app(FilePathService::class);
        if (! $files->exists(self::TEMPLATE_PATH)) {
            throw new \RuntimeException(
                'Template Form NC tidak ditemukan. Upload template pada master Data Kapal atau jalankan php build_laporan_nc_template.php'
            );
        }

        return $files->localPath(self::TEMPLATE_PATH);
    }

    private function fillNcTable(TemplateProcessor $processor, LaporanNcForm $form): void
    {
        $ncs = $form->ncs->values();
        $status = $form->jenis->statusDokumen()->label();
        $selesai = $form->jenis === NcFormJenis::Penutupan ? $form->tanggal?->format('d/m/Y') : null;

        $processor->cloneRow('nc_no', max(1, $ncs->count()));

        if ($ncs->isEmpty()) {
            foreach (['nc_no', 'nc_masalah', 'nc_tanggal', 'nc_pic', 'nc_status', 'nc_selesai', 'nc_tindakan'] as $macro) {
                $processor->setValue("{$macro}#1", '-');
            }

            return;
        }

        foreach ($ncs as $i => $nc) {
            $n = $i + 1;
            $processor->setValues([
                "nc_no#{$n}" => $nc->nomor ?? (string) $n,
                "nc_masalah#{$n}" => $nc->kendala ?? '-',
                "nc_tanggal#{$n}" => $nc->laporanHarian?->tanggal_laporan?->format('d/m/Y') ?? '-',
                "nc_pic#{$n}" => $nc->pic ?: '-',
                "nc_status#{$n}" => $status,
                "nc_selesai#{$n}" => $selesai ?? '-',
                "nc_tindakan#{$n}" => $nc->tindakan ?: '-',
            ]);
        }
    }

    /**
     * Lampiran foto bukti temuan per NC (hanya foto yang sudah selesai diproses).
     *
     * @param  Collection<int, LaporanNc>  $ncs
     */
    private function fillLampiran(TemplateProcessor $processor, Collection $ncs): void
    {
        $withFoto = $ncs->flatMap(fn (LaporanNc $nc) => $nc->foto
            ->filter(fn ($file) => $file->isFileCompleted() && $file->isImage())
            ->map(fn ($file) => ['nc' => $nc, 'file' => $file]))->values();

        try {
            if ($withFoto->isEmpty()) {
                $processor->cloneBlock('blok_lampiran', 0, true, true);

                return;
            }

            $processor->cloneBlock('blok_lampiran', 1, true, false);
            $processor->cloneRow('foto_no', $withFoto->count());

            foreach ($withFoto as $i => $entry) {
                $n = $i + 1;
                $processor->setValue("foto_no#{$n}", (string) $n);
                $processor->setValue("foto_keterangan#{$n}", $entry['nc']->nomor.' — '.$entry['file']->kategori->label().' — '.$entry['nc']->kendala);
                $this->setFoto($processor, "foto_gambar#{$n}", $entry['file']);
            }
        } catch (\Exception $e) {
            Log::warning('LaporanNcWordService: skip lampiran (template tanpa blok ${blok_lampiran}?)', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    private function setFoto(TemplateProcessor $processor, string $macro, LaporanNcFoto $file): void
    {
        $imagePath = $this->imageService->resolveLampiranPath($file->file_path);
        if (! $imagePath) {
            throw new \RuntimeException('Gambar lampiran NC tidak ditemukan.');
        }

        $finalPath = $this->imageService->prepareImageForWord($imagePath, 900, 900, 75);
        try {
            $processor->setImageValue($macro, ['path' => $finalPath, 'width' => self::FOTO_SIZE, 'height' => self::FOTO_SIZE, 'ratio' => true]);
        } catch (\Exception $e) {
            Log::error('LaporanNcWordService: gagal sisipkan foto', ['nc_id' => $file->laporan_nc_id, 'error' => $e->getMessage()]);
            throw $e;
        } finally {
            if ($finalPath !== $imagePath && file_exists($finalPath)) {
                @unlink($finalPath);
            }
        }
    }
}
