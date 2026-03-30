<?php

namespace App\Livewire\Layout;

use App\Livewire\Actions\Logout;
use App\Livewire\Traits\HasMenuItems;
use Livewire\Attributes\On;
use Livewire\Component;

class Navigation extends Component
{
    use HasMenuItems;

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

    public function render()
    {
        $user = auth()->user();
        $roles = $user->getRoleNames();

        return view('livewire.layout.navigation', [
            'title' => data_get(app('view')->getShared(), 'pageTitle', 'Dashboard'),
            'menuItems' => $this->getMenuItems(),
            'authUser' => $user,
            'authUserRole' => $roles->isNotEmpty() ? $roles->join(', ') : 'User',
        ]);
    }
}
