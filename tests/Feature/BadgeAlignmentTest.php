<?php

namespace Tests\Feature;

use App\Enums\NcStatus;
use App\Models\LaporanNc;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BadgeAlignmentTest extends TestCase
{
    public static function badgeCases(): iterable
    {
        foreach (glob(dirname(__DIR__, 2).'/app/Enums/*.php') as $file) {
            $enum = 'App\\Enums\\'.basename($file, '.php');
            if (enum_exists($enum) && method_exists($enum, 'badgeClass')) {
                foreach ($enum::cases() as $case) {
                    yield $enum.'::'.$case->name => [$case];
                }
            }
        }
    }

    #[Test]
    #[DataProvider('badgeCases')]
    public function enum_badges_center_wrapped_text(\UnitEnum $case): void
    {
        $this->assertContains('text-center', explode(' ', $case->badgeClass()));
    }

    public static function monthlyNcHeaderStates(): iterable
    {
        yield 'belum memilih periode' => [0, false];
        yield 'periode tanpa NC' => [0, true];
        yield 'lima open dan satu closed' => [6, true];
    }

    #[Test]
    #[DataProvider('monthlyNcHeaderStates')]
    public function monthly_nc_header_stacks_description_and_wrapping_badges_on_mobile(int $count, bool $hasPeriod): void
    {
        $records = collect(range(1, $count))->take($count)->map(function ($index) use ($count) {
            return (new LaporanNc([
                'nomor' => 'NC-'.$index,
                'status' => $index === $count ? NcStatus::Closed : NcStatus::Open,
                'kendala' => 'Problem pengujian',
                'tindakan' => 'Lakukan pemeriksaan ulang',
            ]))->setRelation('laporanHarian', null);
        });
        $view = $this->view('livewire.laporan-bulanan.partials.repeaters', [
            'ncRekap' => $records,
            'periode_mulai' => $hasPeriod ? '2026-01-01' : null,
            'periode_selesai' => $hasPeriod ? '2026-01-31' : null,
        ]);
        $view->assertSee('Problem & Rekomendasi')->assertSee('Otomatis dari daftar Non-Conformance');
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.(string) $view, LIBXML_NONET);
        $xpath = new \DOMXPath($dom);
        $header = $xpath->query('//h4/parent::div/parent::div')->item(0);
        $this->assertNotNull($header);
        $this->assertStringContainsString('flex-col sm:flex-row', $header->getAttribute('class'));
        $description = $xpath->query('./div', $header)->item(0);
        $this->assertStringContainsString('w-full sm:w-auto sm:flex-1', $description->getAttribute('class'));
        $badges = $xpath->query('./div[2]', $header);
        if ($count > 0) {
            $this->assertSame(1, $badges->length);
            $this->assertStringContainsString('flex-wrap', $badges->item(0)->getAttribute('class'));
            $this->assertSame(3, $xpath->query('./span', $badges->item(0))->length);
            $view->assertSee('6 NC')->assertSee('5 Open')->assertSee('1 Closed');
        } else {
            $this->assertSame(0, $badges->length);
            $view->assertSee($hasPeriod ? 'Tidak ada NC pada periode ini' : 'terlebih dahulu untuk melihat rekap NC');
        }
    }

    public static function sectionHeaderStates(): iterable
    {
        yield 'belum diisi' => [true];
        yield 'sudah diisi' => [false];
    }

    #[Test]
    #[DataProvider('sectionHeaderStates')]
    public function shared_section_header_stacks_label_and_badge_on_mobile(bool $isEmpty): void
    {
        $view = $this->blade('<x-laporan.section-header label="Judul section laporan yang panjang" :is-empty="$isEmpty" :count="1234" />', compact('isEmpty'));
        $view->assertSee('Judul section laporan yang panjang')->assertSee($isEmpty ? 'Belum diisi' : '1234 data');
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.(string) $view, LIBXML_NONET);
        $root = $dom->getElementsByTagName('div')->item(0);
        $this->assertStringContainsString('flex-col sm:flex-row', $root->getAttribute('class'));
        $this->assertStringContainsString('items-start sm:items-center', $root->getAttribute('class'));
    }

    #[Test]
    public function blade_text_badges_center_wrapped_text(): void
    {
        $missing = [];
        foreach (app('files')->allFiles(resource_path('views')) as $file) {
            if (! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }
            preg_match_all('/<span\b[^>]*\bclass="([^"]*)"[^>]*>/', $file->getContents(), $matches);
            foreach ($matches[1] as $classes) {
                if (! preg_match('/\brounded(?:-[a-z0-9]+)?\b/', $classes)
                    || ! preg_match('/\btext-(?:xs|sm|\[(?:10|11)px\])/', $classes)
                    || ! preg_match('/\bp[xlr]-/', $classes)
                    || preg_match('/\bh-(?:\d|\[)/', $classes)) {
                    continue;
                }
                $tokens = preg_split('/\s+/', trim($classes));
                if ((! str_contains($classes, 'badgeClass(') && ! in_array('text-center', $tokens, true))
                    || ! array_intersect(['inline-block', 'inline-flex', 'block', 'flex', 'grid', 'inline-grid'], $tokens)) {
                    $missing[] = $file->getRelativePathname().': '.$classes;
                }
            }
        }
        $this->assertSame([], $missing, implode("\n", $missing));
    }
}
