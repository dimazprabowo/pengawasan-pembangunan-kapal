<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

class WordAttachmentService
{
    private const EXTERNAL_ANCHOR = '${lampiran_external}';

    /**
     * Halaman pembatas lampiran terpusat dua sumbu: page break + sel tabel
     * tanpa garis setinggi (hampir) satu halaman dengan vAlign=center
     * (vertikal) + jc=center (horizontal). Tinggi 13400 < area konten A4
     * (13958 twip) menyisakan ruang untuk paragraf page-break berikutnya
     * agar tidak tercipta halaman kosong ekstra. JANGAN diganti paragraf
     * `spacing before`: Word menekan spasi itu di awal halaman setelah
     * page break sehingga judul naik ke atas.
     */
    public static function coverPageXml(string $title, string $color = '1F3864'): string
    {
        $title = htmlspecialchars($title, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        $ns = 'xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"';

        return '<w:p '.$ns.'><w:r><w:br w:type="page"/></w:r></w:p>'
            .'<w:tbl '.$ns.'><w:tblPr><w:tblW w:w="5000" w:type="pct"/>'
            .'<w:tblBorders><w:top w:val="nil"/><w:left w:val="nil"/><w:bottom w:val="nil"/>'
            .'<w:right w:val="nil"/><w:insideH w:val="nil"/><w:insideV w:val="nil"/></w:tblBorders></w:tblPr>'
            .'<w:tblGrid><w:gridCol w:w="9026"/></w:tblGrid>'
            .'<w:tr w:rsidR="00C0FFEE"><w:trPr><w:trHeight w:val="13400" w:hRule="exact"/><w:cantSplit/></w:trPr>'
            .'<w:tc><w:tcPr><w:tcW w:w="5000" w:type="pct"/><w:vAlign w:val="center"/></w:tcPr>'
            .'<w:p><w:pPr><w:jc w:val="center"/></w:pPr>'
            .'<w:r><w:rPr><w:b/><w:color w:val="'.$color.'"/><w:sz w:val="48"/><w:szCs w:val="48"/></w:rPr>'
            .'<w:t>'.$title.'</w:t></w:r></w:p></w:tc></w:tr></w:tbl>';
    }

    /**
     * Sisipkan lampiran NC di akhir dokumen (sebelum sectPr).
     */
    public function append(string $path, Collection $ncs, bool $includePhotos = false): void
    {
        $images = [];
        foreach ($ncs as $nc) {
            foreach ($nc->foto as $file) {
                if (! $file->isFileCompleted()) {
                    throw new RuntimeException('Lampiran NC belum selesai diproses.');
                }
                if (! Storage::disk()->exists($file->file_path)) {
                    throw new RuntimeException('File lampiran NC tidak ditemukan.');
                }
                $caption = $nc->nomor.' — '.$file->kategori->label().' — '.$nc->kendala;
                if ($file->isImage() && $includePhotos) {
                    $images[] = [$file->file_path, $caption, false];
                } elseif ($file->isPdf()) {
                    if (empty($file->rendered_pages)) {
                        throw new RuntimeException('Halaman PDF NC belum tersedia.');
                    }
                    foreach ($file->rendered_pages as $page => $image) {
                        $images[] = [$image, $caption.' — '.$file->file_name.' — Halaman '.($page + 1), true];
                    }
                }
            }
        }

        $this->insertImages($path, $images, null, 'LAMPIRAN NON-CONFORMANCE');
    }

    /**
     * Sisipkan dokumen eksternal pada paragraf marker ${lampiran_external}
     * (tepat di bawah tabel dokumen eksternal). Bila marker tidak ada di
     * template custom, konten disisipkan di akhir dokumen seperti lampiran NC.
     */
    public function embedExternals(string $path, Collection $externals, string $coverTitle = 'DOKUMEN EKSTERNAL'): void
    {
        $images = [];
        foreach ($externals as $external) {
            if (! $external->hasFile()) {
                continue;
            }
            if (! $external->isFileCompleted()) {
                throw new RuntimeException('Dokumen eksternal belum selesai diproses.');
            }
            if (! Storage::disk()->exists($external->file_path)) {
                throw new RuntimeException('File dokumen eksternal tidak ditemukan.');
            }
            $caption = $external->judul ?: $external->file_name;
            if ($external->isImage()) {
                $images[] = [$external->file_path, $caption, true];
            } elseif ($external->isPdf()) {
                if (empty($external->rendered_pages)) {
                    throw new RuntimeException('Halaman PDF dokumen eksternal belum tersedia.');
                }
                foreach ($external->rendered_pages as $page => $image) {
                    $images[] = [$image, $caption.' — '.$external->file_name.' — Halaman '.($page + 1), true];
                }
            }
        }

        $this->insertImages($path, $images, self::EXTERNAL_ANCHOR, $coverTitle);
    }

    /**
     * Gambar halaman docx laporan tersimpan ($docPath = doc_path di disk)
     * untuk dilampirkan ke laporan induk. Dipakai rantai harian→mingguan→
     * bulanan agar isi lampiran tampil identik di semua viewer (bukan
     * altChunk) dan heading lampiran tidak bocor ke outline dokumen induk.
     *
     * Hasil render di-cache per doc_path (`laporan/lampiran-pages/{sha1}`):
     * selama file sumber tidak berubah, konversi docx→pdf→webp tidak diulang.
     * doc_path baru (generate ulang) = kunci baru; cache lama dibuang lewat
     * forgetPageImages() saat file docx dihapus.
     *
     * @return array<array{0: string, 1: string, 2: bool}> item insertImages [path, caption, fullPage]
     */
    public function reportPageImages(string $docPath): array
    {
        $files = app(FilePathService::class);
        $directory = self::pageCacheDirectory($docPath);
        $manifest = $directory.'/pages.json';

        $pages = $files->exists($manifest) ? json_decode((string) $files->disk()->get($manifest), true) : null;
        if (! is_array($pages) || $pages === [] || ! collect($pages)->every(fn ($page) => is_string($page) && $files->exists($page))) {
            $files->disk()->deleteDirectory($directory);
            $pages = $this->renderDocxPages($files->localPath($docPath), $directory);
            $files->put($manifest, (string) json_encode($pages));
        }

        return array_map(fn (string $page) => [$page, '', true], $pages);
    }

    /**
     * Buang cache gambar halaman milik $docPath (dipanggil saat docx dihapus).
     */
    public function forgetPageImages(string $docPath): void
    {
        app(FilePathService::class)->disk()->deleteDirectory(self::pageCacheDirectory($docPath));
    }

    private static function pageCacheDirectory(string $docPath): string
    {
        return 'laporan/lampiran-pages/'.sha1($docPath);
    }

    /**
     * @return string[] path storage gambar halaman (webp)
     */
    private function renderDocxPages(string $docxLocalPath, string $directory): array
    {
        $files = app(FilePathService::class);
        $pdfLocal = $files->tempPath('pdf');
        app(DocxToPdfService::class)->convert($docxLocalPath, $pdfLocal);

        $source = 'tmp/'.uniqid('lampiran-', true).'.pdf';
        $files->put($source, (string) file_get_contents($pdfLocal));
        try {
            return app(PdfPageService::class)->render($source, $directory);
        } finally {
            $files->delete($source);
            @unlink($pdfLocal);
        }
    }

    /**
     * Sisipkan halaman lampiran (hasil reportPageImages) menggantikan
     * paragraf marker $anchorText, diawali halaman pembatas $coverTitle.
     *
     * @param  array<array{0: string, 1: string, 2: bool}>  $images
     */
    public function embedReportPages(string $path, array $images, string $anchorText, string $coverTitle): void
    {
        $this->insertImages($path, $images, $anchorText, $coverTitle);
    }

    /**
     * Sisipkan gambar ke paket docx sebagai inline drawing.
     *
     * @param  array<array{0: string, 1: string, 2: bool}>  $images  [path storage, caption, fullPage]
     * @param  string|null  $anchorText  teks dalam paragraf marker yang digantikan gambar
     * @param  string|null  $coverTitle  judul halaman pembatas yang disisipkan sebelum gambar
     */
    private function insertImages(string $path, array $images, ?string $anchorText = null, ?string $coverTitle = null): void
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new RuntimeException('Dokumen Word tidak dapat dibuka.');
        }
        try {
            $document = $this->xml($zip->getFromName('word/document.xml'));
            $relations = $this->xml($zip->getFromName('word/_rels/document.xml.rels'));
            $types = $this->xml($zip->getFromName('[Content_Types].xml'));
            $xpath = new DOMXPath($document);
            $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
            $body = $xpath->query('/w:document/w:body')->item(0);
            $section = $xpath->query('w:sectPr', $body)->item(0);
            $pageWidth = (int) $xpath->evaluate('string(w:pgSz/@w:w)', $section) ?: 11906;
            $pageHeight = (int) $xpath->evaluate('string(w:pgSz/@w:h)', $section) ?: 16838;
            $left = (int) $xpath->evaluate('string(w:pgMar/@w:left)', $section) ?: 1440;
            $right = (int) $xpath->evaluate('string(w:pgMar/@w:right)', $section) ?: 1440;
            $top = (int) $xpath->evaluate('string(w:pgMar/@w:top)', $section) ?: 1440;
            $bottom = (int) $xpath->evaluate('string(w:pgMar/@w:bottom)', $section) ?: 1440;
            $availableWidth = max(1, $pageWidth - $left - $right) * 635;
            $availableHeight = max(1, $pageHeight - $top - $bottom - 720) * 635;
            $typeXpath = new DOMXPath($types);
            if ($images && $typeXpath->query('//*[local-name()="Default" and @Extension="jpg"]')->length === 0) {
                $type = $types->createElementNS($types->documentElement->namespaceURI, 'Default');
                $type->setAttribute('Extension', 'jpg');
                $type->setAttribute('ContentType', 'image/jpeg');
                $types->documentElement->appendChild($type);
            }
            $id = 1;
            foreach ($xpath->query('//*[local-name()="docPr"]/@id') as $attribute) {
                $id = max($id, (int) $attribute->value + 1);
            }

            $anchor = null;
            if ($anchorText !== null) {
                foreach ($xpath->query('w:p', $body) as $paragraph) {
                    if ($paragraph instanceof DOMElement && str_contains($paragraph->textContent, $anchorText)) {
                        $anchor = $paragraph;
                        break;
                    }
                }
            }
            $insertBefore = $anchor ?? $section;

            if ($coverTitle !== null && $images) {
                $cover = $document->createDocumentFragment();
                if (! $cover->appendXML(self::coverPageXml($coverTitle))) {
                    throw new RuntimeException('Struktur lampiran Word tidak valid.');
                }
                $body->insertBefore($cover, $insertBefore);
            }

            $first = true;
            $wordImage = app(WordImageService::class);
            $bounds = $this->sourceBounds($images, $wordImage);
            foreach ($images as [$image, $caption, $pdfPage]) {
                $fullPath = app(FilePathService::class)->localPath($image);
                $size = @getimagesize($fullPath);
                if (! $size || $size[0] < 1 || $size[1] < 1) {
                    throw new RuntimeException('Gambar lampiran tidak dapat dibaca.');
                }
                $key = 'att_'.bin2hex(random_bytes(12));
                $name = $key.'.jpg';
                $relation = $relations->createElementNS($relations->documentElement->namespaceURI, 'Relationship');
                $relation->setAttribute('Id', $key);
                $relation->setAttribute('Type', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/image');
                $relation->setAttribute('Target', 'media/'.$name);
                $relations->documentElement->appendChild($relation);
                $prepared = $wordImage->prepareImageForWord($fullPath, $pdfPage ? 2400 : 900, $pdfPage ? 2400 : 900, 85);
                $final = $prepared;
                try {
                    if ($pdfPage && isset($bounds[self::boundsKey($image)])) {
                        // Pangkas margin putih dengan kotak yang SAMA untuk semua
                        // halaman satu dokumen sumber, lalu ukur ulang hasil crop.
                        $trimmed = $wordImage->cropToBounds($prepared, $bounds[self::boundsKey($image)]);
                        if ($trimmed !== $prepared) {
                            $final = $trimmed;
                            $size = @getimagesize($final) ?: $size;
                        }
                    }
                    $maxWidth = $pdfPage ? $availableWidth : min($availableWidth, 6 * 360000);
                    $maxHeight = $pdfPage ? $availableHeight : min($availableHeight, 6 * 360000);
                    $scale = min($maxWidth / $size[0], $maxHeight / $size[1]);
                    $width = (int) round($size[0] * $scale);
                    $height = (int) round($size[1] * $scale);
                    if (getimagesize($final)[2] !== IMAGETYPE_JPEG || ! $zip->addFromString('word/media/'.$name, file_get_contents($final))) {
                        throw new RuntimeException('Gagal menyisipkan lampiran.');
                    }
                } finally {
                    foreach (array_unique([$prepared, $final]) as $tmp) {
                        if ($tmp !== $fullPath) {
                            @unlink($tmp);
                        }
                    }
                }
                $caption = htmlspecialchars($caption, ENT_XML1 | ENT_QUOTES, 'UTF-8');
                $pageBreak = $pdfPage || $first ? '<w:pageBreakBefore/>' : '';
                $first = false;
                $xml = $caption === '' ? ''
                    : '<w:p xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:pPr>'.$pageBreak.'<w:keepNext/></w:pPr><w:r><w:t xml:space="preserve">'.$caption.'</w:t></w:r></w:p>';
                $imageBreak = $caption === '' ? $pageBreak : '';
                $xml .= '<w:p xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:pPr>'.$imageBreak.'<w:jc w:val="center"/></w:pPr><w:r><w:drawing><wp:inline xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing"><wp:extent cx="'.$width.'" cy="'.$height.'"/><wp:docPr id="'.$id.'" name="'.$key.'"/><a:graphic xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/picture"><pic:pic xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture"><pic:nvPicPr><pic:cNvPr id="0" name="'.$name.'"/><pic:cNvPicPr/></pic:nvPicPr><pic:blipFill><a:blip xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" r:embed="'.$key.'"/><a:stretch><a:fillRect/></a:stretch></pic:blipFill><pic:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="'.$width.'" cy="'.$height.'"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></pic:spPr></pic:pic></a:graphicData></a:graphic></wp:inline></w:drawing></w:r></w:p>';
                $fragment = $document->createDocumentFragment();
                if (! $fragment->appendXML($xml)) {
                    throw new RuntimeException('Struktur lampiran Word tidak valid.');
                }
                $body->insertBefore($fragment, $insertBefore);
                $id++;
            }

            if ($anchor instanceof DOMElement) {
                $body->removeChild($anchor);
            }
            $zip->addFromString('word/document.xml', $document->saveXML());
            $zip->addFromString('word/_rels/document.xml.rels', $relations->saveXML());
            $zip->addFromString('[Content_Types].xml', $types->saveXML());
        } finally {
            $zip->close();
        }
    }

