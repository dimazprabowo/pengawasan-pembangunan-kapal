<?php

namespace Tests\Feature;

use App\Livewire\LaporanMingguan\LaporanMingguanCreate;
use App\Livewire\LaporanMingguan\LaporanMingguanEdit;
use App\Models\JenisKapal;
use App\Models\KurvaSWorkGroup;
use App\Models\LaporanMingguan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Livewire\Mechanisms\HandleComponents\Checksum;
use Tests\TestCase;

class LaporanMingguanSnapshotTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function admin(): User
    {
        return User::firstOrFail();
    }

    private function jenisKapalWithKurvaS(): JenisKapal
    {
        return JenisKapal::whereHas('kurvaSWorkGroups')->firstOrFail();
    }

    /**
     * Simulasikan serialisasi ulang snapshot oleh JavaScript:
     * - JS mengurutkan key object integer-index secara ascending di depan
     *   key string (yang tetap pada insertion order).
     * - JS tidak membedakan int/float, sehingga 12.0 dikirim balik sebagai 12.
     * Keduanya mengubah hasil json_encode snapshot -> checksum mismatch
     * (CorruptComponentPayloadException) bila payload tidak distabilkan.
     */
    private function jsRoundTrip(mixed $value): mixed
    {
        if (! is_array($value)) {
            return is_float($value) && floor($value) === $value ? (int) $value : $value;
        }

        $value = array_map($this->jsRoundTrip(...), $value);

        uksort($value, function ($a, $b) {
            $aIndex = is_int($a) && $a >= 0 && $a < 4294967295;
            $bIndex = is_int($b) && $b >= 0 && $b < 4294967295;

            if ($aIndex && $bIndex) {
                return $a <=> $b;
            }

            if ($aIndex) {
                return -1;
            }

            if ($bIndex) {
                return 1;
            }

            return 0;
        });

        return $value;
    }

    private function assertSnapshotValidAfterJsRoundTrip(array $snapshot): void
    {
        Checksum::verify($this->jsRoundTrip($snapshot));

        $this->addToAssertionCount(1);
    }

    private function makeWorkGroupKeysNonAscending(JenisKapal $jenisKapal): void
    {
        $groups = KurvaSWorkGroup::where('jenis_kapal_id', $jenisKapal->id)
            ->orderBy('id')
            ->get();

        // Pindahkan group kedua ke urutan pertama agar map [id => pct]
        // memiliki key yang tidak ascending (id lebih besar di posisi awal).
        $groups[1]->update(['sort_order' => $groups[0]->sort_order - 1]);
    }

    public function test_create_progress_input_update_keeps_snapshot_valid(): void
    {
        $jenisKapal = $this->jenisKapalWithKurvaS();
        $workGroup = KurvaSWorkGroup::where('jenis_kapal_id', $jenisKapal->id)->firstOrFail();
        $this->makeWorkGroupKeysNonAscending($jenisKapal);

        $component = Livewire::actingAs($this->admin())
            ->test(LaporanMingguanCreate::class, ['jenisKapal' => $jenisKapal])
            ->assertSet('hasKurvaS', true);

        $this->assertSnapshotValidAfterJsRoundTrip($component->snapshot);

        $component
            ->set('minggu_ke', 1)
            ->set('progressPerGroup.'.$workGroup->id, '25')
            ->assertSet('progressPerGroup.'.$workGroup->id, '25');

        $this->assertSnapshotValidAfterJsRoundTrip($component->snapshot);
    }

    public function test_edit_progress_input_update_keeps_snapshot_valid(): void
    {
        $jenisKapal = $this->jenisKapalWithKurvaS();
        $laporan = LaporanMingguan::where('jenis_kapal_id', $jenisKapal->id)->firstOrFail();
        $workGroup = KurvaSWorkGroup::where('jenis_kapal_id', $jenisKapal->id)->firstOrFail();
        $this->makeWorkGroupKeysNonAscending($jenisKapal);

        $component = Livewire::actingAs($this->admin())
            ->test(LaporanMingguanEdit::class, ['jenisKapal' => $jenisKapal, 'laporanMingguan' => $laporan])
            ->assertSet('hasKurvaS', true);

        $this->assertSnapshotValidAfterJsRoundTrip($component->snapshot);

        $component
            ->set('progressPerGroup.'.$workGroup->id, '25')
            ->assertSet('progressPerGroup.'.$workGroup->id, '25');

        $this->assertSnapshotValidAfterJsRoundTrip($component->snapshot);
    }
}
