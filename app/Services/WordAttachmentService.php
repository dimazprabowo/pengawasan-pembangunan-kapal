<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

class WordAttachmentService
{
    private const EXTERNAL_ANCHOR = '${lampiran_external}';

    private const REPORT_PAGE_SIZE = 2400;

    private const REPORT_PAGE_QUALITY = 85;

    private const READY_IMAGE_VERSION = 1;

    /**
     * Signature ZIP archive comment: dokumen sudah melewati normalizePageBreaks.
     * Docx laporan lama (tanpa signature) dianggap stale oleh resolve*Docs dan
     * diregenerasi sekali, agar halaman lampiran tidak merender ulang halaman
     * kosong dari dokumen sumber yang dibuat sebelum perbaikan.
     */
    public const BREAK_SIGNATURE = 'pb-norm-6';

    public function isBreakNormalized(string $docxPath): bool
    {
        $zip = new ZipArchive;
        if ($zip->open($docxPath) !== true) {
            return false;
        }
        $current = $zip->getArchiveComment() === self::BREAK_SIGNATURE;
        $zip->close();

        return $current;
    }

    /**
     * Normalisasi paragraf kosong berisi <w:br w:type="page"/> — sumber halaman
     * kosong "terkadang": bila isi sebelumnya berakhir pas di batas bawah halaman,
     * paragraf itu sendiri terdorong ke halaman baru dan break-nya membuat satu
     * halaman lagi. Dipanggil pasca-save oleh generator harian/mingguan/bulanan:
     *  - diikuti paragraf → dipindah jadi w:pageBreakBefore pada paragraf
     *    berikutnya (mustahil menghasilkan halaman kosong);
     *  - diikuti elemen lain (tabel, altChunk, dst.) → paragraf wajib ada sebagai
     *    pemisah tetapi dikempeskan ke ±1pt agar tak mungkin terdorong sendirian;
     *  - di akhir dokumen (sebelum sectPr akhir) → dihapus.
     * Paragraf dengan teks, gambar, atau sectPr (batas section) tidak disentuh.
     */
    public function normalizePageBreaks(string $docxPath): void
    {
        $zip = new ZipArchive;
        if ($zip->open($docxPath) !== true) {
            return;
        }

        $xml = $zip->getFromName('word/document.xml');
        if ($xml === false) {
            $zip->close();

            return;
        }
        $zip->setArchiveComment(self::BREAK_SIGNATURE);

        $doc = new DOMDocument;
        $doc->preserveWhiteSpace = true;
        $doc->loadXML($xml);
        $xp = new DOMXPath($doc);
        $xp->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        $w = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

        $changed = false;
        $section = $xp->query('/w:document/w:body/w:sectPr')->item(0);
        $last = $section?->previousSibling;
        while ($last !== null) {
            if (! $last instanceof DOMElement) {
                $last = $last->previousSibling;

                continue;
            }
            if ($last->localName !== 'p' || trim($last->textContent) !== ''
                || $xp->query('.//w:drawing | .//w:pict | .//w:object | .//w:sectPr | .//w:fldChar | .//w:instrText | .//w:bookmarkStart | .//w:bookmarkEnd | .//w:sym | .//w:footnoteReference | .//w:endnoteReference | .//w:commentReference | .//*[local-name()="oMath"]', $last)->length > 0) {
                break;
            }
            $previous = $last->previousSibling;
            $last->parentNode->removeChild($last);
            $last = $previous;
            $changed = true;
        }
        if ($last instanceof DOMElement && $last->localName === 'tbl') {
            $paragraph = $doc->createElementNS($w, 'w:p');
            $this->shrinkBreakParagraph($doc, $xp, $paragraph);
            $section->parentNode->insertBefore($paragraph, $section);
            $changed = true;
        }

        // Batas section back-to-back: paragraf kosong pembawa sectPr tepat setelah
        // paragraf batas section lain menutup section yang isinya hanya dirinya
        // sendiri -> satu halaman kosong ber-footer induk di antara dua lampiran.
        do {
            $removed = false;
            foreach (iterator_to_array($xp->query('/w:document/w:body/w:p[w:pPr/w:sectPr]')) as $p) {
                if (trim($p->textContent) !== ''
                    || $xp->query('.//w:drawing | .//w:pict | .//w:br | .//w:t | .//w:instrText | .//w:fldChar', $p)->length > 0) {
                    continue;
                }
                $prev = $p->previousSibling;
                while ($prev !== null && ! $prev instanceof DOMElement) {
                    $prev = $prev->previousSibling;
                }
                if ($prev instanceof DOMElement && $prev->localName === 'p'
                    && $xp->query('w:pPr/w:sectPr', $prev)->length > 0) {
                    $p->parentNode->removeChild($p);
                    $removed = $changed = true;
                }
            }
        } while ($removed);

        foreach (iterator_to_array($xp->query('/w:document/w:body/w:p[.//w:br[@w:type="page"]]')) as $p) {
            if (trim($p->textContent) !== ''
                || $xp->query('.//w:drawing | .//w:pict | w:pPr/w:sectPr', $p)->length > 0) {
                continue;
            }

            $next = $p->nextSibling;
            while ($next !== null && (! $next instanceof DOMElement
                || in_array($next->localName, ['bookmarkStart', 'bookmarkEnd', 'proofErr', 'permStart', 'permEnd'], true))) {
                $next = $next->nextSibling;
            }

            if ($next instanceof DOMElement && $next->localName === 'p'
                && $xp->query('w:pPr/w:sectPr', $next)->length === 0) {
                $pPr = $xp->query('w:pPr', $next)->item(0);
                if (! $pPr instanceof DOMElement) {
                    $pPr = $doc->createElementNS($w, 'w:pPr');
                    $next->insertBefore($pPr, $next->firstChild);
                }
                if ($xp->query('w:pageBreakBefore', $pPr)->length === 0) {
                    $anchor = null;
                    foreach ($xp->query('w:pStyle | w:keepNext | w:keepLines', $pPr) as $el) {
                        $anchor = $el;
                    }
                    $pbb = $doc->createElementNS($w, 'w:pageBreakBefore');
                    if ($anchor instanceof DOMElement) {
                        $anchor->parentNode->insertBefore($pbb, $anchor->nextSibling);
                    } else {
                        $pPr->insertBefore($pbb, $pPr->firstChild);
                    }
                }
                $p->parentNode->removeChild($p);
                $changed = true;
            } elseif ($next instanceof DOMElement && $next->localName === 'sectPr') {
                $p->parentNode->removeChild($p);
                $changed = true;
            } else {
                $this->shrinkBreakParagraph($doc, $xp, $p);
                $changed = true;
            }
        }

        if ($changed) {
            $zip->addFromString('word/document.xml', $doc->saveXML());
        }
        $zip->close();
    }

