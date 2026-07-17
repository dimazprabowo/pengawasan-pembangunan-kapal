<?php

namespace App\Livewire\Layout;

use App\Livewire\Actions\Logout;
use App\Livewire\Traits\HasMenuItems;
use App\Livewire\Traits\HasNotification;
use App\Services\ImpersonateService;
use Livewire\Attributes\On;
use Livewire\Component;

class Navigation extends Component
{
    use HasMenuItems, HasNotification;

    #[On('profile-updated')]
    public function refreshUserData(): void
    {
        // Force re-render to get fresh auth()->user() data
    }

    public function logout(Logout $logout): void
    {
        $logout();
        $this->redirect('/', navigate: true);
    }

    public function stopImpersonating(ImpersonateService $service): void
    {
        abort_unless($service->isImpersonating(), 403, 'Tidak ada sesi impersonate yang aktif.');

        $service->stop();
        $this->notifySuccess('Berhasil kembali ke akun Anda.');
        $this->redirect(route('dashboard'), navigate: true);
    }

    public function render(ImpersonateService $impersonateService)
    {
        $user = auth()->user();
        $roles = $user->getRoleNames();

        return view('livewire.layout.navigation', [
            'title' => data_get(app('view')->getShared(), 'pageTitle', 'Dashboard'),
            'menuItems' => $this->getMenuItems(),
            'authUser' => $user,
            'authUserRole' => $roles->isNotEmpty() ? $roles->join(', ') : 'User',
            'isImpersonating' => $impersonateService->isImpersonating(),
            'originalUser' => $impersonateService->getOriginalUser(),
        ]);
    }
}
