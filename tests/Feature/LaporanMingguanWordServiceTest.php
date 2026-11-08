<?php

namespace Tests\Feature;

use App\Models\DataKapal;
use App\Models\LaporanHarian;
use App\Models\LaporanMingguan;
use App\Models\User;
use App\Services\LaporanMingguanWordService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\PhpWord;
use Tests\TestCase;

/**
 * Regresi justify: newline di ringkasan sebelumnya menjadi <w:br/> dalam satu
 * paragraf justify — baris sebelum break ikut di-stretch Word selebar margin
 * walaupun merupakan baris terakhir input. Sekarang tiap baris menjadi <w:p>
 * sendiri via cloneBlock ${blok_ringkasan}.
 */
class LaporanMingguanWordServiceTest extends TestCase
{
    use RefreshDatabase;

    /** @var string[] path absolut docx hasil generate untuk dibersihkan */
    private array $generated = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    protected function tearDown(): void
    {
        foreach ($this->generated as $path) {
            @unlink($path);
        }
        Storage::disk('local')->delete('templates/test-custom-mingguan.docx');
        parent::tearDown();
    }

    private function buatLaporan(?string $ringkasan, ?DataKapal $dataKapal = null): LaporanMingguan
    {
        return LaporanMingguan::create([
            'user_id' => User::firstOrFail()->id,
            'data_kapal_id' => ($dataKapal ?? DataKapal::firstOrFail())->id,
            'tanggal_laporan' => '2026-11-06',
            'periode_mulai' => '2026-11-02',
            'periode_selesai' => '2026-11-08',
            'minggu_ke' => 5,
            'ringkasan' => $ringkasan,
        ]);
    }

    private function documentXml(string $relativePath): string
    {
        $full = storage_path('app/'.$relativePath);
        $this->generated[] = $full;

        $zip = new \ZipArchive;
        $zip->open($full);
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        return $xml;
    }

    private function generate(LaporanMingguan $laporan): string
    {
        return $this->documentXml(app(LaporanMingguanWordService::class)->generate($laporan));
    }

    /** Satu <w:p> utuh yang memuat $teks (tidak menyeberang ke paragraf lain). */
    private function paragrafMemuat(string $xml, string $teks): ?string
    {
        $re = '/<w:p\b(?:(?!<\/w:p>).)*'.preg_quote($teks, '/').'(?:(?!<\/w:p>).)*<\/w:p>/s';

        return preg_match($re, $xml, $m) ? $m[0] : null;
    }

    public function test_ringkasan_multiline_menjadi_paragraf_terpisah(): void
    {
        $laporan = $this->buatLaporan(
            "Progress minggu ini berjalan sesuai rencana.\n\npenyelesaian interior dan eksterior kapal\n"
        );

        $xml = $this->generate($laporan);

        // 3 baris (termasuk baris kosong pemisah) → 3 paragraf justify terpisah
        $this->assertSame(3, substr_count($xml, '<w:jc w:val="both"/>'));

        $paragraf = $this->paragrafMemuat($xml, 'penyelesaian interior dan eksterior kapal');
        $this->assertNotNull($paragraf, 'Teks ringkasan tidak ditemukan di dokumen');
        $this->assertStringNotContainsString('<w:br/>', $paragraf);
        $this->assertStringNotContainsString('${ringkasan', $xml);
    }

    public function test_ringkasan_kosong_dirender_strip(): void
    {
        $laporan = $this->buatLaporan(null);

        $xml = $this->generate($laporan);

        $this->assertSame(1, substr_count($xml, '<w:jc w:val="both"/>'));
        $paragraf = $this->paragrafMemuat($xml, '<w:jc w:val="both"/>');
        $this->assertSame('-', trim(strip_tags($paragraf)));
    }

    public function test_sampul_laporan_harian_terpusat_dua_sumbu(): void
    {
        // Laporan harian dengan docx "jadi" — cukup file docx valid agar
        // tersisip sebagai altChunk (generate harian tidak diuji di sini)
        $harian = LaporanHarian::firstOrFail();
        $dir = storage_path('app/laporan/word');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $fake = $dir.'/fake-harian-test.docx';
        copy(storage_path('app/templates/laporan-mingguan/template-laporan-mingguan.docx'), $fake);
        $this->generated[] = $fake;
        $harian->update([
            'doc_path' => 'laporan/word/fake-harian-test.docx',
            'doc_name' => 'fake-harian-test.docx',
            'doc_status' => 'completed',
        ]);

        $laporan = $this->buatLaporan('Ringkasan uji.');
        $laporan->laporanHarian()->attach($harian->id);

        $xml = $this->generate($laporan);

        // Sampul = sel tabel setinggi halaman: vAlign center (vertikal) +
        // jc center (horizontal), bukan lagi trik spacing before
        $this->assertStringContainsString('LAPORAN HARIAN', $xml);
        $this->assertStringContainsString('<w:vAlign w:val="center"/>', $xml);
        $this->assertStringContainsString('<w:trHeight w:val="13400" w:hRule="exact"/>', $xml);
        $this->assertStringNotContainsString('w:before="5600"', $xml);
        $this->assertStringContainsString('<w:altChunk', $xml);
    }

    public function test_ringkasan_template_tanpa_blok_tetap_terisi(): void
    {
        // Template custom lama: ${ringkasan} polos tanpa ${blok_ringkasan}
        $phpWord = new PhpWord;
        $phpWord->addSection()->addText('${ringkasan}');
        $tmp = tempnam(sys_get_temp_dir(), 'tpl').'.docx';
        $phpWord->save($tmp, 'Word2007');
        Storage::disk('local')->put('templates/test-custom-mingguan.docx', file_get_contents($tmp));
        @unlink($tmp);

        $dataKapal = DataKapal::firstOrFail();
        $dataKapal->update(['template_path_mingguan' => 'templates/test-custom-mingguan.docx']);

        $laporan = $this->buatLaporan("penyelesaian interior dan eksterior kapal\n", $dataKapal);
        $xml = $this->generate($laporan);

        $this->assertStringContainsString('penyelesaian interior dan eksterior kapal', $xml);
        $this->assertStringNotContainsString('${ringkasan}', $xml);
    }
}