    /**
     * Paragraf pemisah antar-elemen non-paragraf (tabel/altChunk) wajib ada;
     * kempeskan ke ±1pt agar tidak mungkin terdorong sendirian ke halaman baru.
     */
    private function shrinkBreakParagraph(DOMDocument $doc, DOMXPath $xp, DOMElement $p): void
    {
        $w = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
        $pPr = $xp->query('w:pPr', $p)->item(0);
        if (! $pPr instanceof DOMElement) {
            $pPr = $doc->createElementNS($w, 'w:pPr');
            $p->insertBefore($pPr, $p->firstChild);
        }
        foreach ($xp->query('w:spacing', $pPr) as $s) {
            $pPr->removeChild($s);
        }
        $spacing = $doc->createElementNS($w, 'w:spacing');
        $spacing->setAttributeNS($w, 'w:before', '0');
        $spacing->setAttributeNS($w, 'w:after', '0');
        $spacing->setAttributeNS($w, 'w:line', '20');
        $spacing->setAttributeNS($w, 'w:lineRule', 'exact');
        $pPr->appendChild($spacing);
        foreach ($xp->query('w:r', $p) as $r) {
            $rPr = $xp->query('w:rPr', $r)->item(0);
            if (! $rPr instanceof DOMElement) {
                $rPr = $doc->createElementNS($w, 'w:rPr');
                $r->insertBefore($rPr, $r->firstChild);
            }
            foreach (['w:sz', 'w:szCs'] as $tag) {
                foreach ($xp->query($tag, $rPr) as $s) {
                    $rPr->removeChild($s);
                }
                $rPr->appendChild($doc->createElementNS($w, $tag))->setAttributeNS($w, 'w:val', '2');
            }
        }
    }

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
    public function append(string $path, Collection $ncs, bool $includePhotos = false, string $coverTitle = 'LAMPIRAN NON-CONFORMANCE'): void
    {
        $images = [];
        foreach ($ncs->values() as $nc) {
            foreach ($nc->foto as $file) {
                if (! $file->isFileCompleted()) {
                    throw new RuntimeException('Lampiran NC belum selesai diproses.');
                }
                if (! Storage::disk()->exists($file->file_path)) {
                    throw new RuntimeException('File lampiran NC tidak ditemukan.');
                }
                if ($file->isImage() && $includePhotos) {
                    $images[] = [$file->file_path, '', false];
                } elseif ($file->isPdf()) {
                    if (empty($file->rendered_pages)) {
                        throw new RuntimeException('Halaman PDF NC belum tersedia.');
                    }
                    foreach ($file->rendered_pages as $image) {
                        $images[] = [$image, '', true];
                    }
                }
            }
        }

        $this->insertImages($path, [['cover' => $images ? $coverTitle : null, 'images' => $images]], null, true);
    }

