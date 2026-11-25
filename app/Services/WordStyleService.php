<?php

namespace App\Services;

use PhpOffice\PhpWord\Element\AbstractElement;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\SimpleType\JcTable;
use PhpOffice\PhpWord\SimpleType\TblWidth;
use PhpOffice\PhpWord\Style;
use PhpOffice\PhpWord\Style\Font;
use PhpOffice\PhpWord\Style\Paragraph;
use PhpOffice\PhpWord\TemplateProcessor;

final class WordStyleService
{
    public static function hideCoverHeaderFooter(string $path): void
    {
        $zip = new \ZipArchive;
        if ($zip->open($path) !== true) {
            throw new \RuntimeException('Dokumen Word tidak dapat dibuka.');
        }
        try {
            $document = new \DOMDocument;
            if (! $document->loadXML((string) $zip->getFromName('word/document.xml'), LIBXML_NONET)) {
                throw new \RuntimeException('Struktur dokumen Word tidak valid.');
            }
            $xpath = new \DOMXPath($document);
            $namespace = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
            $xpath->registerNamespace('w', $namespace);
            $section = $xpath->query('//w:sectPr')->item(0);
            if (! $section instanceof \DOMElement) {
                throw new \RuntimeException('Pengaturan halaman Word tidak ditemukan.');
            }
            foreach (iterator_to_array($xpath->query('w:headerReference[@w:type="first"] | w:footerReference[@w:type="first"] | w:titlePg', $section)) as $node) {
                $section->removeChild($node);
            }
            $section->insertBefore($document->createElementNS($namespace, 'w:titlePg'), $xpath->query('w:textDirection | w:bidi | w:rtlGutter | w:docGrid | w:printerSettings | w:sectPrChange', $section)->item(0));
            $zip->addFromString('word/document.xml', $document->saveXML());
        } finally {
            $zip->close();
        }
    }

    public static function prepare(PhpWord|AbstractElement $document): void
    {
        if ($document instanceof PhpWord) {
            foreach (Style::getStyles() as $style) {
                self::prepareParagraph($style instanceof Font ? $style->getParagraph() : $style);
            }
            foreach ($document->getSections() as $section) {
                self::prepareElement($section);
            }
        } else {
            self::prepareElement($document);
        }
    }

    public static function setComplexBlock(TemplateProcessor $processor, string $placeholder, AbstractElement $block): void
    {
        self::prepare($block);
        $processor->setComplexBlock($placeholder, $block);
    }

    /**
     * Tabel label | : | nilai (pola blok info laporan — tanpa header).
     * Dipakai bersama laporan mingguan & bulanan.
     *
     * @param  array<string, string>  $rows
     */
    public static function labelValueTable(array $rows): Table
    {
        $t = new Table([
            'borderSize' => 4,
            'borderColor' => '8EAADB',
            'cellMarginLeft' => 80,
            'cellMarginRight' => 80,
            'cellMarginTop' => 40,
            'cellMarginBottom' => 40,
            'alignment' => JcTable::CENTER,
            'layout' => 'fixed',
            'unit' => TblWidth::TWIP,
            'width' => 9026,
        ]);
        foreach ($rows as $label => $value) {
            $t->addRow();
            $t->addCell(3200, ['bgColor' => 'D9E2F3', 'valign' => 'center'])
                ->addText($label, ['bold' => true, 'size' => 9, 'color' => '1F3864']);
            $t->addCell(300, ['valign' => 'center'])->addText(':', ['size' => 9], ['alignment' => Jc::CENTER]);
            $t->addCell(5526, ['valign' => 'center'])->addText($value !== '' ? $value : '-', ['size' => 9]);
        }

        return $t;
    }

    private static function prepareElement(AbstractElement $element): void
    {
        if (method_exists($element, 'getParagraphStyle')) {
            self::prepareParagraph($element->getParagraphStyle());
        }
        if (method_exists($element, 'getTextObject')) {
            self::prepareElement($element->getTextObject());
        }
        foreach (['getElements', 'getRows', 'getCells', 'getHeaders', 'getFooters'] as $getter) {
            if (method_exists($element, $getter)) {
                foreach ($element->{$getter}() as $child) {
                    self::prepareElement($child);
                }
            }
        }
    }

    private static function prepareParagraph(mixed $style): void
    {
        if ($style instanceof Paragraph && $style->getNumStyle() === null) {
            $style->setNumStyle('');
        }
    }
}
