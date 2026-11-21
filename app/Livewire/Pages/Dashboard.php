<?php

namespace App\Livewire\Pages;

use App\Livewire\Traits\HasDataKapalFilter;
use App\Models\DataKapal;
use App\Models\LaporanHarian;
use App\Models\Owner;
use App\Models\User;
use App\Services\DashboardService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Spatie\Permission\Models\Role;

#[Layout('layouts.app', ['title' => 'Dashboard'])]
#[Title('Dashboard')]
class Dashboard extends Component
{
    use AuthorizesRequests, HasDataKapalFilter;

    private const LAPORAN_TIPES = ['harian', 'mingguan', 'bulanan'];

    /** Kode galangan terpilih di peta (persist di URL agar refresh tetap). */
    #[Url(as: 'galangan')]
    public ?string $galanganKode = null;

    /** Route key terenkripsi data kapal terpilih di panel Kurva-S (persist di URL). */
    #[Url(as: 'kapal')]
    public ?string $kurvaSkapalKey = null;

    /** Mode grafik Kurva-S dashboard: 'mingguan' | 'bulanan'. */
    public string $kurvaSMode = 'mingguan';

    public function mount(): void
    {
        if (! Gate::allows('viewStats')) {
            $this->galanganKode = null;
            $this->kurvaSkapalKey = null;
        }
    }

    public function selectGalangan(?string $kode): void
    {
        $this->authorize('viewStats');

        $this->galanganKode = $kode ?: null;
    }

    public function selectKurvaSkapal(?string $key): void
    {
        $this->authorize('viewStats');
        $this->kurvaSkapalKey = $key ?: null;
        $this->dispatchKurvaSUpdate();
    }

    public function setKurvaSMode(string $mode): void
    {
        $this->authorize('viewStats');
        abort_unless(in_array($mode, ['mingguan', 'bulanan'], true), 404);
        $this->kurvaSMode = $mode;
        $this->dispatchKurvaSUpdate();
    }

    /**
     * Kirim data grafik terbaru ke chart (canvas di-wire:ignore — update
     * lewat event kurva-s-updated, pola yang sama dengan form mingguan).
     */
    private function dispatchKurvaSUpdate(): void
    {
        $service = app(DashboardService::class);
        $selected = $this->selectedKurvaSkapal($service);
        if (! $selected) {
            return;
        }

        $this->dispatch('kurva-s-updated', chartData: $service->kurvaSChart($selected, $this->kurvaSMode));
    }

    /**
     * Data kapal terpilih dari daftar yang SUDAH ter-scope akses user.
     */
    private function selectedKurvaSkapal(DashboardService $service): ?DataKapal
    {
        return $this->findKurvaSkapal($service->kurvaSKapalRows($this->accessibleKapalIds()));
    }

    /**
     * `getRouteKey()` menghasilkan ciphertext baru tiap panggil (random IV),
     * jadi pencocokan WAJIB lewat dekripsi ke id — bukan perbandingan string.
     */
    private function findKurvaSkapal(iterable $rows): ?DataKapal
    {
        if (! $this->kurvaSkapalKey) {
            return null;
        }

        try {
            $id = Crypt::decryptString($this->kurvaSkapalKey);
        } catch (\Throwable) {
            return null;
        }

        return collect($rows)->firstWhere('id', $id);
    }

    public function openLaporan(int $dataKapalId, string $tipe)
    {
        $this->authorize('viewStats');
        $this->authorize('viewAny', LaporanHarian::class);
        abort_unless(in_array($tipe, self::LAPORAN_TIPES, true), 404);

        $dataKapal = $this->getDataKapalQuery()->find($dataKapalId);
        abort_unless($dataKapal, 403);

        return $this->redirect(route("laporan-{$tipe}.index", $dataKapal), navigate: true);
    }

    /**
     * Id data kapal yang boleh diakses user (scope owner / view_all).
     *
     * @return Collection<int, int>
     */
    private function accessibleKapalIds(): Collection
    {
        return $this->getDataKapalQuery()->reorder()->pluck('data_kapal.id');
    }

    private function greeting(): string
    {
        return match (true) {
            now()->hour < 11 => 'Selamat pagi',
            now()->hour < 15 => 'Selamat siang',
            now()->hour < 18 => 'Selamat sore',
            default => 'Selamat malam',
        };
    }

    public function render(DashboardService $service)
    {
        $user = auth()->user();
        $canViewStats = Gate::allows('viewStats');

        $data = [
            'authUser' => $user,
            'authUserRole' => $user->getRoleNames()->join(', ') ?: 'User',
            'canViewStats' => $canViewStats,
            'appJoinedAt' => $user->created_at,
        ];

        if ($canViewStats) {
            $kapalIds = $this->accessibleKapalIds();
            $detail = $this->galanganKode ? $service->galanganDetail($this->galanganKode, $kapalIds) : null;

            if ($this->galanganKode && ! $detail) {
                $this->galanganKode = null;
            }

            $kurvaSKapal = $service->kurvaSKapalRows($kapalIds);
            $selectedKurvaSkapal = $this->kurvaSkapalKey ? $this->findKurvaSkapal($kurvaSKapal) : null;

            if ($this->kurvaSkapalKey && ! $selectedKurvaSkapal) {
                $this->kurvaSkapalKey = null;
            }

            $data += [
                'greeting' => $this->greeting(),
                'totalUsers' => User::count(),
                'totalOwners' => Owner::count(),
                'totalRoles' => Role::count(),
                'summary' => $service->summary($kapalIds),
                'markers' => $service->markers($kapalIds),
                'detail' => $detail,
                'recentLaporan' => $service->recentLaporan($kapalIds),
                'kurvaSKapal' => $kurvaSKapal,
                'selectedKurvaSkapal' => $selectedKurvaSkapal,
                'kurvaSChart' => $selectedKurvaSkapal
                    ? $service->kurvaSChart($selectedKurvaSkapal, $this->kurvaSMode)
                    : null,
            ];
        }

        return view('livewire.pages.dashboard', $data);
    }
}
