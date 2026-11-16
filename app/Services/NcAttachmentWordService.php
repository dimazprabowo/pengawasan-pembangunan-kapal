<?php

namespace App\Services;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

class NcAttachmentWordService
{
    private function signature(Collection $ncs): string
    {
        return hash('sha256', json_encode($ncs->flatMap(fn ($nc) => $nc->foto->map(fn ($file) => [
            $nc->id, $nc->nomor, $nc->kendala, $file->id, $file->kategori?->value,
            $file->file_name, $file->file_path, $file->file_status, $file->rendered_pages,
        ]))->values()->all(), JSON_THROW_ON_ERROR));
    }

    public function isCurrent(string $path, Collection $ncs): bool
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            return false;
        }
        try {
            $comment = $zip->getArchiveComment();

            return ! is_string($comment) || ! str_starts_with($comment, 'nc-attachments:')
                ? $ncs->every(fn ($nc) => $nc->foto->isEmpty())
                : hash_equals($this->signature($ncs), substr($comment, strlen('nc-attachments:')));
        } finally {
            $zip->close();
        }
    }

    public function append(string $path, Collection $ncs, bool $includePhotos = false): void
    {
        $images = [];
        foreach ($ncs as $nc) {
            foreach ($nc->foto as $file) {
                if (! $file->isFileCompleted()) {
                    throw new RuntimeException('Lampiran NC belum selesai diproses.');
                }
                if (! Storage::disk('local')->exists($file->file_path)) {
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
            if ($typeXpath->query('//*[local-name()="Default" and @Extension="jpg"]')->length === 0) {
                $type = $types->createElementNS($types->documentElement->namespaceURI, 'Default');
                $type->setAttribute('Extension', 'jpg');
                $type->setAttribute('ContentType', 'image/jpeg');
                $types->documentElement->appendChild($type);
            }
            $id = 1;
            foreach ($xpath->query('//*[local-name()="docPr"]/@id') as $attribute) {
                $id = max($id, (int) $attribute->value + 1);
            }
            $first = true;
            foreach ($images as [$image, $caption, $pdfPage]) {
                $fullPath = Storage::disk('local')->path($image);
                $size = @getimagesize($fullPath);
                if (! $size || $size[0] < 1 || $size[1] < 1) {
                    throw new RuntimeException('Gambar lampiran NC tidak dapat dibaca.');
                }
                $maxWidth = min($availableWidth, ($pdfPage ? 17 : 6) * 360000);
                $maxHeight = min($availableHeight, ($pdfPage ? 22 : 6) * 360000);
                $scale = min($maxWidth / $size[0], $maxHeight / $size[1]);
                $width = (int) round($size[0] * $scale);
                $height = (int) round($size[1] * $scale);
                $key = 'nc_'.bin2hex(random_bytes(12));
                $name = $key.'.jpg';
                $relation = $relations->createElementNS($relations->documentElement->namespaceURI, 'Relationship');
                $relation->setAttribute('Id', $key);
                $relation->setAttribute('Type', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/image');
                $relation->setAttribute('Target', 'media/'.$name);
                $relations->documentElement->appendChild($relation);
                $prepared = app(WordImageService::class)->prepareImageForWord($fullPath, $pdfPage ? 2400 : 900, $pdfPage ? 2400 : 900, 85);
                try {
                    if (getimagesize($prepared)[2] !== IMAGETYPE_JPEG || ! $zip->addFromString('word/media/'.$name, file_get_contents($prepared))) {
                        throw new RuntimeException('Gagal menyisipkan lampiran NC.');
                    }
                } finally {
                    if ($prepared !== $fullPath) {
                        @unlink($prepared);
                    }
                }
                $caption = htmlspecialchars($caption, ENT_XML1 | ENT_QUOTES, 'UTF-8');
                $pageBreak = $pdfPage || $first ? '<w:pageBreakBefore/>' : '';
                $first = false;
                $xml = '<w:p xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:pPr>'.$pageBreak.'<w:keepNext/></w:pPr><w:r><w:t xml:space="preserve">'.$caption.'</w:t></w:r></w:p>';
                $xml .= '<w:p xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:pPr><w:jc w:val="center"/></w:pPr><w:r><w:drawing><wp:inline xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing"><wp:extent cx="'.$width.'" cy="'.$height.'"/><wp:docPr id="'.$id.'" name="'.$key.'"/><a:graphic xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/picture"><pic:pic xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture"><pic:nvPicPr><pic:cNvPr id="0" name="'.$name.'"/><pic:cNvPicPr/></pic:nvPicPr><pic:blipFill><a:blip xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" r:embed="'.$key.'"/><a:stretch><a:fillRect/></a:stretch></pic:blipFill><pic:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="'.$width.'" cy="'.$height.'"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></pic:spPr></pic:pic></a:graphicData></a:graphic></wp:inline></w:drawing></w:r></w:p>';
                $fragment = $document->createDocumentFragment();
                if (! $fragment->appendXML($xml)) {
                    throw new RuntimeException('Struktur lampiran Word tidak valid.');
                }
                $body->insertBefore($fragment, $section);
                $id++;
            }
            $zip->setArchiveComment('nc-attachments:'.$this->signature($ncs));
            $zip->addFromString('word/document.xml', $document->saveXML());
            $zip->addFromString('word/_rels/document.xml.rels', $relations->saveXML());
            $zip->addFromString('[Content_Types].xml', $types->saveXML());
        } finally {
            $zip->close();
        }
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