    /**
     * Sisipkan dokumen eksternal pada paragraf marker ${lampiran_external}
     * (tepat di bawah tabel dokumen eksternal). Bila marker tidak ada di
     * template custom, konten disisipkan di akhir dokumen seperti lampiran NC.
     */
    public function embedExternals(string $path, Collection $externals, string $coverTitle = 'DOKUMEN PENDUKUNG'): void
    {
        $images = [];
        foreach ($externals->values() as $external) {
            if (! $external->hasFile()) {
                continue;
            }
            if (! $external->isFileCompleted()) {
                throw new RuntimeException('Dokumen pendukung belum selesai diproses.');
            }
            if (! Storage::disk()->exists($external->file_path)) {
                throw new RuntimeException('File dokumen pendukung tidak ditemukan.');
            }
            if ($external->isImage()) {
                $images[] = [$external->file_path, '', true];
            } elseif ($external->isPdf()) {
                if (empty($external->rendered_pages)) {
                    throw new RuntimeException('Halaman PDF dokumen pendukung belum tersedia.');
                }
                foreach ($external->rendered_pages as $image) {
                    $images[] = [$image, '', true];
                }
            }
        }

        $this->insertImages($path, [['cover' => $coverTitle, 'images' => $images]], self::EXTERNAL_ANCHOR, true);
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

        return array_map(fn (string $page) => [$this->preparedReportPage($page), '', true], $pages);
    }