    /**
     * Union kotak konten seluruh halaman per dokumen sumber (halaman hasil
     * render satu PDF/DOCX berada di direktori yang sama). Crop seragam
     * menjaga posisi relatif tiap halaman — sampul tetap di tengah, header
     * dan footer sejajar antarhalaman.
     *
     * @param  array<array{0: string, 1: string, 2: bool}>  $images
     * @return array<string, array{0: float, 1: float, 2: float, 3: float}>
     */
    private function sourceBounds(array $images, WordImageService $wordImage): array
    {
        $bounds = [];
        foreach ($images as [$image, , $pdfPage]) {
            if (! $pdfPage) {
                continue;
            }
            $box = $wordImage->contentBounds(app(FilePathService::class)->localPath($image));
            if ($box === null) {
                continue;
            }
            $key = self::boundsKey($image);
            $bounds[$key] = isset($bounds[$key])
                ? [min($bounds[$key][0], $box[0]), min($bounds[$key][1], $box[1]), max($bounds[$key][2], $box[2]), max($bounds[$key][3], $box[3])]
                : $box;
        }

        return $bounds;
    }

    /**
     * Kunci grup crop: halaman render PDF (`{dir}/{uuid}/page-N.*`) per
     * direktori dokumen; gambar tunggal per file.
     */
    private static function boundsKey(string $image): string
    {
        return preg_match('/(^|\/)page-\d+\.\w+$/', $image) ? dirname($image) : $image;
    }

    private function xml(string|false $content): DOMDocument
    {
        $document = new DOMDocument;
        if ($content === false || ! $document->loadXML($content, LIBXML_NONET)) {
            throw new RuntimeException('Paket dokumen Word tidak valid.');
        }

        return $document;
    }
}
