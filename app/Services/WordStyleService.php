<?php

namespace App\Services;

use PhpOffice\PhpWord\Element\AbstractElement;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Style;
use PhpOffice\PhpWord\Style\Font;
use PhpOffice\PhpWord\Style\Paragraph;
use PhpOffice\PhpWord\TemplateProcessor;

final class WordStyleService
{
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