    private function preparedReportPage(string $page): string
    {
        $files = app(FilePathService::class);
        $source = $files->localPath($page);
        $sourceHash = hash_file('sha256', $source);
        if ($sourceHash === false) {
            throw new RuntimeException('Gambar halaman laporan tidak dapat dibaca.');
        }
        $key = hash('sha256', implode(':', [self::READY_IMAGE_VERSION, self::REPORT_PAGE_SIZE, self::REPORT_PAGE_QUALITY, $sourceHash]));
        $path = dirname($page).'/word-ready/'.pathinfo($page, PATHINFO_FILENAME).'-'.$key.'.jpg';
        $checksumPath = $path.'.sha256';
        if ($files->exists($path) && $files->exists($checksumPath)
            && hash_file('sha256', $files->localPath($path)) === $files->disk()->get($checksumPath)) {
            return $path;
        }

        $prepared = app(WordImageService::class)->prepareImageForWord($source, self::REPORT_PAGE_SIZE, self::REPORT_PAGE_SIZE, self::REPORT_PAGE_QUALITY);
        $temporary = null;
        try {
            if ($prepared === $source || (@getimagesize($prepared)[2] ?? null) !== IMAGETYPE_JPEG) {
                throw new RuntimeException('Gambar halaman laporan gagal disiapkan.');
            }
            $checksum = hash_file('sha256', $prepared);
            if ($checksum === false) {
                throw new RuntimeException('Checksum gambar halaman laporan tidak dapat dibaca.');
            }
            if ($files->isLocalDisk()) {
                $destination = $files->disk()->path($path);
                File::ensureDirectoryExists(dirname($destination));
                $temporary = $destination.'.'.Str::uuid().'.tmp';
                if (! copy($prepared, $temporary) || ! rename($temporary, $destination)) {
                    throw new RuntimeException('Cache gambar halaman laporan gagal disimpan.');
                }
            } else {
                $files->put($path, (string) file_get_contents($prepared));
            }
            $files->put($checksumPath, $checksum);

            return $path;
        } finally {
            if ($prepared !== $source) {
                @unlink($prepared);
            }
            if ($temporary !== null && is_file($temporary)) {
                @unlink($temporary);
            }
        }
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

    public function embedDivider(string $path, string $anchorText, string $title): void
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new RuntimeException('Dokumen Word tidak dapat dibuka.');
        }
        try {
            $document = $this->xml($zip->getFromName('word/document.xml'));
            $xpath = new DOMXPath($document);
            $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
            $body = $xpath->query('/w:document/w:body')->item(0);
            $anchor = null;
            foreach ($xpath->query('w:p', $body) as $paragraph) {
                if (str_contains($paragraph->textContent, $anchorText)) {
                    $anchor = $paragraph;
                    break;
                }
            }
            if (! $anchor instanceof DOMElement) {
                return;
            }
            $normal = $xpath->query('following::w:sectPr[1]', $anchor)->item(0);
            if (! $normal instanceof DOMElement) {
                throw new RuntimeException('Pengaturan halaman pemisah tidak ditemukan.');
            }
            $relations = $this->xml($zip->getFromName('word/_rels/document.xml.rels'));
            $types = $this->xml($zip->getFromName('[Content_Types].xml'));
            $coverSection = $this->borderlessSection($normal, $zip, $relations, $types);
            $margins = $xpath->query('w:pgMar', $normal)->item(0);
            if ($margins instanceof DOMElement) {
                $coverSection->replaceChild($margins->cloneNode(true), $xpath->query('w:pgMar', $coverSection)->item(0));
            }
            $previous = $anchor->previousSibling;
            while ($previous !== null && ! $previous instanceof DOMElement) {
                $previous = $previous->previousSibling;
            }
            if (! $previous instanceof DOMElement || $xpath->query('w:pPr/w:sectPr', $previous)->length === 0) {
                $this->endSectionBefore($body, $anchor, $normal->cloneNode(true), $xpath);
            }
            $cover = $document->createDocumentFragment();
            if (! $cover->appendXML(self::coverPageXml($title))) {
                throw new RuntimeException('Struktur halaman pemisah tidak valid.');
            }
            $cover->removeChild($cover->firstChild);
            $body->insertBefore($cover, $anchor);
            $body->insertBefore($this->sectionBreak($document, $coverSection), $anchor);
            $body->removeChild($anchor);
            $zip->addFromString('word/document.xml', $document->saveXML());
            $zip->addFromString('word/_rels/document.xml.rels', $relations->saveXML());
            $zip->addFromString('[Content_Types].xml', $types->saveXML());
        } finally {
            $zip->close();
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
        $this->insertImages($path, [['cover' => $coverTitle, 'images' => $images]], $anchorText, true);
    }

