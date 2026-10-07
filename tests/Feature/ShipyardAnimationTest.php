<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class ShipyardAnimationTest extends TestCase
{
    public function test_component_accepts_custom_text_and_attributes(): void
    {
        $html = Blade::render(
            '<x-shipyard-animation :title="$title" :caption="$caption" class="max-w-md" data-testid="shipyard" />',
            ['title' => 'Galangan <utama>', 'caption' => 'Progres & inspeksi']
        );

        $this->assertStringContainsString('Galangan &lt;utama&gt;', $html);
        $this->assertStringContainsString('Progres &amp; inspeksi', $html);
        $this->assertStringContainsString('max-w-md', $html);
        $this->assertStringContainsString('data-testid="shipyard"', $html);
        $this->assertStringContainsString('prefers-reduced-motion: reduce', $html);
        $this->assertStringContainsString(':aria-pressed="paused"', $html);
    }

    public function test_multiple_instances_have_unique_svg_ids_and_shared_styles(): void
    {
        $html = Blade::render('<x-shipyard-animation /><x-shipyard-animation />');

        preg_match_all('/\bid="(shipyard-[^"]+)"/', $html, $ids);
        preg_match_all('/(?:href="#|url\(#)(shipyard-[^")]+)/', $html, $references);

        $this->assertNotEmpty($ids[1]);
        $this->assertCount(count($ids[1]), array_unique($ids[1]));
        $this->assertNotEmpty($references[1]);

        foreach ($references[1] as $reference) {
            $this->assertContains($reference, $ids[1]);
        }

        $this->assertSame(1, substr_count($html, '@keyframes simproIsoGantry'));
        $this->assertSame(2, substr_count($html, 'x-data="{ paused: false }"'));
        $this->assertSame(2, substr_count($html, 'simpro-gantry-rear'));
        $this->assertSame(2, substr_count($html, 'simpro-gantry-front'));
    }

    public function test_login_renders_the_reusable_animation(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('simpro-gantry-front', false)
            ->assertSee('simpro-beacon-alt', false)
            ->assertSee('Galangan digital');
    }
}
