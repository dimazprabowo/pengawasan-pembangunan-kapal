<?php

namespace Tests\Feature;

use App\Services\WordChunkService;
use App\Services\WordStyleService;
use PhpOffice\PhpWord\PhpWord;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ZipArchive;

class WordChunkServiceTest extends TestCase
{
    private array $paths = [];

    protected function tearDown(): void
    {
        foreach ($this->paths as $path) {
            unlink($path);
        }
        parent::tearDown();
    }

    private function path(string $content = ''): string
    {
        $path = tempnam(sys_get_temp_dir(), 'chunk-test-');
        $this->paths[] = $path;
        file_put_contents($path, $content);

        return $path;
    }

    private function package(string $text, array $chunks = [], ?string $after = null): string
    {
        $word = new PhpWord;
        $section = $word->addSection();
        $section->addText($text);
        $section->addHeader()->addText('Header '.$text);
        WordStyleService::prepare($word);
        $path = $this->path();
        $word->save($path, 'Word2007');
        $zip = new ZipArchive;
        $zip->open($path);
        $xml = $zip->getFromName('word/document.xml');
        $rels = $zip->getFromName('word/_rels/document.xml.rels');
        $body = '';
        foreach ($chunks as $i => $content) {
            $id = 'rIdNested'.$i;
            $zip->addFromString('word/nested'.$i.'.docx', $content);
            $rels = str_replace('</Relationships>', '<Relationship Id="'.$id.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/aFChunk" Target="nested'.$i.'.docx"/></Relationships>', $rels);
            $body .= '<w:altChunk r:id="'.$id.'"/>';
        }
        if ($after !== null) {
            $body .= '<w:p><w:r><w:t>'.$after.'</w:t></w:r></w:p>';
        }
        $xml = str_replace('<w:sectPr', $body.'<w:sectPr', $xml);
        $zip->addFromString('word/document.xml', $xml);
        $zip->addFromString('word/_rels/document.xml.rels', $rels);
        $zip->addFromString('word/media/resource.bin', 'Resource '.$text);
        $types = $zip->getFromName('[Content_Types].xml');
        foreach (array_keys($chunks) as $i) {
            $types = str_replace('</Types>', '<Override PartName="/word/nested'.$i.'.docx" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>', $types);
        }
        $types = str_replace('</Types>', '<Default Extension="docx" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/><Default Extension="bin" ContentType="application/octet-stream"/></Types>', $types);
        $zip->addFromString('[Content_Types].xml', $types);
        $zip->close();

        return file_get_contents($path);
    }

    #[Test]
    public function lampiran_bertingkat_diratakan_tanpa_mengubah_urutan_dan_resource(): void
    {
        $daily1 = $this->package('Harian 1');
        $daily2 = $this->package('Harian 2');
        $weekly = $this->package('Mingguan', [$daily1, $daily2], 'Penutup mingguan');
        $chunks = iterator_to_array((new WordChunkService)->flatten($weekly), false);
        $this->assertCount(4, $chunks);
        $this->assertSame($daily1, $chunks[1]);
        $this->assertSame($daily2, $chunks[2]);
        foreach ($chunks as $i => $content) {
            $zip = new ZipArchive;
            $zip->open($this->path($content));
            try {
                $xml = $zip->getFromName('word/document.xml');
                $document = new \DOMDocument;
                $this->assertTrue($document->loadXML($xml));
                $this->assertStringContainsString(['Mingguan', 'Harian 1', 'Harian 2', 'Penutup mingguan'][$i], $document->textContent);
                $this->assertStringNotContainsString('altChunk', $xml);
                $this->assertStringNotContainsString('/aFChunk', $zip->getFromName('word/_rels/document.xml.rels'));
                $this->assertNotFalse($zip->getFromName('word/styles.xml'));
                $this->assertNotFalse($zip->getFromName('word/header1.xml'));
                $this->assertNotFalse($zip->getFromName('word/media/resource.bin'));
                $this->assertFalse($zip->locateName('word/nested0.docx'));
                $this->assertStringNotContainsString('/word/nested', $zip->getFromName('[Content_Types].xml'));
            } finally {
                $zip->close();
            }
        }
    }

    #[Test]
    public function perataan_rekursif_mendukung_lebih_dari_dua_tingkat(): void
    {
        $daily = $this->package('Harian');
        $root = $this->package('Bulanan', [$this->package('Mingguan', [$daily])]);
        $chunks = iterator_to_array((new WordChunkService)->flatten($root), false);
        $this->assertCount(3, $chunks);
        $this->assertSame($daily, $chunks[2]);
    }

    #[Test]
    public function dokumen_tanpa_lampiran_tetap_identik(): void
    {
        $content = $this->package('Tanpa harian');
        $service = new WordChunkService;
        $this->assertSame(0, $service->count($this->path($content)));
        $this->assertSame([$content], iterator_to_array($service->flatten($content), false));
    }

    #[Test]
    public function relasi_lampiran_yang_hilang_tidak_dilewati_diam_diam(): void
    {
        $path = $this->path($this->package('Mingguan', [$this->package('Harian')]));
        $zip = new ZipArchive;
        $zip->open($path);
        $zip->deleteName('word/nested0.docx');
        $zip->close();
        $this->expectException(\RuntimeException::class);
        iterator_to_array((new WordChunkService)->flatten(file_get_contents($path)), false);
    }
}