    /**
     * Seperti embedReportPages untuk beberapa dokumen sumber: semua halaman
     * berada di belakang SATU halaman pembatas kategori ($coverTitle, ditentukan
     * caller agar sesuai penomoran daftar lampiran di atasnya).
     *
     * @param  array<array<array{0: string, 1: string, 2: bool}>>  $groups  daftar gambar per dokumen
     */
    public function embedGroupedReportPages(string $path, array $groups, string $anchorText, string $coverTitle): void
    {
        $images = [];
        foreach ($groups as $group) {
            foreach ($group as $item) {
                $images[] = $item;
            }
        }
        $this->insertImages($path, [['cover' => $coverTitle, 'images' => $images]], $anchorText, true);
    }

    /**
     * Sisipkan gambar ke paket docx sebagai inline drawing.
     *
     * @param  array<array{cover: string|null, images: array<array{0: string, 1: string, 2: bool}>}>  $groups  grup lampiran; tiap grup diawali halaman pembatas 'cover' lalu gambar-gambarnya
     * @param  string|null  $anchorText  teks dalam paragraf marker yang digantikan gambar
     */
    private function insertImages(string $path, array $groups, ?string $anchorText = null, bool $borderless = false): void
    {
        // Halaman render (±2400x3400 px) dibaca & di-clone GD satu per satu saat
        // crop margin — ratusan halaman pada bulanan melampaui limit default.
        $memoryLimit = ini_parse_quantity(ini_get('memory_limit'));
        if ($memoryLimit > 0 && $memoryLimit < 512 * 1024 * 1024) {
            ini_set('memory_limit', '512M');
        }

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
            $groups = array_values(array_filter($groups, fn ($group) => ! empty($group['images'])));
            $images = [];
            foreach ($groups as $group) {
                foreach ($group['images'] as $item) {
                    $images[] = $item;
                }
            }
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

            $attachmentSection = null;
            $restoreSection = null;
            if ($borderless && $images) {
                $layout = $anchor instanceof DOMElement
                    ? $xpath->query('following::w:sectPr[1]', $anchor)->item(0)
                    : $section;
                if (! $layout instanceof DOMElement) {
                    throw new RuntimeException('Pengaturan halaman lampiran tidak ditemukan.');
                }
                $pageWidth = (int) $xpath->evaluate('string(w:pgSz/@w:w)', $layout) ?: 11906;
                $pageHeight = (int) $xpath->evaluate('string(w:pgSz/@w:h)', $layout) ?: 16838;
                $normalSection = $layout->cloneNode(true);
                $attachmentSection = $this->borderlessSection($normalSection, $zip, $relations, $types);
                $this->endSectionBefore($body, $insertBefore, $normalSection, $xpath);
                // Section halaman pembatas: header/footer kosong seperti lampiran,
                // tetapi margin normal agar judul tetap terpusat pada area teks.
                $coverSection = $attachmentSection->cloneNode(true);
                $coverMargins = $coverSection->getElementsByTagNameNS($coverSection->namespaceURI, 'pgMar')->item(0);
                $normalMargins = $normalSection->getElementsByTagNameNS($normalSection->namespaceURI, 'pgMar')->item(0);
                if ($normalMargins instanceof DOMElement) {
                    $coverSection->replaceChild($normalMargins->cloneNode(true), $coverMargins);
                }
                $restoreSection = $layout;
                $availableWidth = $pageWidth * 635;
                $availableHeight = max(1, $pageHeight - 40) * 635;
            }

            $first = true;
            $wordImage = app(WordImageService::class);
            $bounds = $borderless ? [] : $this->sourceBounds($images, $wordImage);
            foreach ($groups as $groupIndex => $group) {
                if ($borderless) {
                    // Tutup section gambar grup sebelumnya sebelum pembatas berikut.
                    if ($groupIndex > 0) {
                        $this->endSectionBefore($body, $insertBefore, $attachmentSection->cloneNode(true), $xpath);
                    }
                    if (($group['cover'] ?? null) !== null) {
                        $cover = $document->createDocumentFragment();
                        if (! $cover->appendXML(self::coverPageXml($group['cover']))) {
                            throw new RuntimeException('Struktur lampiran Word tidak valid.');
                        }
                        $cover->removeChild($cover->firstChild);
                        $body->insertBefore($cover, $insertBefore);
                        $body->insertBefore($this->sectionBreak($document, $coverSection->cloneNode(true)), $insertBefore);
                    }
                }
                $groupFirst = true;
                foreach ($group['images'] as [$image, , $pdfPage]) {
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
                    $ready = $pdfPage && $size[2] === IMAGETYPE_JPEG
                        && str_starts_with($image, 'laporan/lampiran-pages/') && str_contains($image, '/word-ready/');
                    $prepared = $ready ? $fullPath : $wordImage->prepareImageForWord($fullPath, $pdfPage ? self::REPORT_PAGE_SIZE : 900, $pdfPage ? self::REPORT_PAGE_SIZE : 900, self::REPORT_PAGE_QUALITY);
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
                    $imageBreak = ($borderless ? ! $groupFirst : ($pdfPage || $first)) ? '<w:pageBreakBefore/>' : '';
                    $groupFirst = false;
                    $first = false;
                    $xml = '';
                    $xml .= '<w:p xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:pPr>'.$imageBreak.'<w:jc w:val="center"/></w:pPr><w:r><w:drawing><wp:inline xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing"><wp:extent cx="'.$width.'" cy="'.$height.'"/><wp:docPr id="'.$id.'" name="'.$key.'"/><a:graphic xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/picture"><pic:pic xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture"><pic:nvPicPr><pic:cNvPr id="0" name="'.$name.'"/><pic:cNvPicPr/></pic:nvPicPr><pic:blipFill><a:blip xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" r:embed="'.$key.'"/><a:stretch><a:fillRect/></a:stretch></pic:blipFill><pic:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="'.$width.'" cy="'.$height.'"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></pic:spPr></pic:pic></a:graphicData></a:graphic></wp:inline></w:drawing></w:r></w:p>';
                    if ($borderless) {
                        $xml = str_replace('<w:pPr>', '<w:pPr><w:spacing w:before="0" w:after="0" w:line="240" w:lineRule="auto"/><w:ind w:left="0" w:right="0" w:firstLine="0"/>', $xml);
                        $xml = str_replace('<w:r><w:drawing>', '<w:r><w:rPr><w:sz w:val="2"/></w:rPr><w:drawing>', $xml);
                    }
                    $fragment = $document->createDocumentFragment();
                    if (! $fragment->appendXML($xml)) {
                        throw new RuntimeException('Struktur lampiran Word tidak valid.');
                    }
                    $body->insertBefore($fragment, $insertBefore);
                    $id++;
                }
            }

            if ($attachmentSection instanceof DOMElement) {
                $following = $anchor?->nextSibling;
                while ($following !== null && ! $following instanceof DOMElement) {
                    $following = $following->nextSibling;
                }
                if ($restoreSection === $section && ($following === null || $following === $section)) {
                    $body->replaceChild($attachmentSection, $section);
                } else {
                    $this->endSectionBefore($body, $insertBefore, $attachmentSection, $xpath);
                    if ($following instanceof DOMElement && $following->localName === 'p'
                        && trim($following->textContent) === ''
                        && $restoreSection?->parentNode?->parentNode === $following
                        && $xpath->query('.//w:drawing | .//w:pict | .//w:br', $following)->length === 0) {
                        $body->removeChild($following);
                    }
                }
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
    private function endSectionBefore(DOMElement $body, \DOMNode $before, DOMElement $section, DOMXPath $xpath): void
    {
        $previous = $before->previousSibling;
        while ($previous !== null) {
            if (! $previous instanceof DOMElement) {
                $previous = $previous->previousSibling;

                continue;
            }
            if ($previous->localName !== 'p' || trim($previous->textContent) !== ''
                || $xpath->query('.//w:drawing | .//w:pict | .//w:sectPr', $previous)->length > 0
                || $xpath->query('.//w:br[@w:type="page"] | w:pPr/w:pageBreakBefore', $previous)->length === 0) {
                break;
            }
            $remove = $previous;
            $previous = $previous->previousSibling;
            $body->removeChild($remove);
        }
        $boundary = $this->sectionBreak($body->ownerDocument, $section);
        if ($previous instanceof DOMElement && $previous->localName === 'p'
            && ! str_contains($previous->textContent, '${')
            && $xpath->query('w:pPr/w:sectPr', $previous)->length === 0) {
            $properties = $xpath->query('w:pPr', $previous)->item(0);
            if (! $properties instanceof DOMElement) {
                $properties = $body->ownerDocument->createElementNS($section->namespaceURI, 'w:pPr');
                $previous->insertBefore($properties, $previous->firstChild);
            }
            $properties->appendChild($boundary->firstChild->lastChild->cloneNode(true));
        } else {
            $body->insertBefore($boundary, $before);
        }
    }

    private function sectionBreak(DOMDocument $document, DOMElement $section): DOMElement
    {
        $namespace = $section->namespaceURI;
        $paragraph = $document->createElementNS($namespace, 'w:p');
        $properties = $document->createElementNS($namespace, 'w:pPr');
        $spacing = $document->createElementNS($namespace, 'w:spacing');
        foreach (['before' => '0', 'after' => '0', 'line' => '1', 'lineRule' => 'exact'] as $name => $value) {
            $spacing->setAttributeNS($namespace, 'w:'.$name, $value);
        }
        $properties->appendChild($spacing);
        $properties->appendChild($section->cloneNode(true));
        $paragraph->appendChild($properties);

        return $paragraph;
    }

    private function borderlessSection(DOMElement $normal, ZipArchive $zip, DOMDocument $relations, DOMDocument $types): DOMElement
    {
        $section = $normal->cloneNode(true);
        $namespace = $section->namespaceURI;
        $document = $section->ownerDocument;
        foreach (iterator_to_array($section->childNodes) as $child) {
            if (in_array($child->localName, ['headerReference', 'footerReference', 'pgBorders', 'cols'], true)) {
                $section->removeChild($child);
            }
        }
        $margins = $section->getElementsByTagNameNS($namespace, 'pgMar')->item(0);
        if (! $margins instanceof DOMElement) {
            $margins = $section->appendChild($document->createElementNS($namespace, 'w:pgMar'));
        }
        foreach (['top', 'right', 'bottom', 'left', 'header', 'footer', 'gutter'] as $name) {
            $margins->setAttributeNS($namespace, 'w:'.$name, '0');
        }
        foreach (['footer' => 'ftr', 'header' => 'hdr'] as $kind => $root) {
            $id = 'attachment_'.bin2hex(random_bytes(8));
            $name = $kind.'_'.$id.'.xml';
            $zip->addFromString('word/'.$name, '<?xml version="1.0" encoding="UTF-8"?><w:'.$root.' xmlns:w="'.$namespace.'"><w:p/></w:'.$root.'>');
            $relation = $relations->createElementNS($relations->documentElement->namespaceURI, 'Relationship');
            $relation->setAttribute('Id', $id);
            $relation->setAttribute('Type', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/'.$kind);
            $relation->setAttribute('Target', $name);
            $relations->documentElement->appendChild($relation);
            $override = $types->createElementNS($types->documentElement->namespaceURI, 'Override');
            $override->setAttribute('PartName', '/word/'.$name);
            $override->setAttribute('ContentType', 'application/vnd.openxmlformats-officedocument.wordprocessingml.'.$kind.'+xml');
            $types->documentElement->appendChild($override);
            foreach (['default', 'first', 'even'] as $type) {
                $reference = $document->createElementNS($namespace, 'w:'.$kind.'Reference');
                $reference->setAttributeNS($namespace, 'w:type', $type);
                $reference->setAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships', 'r:id', $id);
                $section->insertBefore($reference, $section->firstChild);
            }
        }

        return $section;
    }

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
