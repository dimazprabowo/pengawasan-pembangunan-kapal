<?php

namespace App\Services;

use DOMDocument;
use DOMXPath;
use Generator;
use RuntimeException;
use ZipArchive;

class WordChunkService
{
    public function count(string $path): int
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            return -1;
        }

        try {
            $document = $this->document($zip->getFromName('word/document.xml'));

            return (new DOMXPath($document))->query('//*[local-name()="altChunk"]')->length;
        } finally {
            $zip->close();
        }
    }

    public function flatten(string $content, int $depth = 0): Generator
    {
        if ($depth > 8) {
            throw new RuntimeException('Lampiran Word melebihi batas kedalaman.');
        }

        $path = tempnam(sys_get_temp_dir(), 'word-chunk-');
        if ($path === false) {
            throw new RuntimeException('Gagal menyiapkan file sementara lampiran Word.');
        }

        $zip = new ZipArchive;
        try {
            file_put_contents($path, $content);
            if ($zip->open($path) !== true) {
                throw new RuntimeException('Paket lampiran Word tidak valid.');
            }

            try {
                $document = $this->document($zip->getFromName('word/document.xml'));
                $xpath = new DOMXPath($document);
                $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
                $chunks = $xpath->query('//w:altChunk');
                if ($chunks->length === 0) {
                    yield $content;

                    return;
                }

                $body = $xpath->query('/w:document/w:body')->item(0);
                if (! $body || $chunks->length !== $xpath->query('w:altChunk', $body)->length) {
                    throw new RuntimeException('Posisi lampiran Word bertingkat tidak didukung.');
                }

                $relationships = $this->document($zip->getFromName('word/_rels/document.xml.rels'));
                $types = $this->document($zip->getFromName('[Content_Types].xml'));
                $nested = [];
                foreach (iterator_to_array($relationships->documentElement->childNodes) as $relationship) {
                    if (! $relationship instanceof \DOMElement || ! str_ends_with($relationship->getAttribute('Type'), '/aFChunk')) {
                        continue;
                    }
                    if ($relationship->getAttribute('TargetMode') === 'External') {
                        throw new RuntimeException('Lampiran Word eksternal tidak didukung.');
                    }
                    $target = $relationship->getAttribute('Target');
                    $entry = str_starts_with($target, '/') ? ltrim($target, '/') : 'word/'.$target;
                    $bytes = $zip->getFromName($entry);
                    if ($bytes === false || ! str_ends_with(strtolower($target), '.docx')) {
                        throw new RuntimeException('Lampiran DOCX bertingkat tidak ditemukan atau tidak didukung.');
                    }
                    $nested[$relationship->getAttribute('Id')] = $bytes;
                    $zip->deleteName($entry);
                    foreach (iterator_to_array($types->documentElement->childNodes) as $type) {
                        if ($type instanceof \DOMElement && $type->getAttribute('PartName') === '/'.$entry) {
                            $types->documentElement->removeChild($type);
                        }
                    }
                    $relationships->documentElement->removeChild($relationship);
                }
                $zip->addFromString('[Content_Types].xml', $types->saveXML());
                $zip->addFromString('word/_rels/document.xml.rels', $relationships->saveXML());
                $section = $xpath->query('w:sectPr', $body)->item(0);
                $nodes = [];
                foreach (iterator_to_array($body->childNodes) as $node) {
                    if ($node === $section) {
                        continue;
                    }
                    if ($node->localName !== 'altChunk') {
                        $nodes[] = $node;

                        continue;
                    }
                    while ($nodes && $xpath->evaluate('boolean(self::w:p[w:r/w:br[@w:type="page"] and not(.//w:t) and not(.//w:drawing)])', $nodes[array_key_last($nodes)])) {
                        array_pop($nodes);
                    }
                    if ($nodes) {
                        yield $this->segment($zip, $path, $document, $nodes, $section);
                        $nodes = [];
                    }
                    $id = $node->getAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships', 'id');
                    if (! isset($nested[$id])) {
                        throw new RuntimeException('Relasi lampiran Word tidak ditemukan.');
                    }
                    yield from $this->flatten($nested[$id], $depth + 1);
                }
                if ($nodes) {
                    yield $this->segment($zip, $path, $document, $nodes, $section);
                }
            } finally {
                $zip->close();
            }
        } finally {
            unlink($path);
        }
    }

    private function segment(ZipArchive $zip, string $path, DOMDocument $source, array $nodes, ?\DOMNode $section): string
    {
        $document = clone $source;
        $body = $document->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'body')->item(0);
        while ($body->firstChild) {
            $body->removeChild($body->firstChild);
        }
        foreach ($nodes as $node) {
            $body->appendChild($document->importNode($node, true));
        }
        if ($section) {
            $body->appendChild($document->importNode($section, true));
        }
        $zip->addFromString('word/document.xml', $document->saveXML());
        $zip->close();
        $content = file_get_contents($path);
        if ($zip->open($path) !== true) {
            throw new RuntimeException('Gagal membuka kembali segmen lampiran Word.');
        }

        return $content;
    }

    private function document(string|false $xml): DOMDocument
    {
        $document = new DOMDocument;
        if ($xml === false || ! $document->loadXML($xml, LIBXML_NONET)) {
            throw new RuntimeException('XML lampiran Word tidak valid.');
        }

        return $document;
    }
}
