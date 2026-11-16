<?php

namespace App\Livewire\Pages;

use App\Livewire\Traits\HasDataKapalFilter;
use App\Models\Company;
use App\Models\LaporanHarian;
use App\Models\User;
use App\Services\DashboardService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Collection;
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

    public function mount(): void
    {
        if (! Gate::allows('viewStats')) {
            $this->galanganKode = null;
        }
    }

    public function selectGalangan(?string $kode): void
    {
        $this->authorize('viewStats');

        $this->galanganKode = $kode ?: null;
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
     * Id data kapal yang boleh diakses user (scope perusahaan / view_all).
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

            $data += [
                'greeting' => $this->greeting(),
                'totalUsers' => User::count(),
                'totalCompanies' => Company::count(),
                'totalRoles' => Role::count(),
                'summary' => $service->summary($kapalIds),
                'markers' => $service->markers($kapalIds),
                'detail' => $detail,
                'recentLaporan' => $service->recentLaporan($kapalIds),
            ];
        }

        return view('livewire.pages.dashboard', $data);
    }
}
