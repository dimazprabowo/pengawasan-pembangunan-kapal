<?php

namespace App\Livewire\Profile;

use App\Livewire\Traits\HasNotification;
use App\Models\User;
use App\Services\ImpersonateService;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role;

class ImpersonateUser extends Component
{
    use HasNotification, WithPagination;

    public string $search = '';
    public string $roleFilter = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingRoleFilter(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'roleFilter']);
        $this->resetPage();
    }

    public function startImpersonate(int $userId, ImpersonateService $service): void
    {
        abort_unless(auth()->user()->can('users_impersonate'), 403);
        abort_if($service->isImpersonating(), 403, 'Anda sedang dalam sesi impersonate.');

        $target = User::findOrFail($userId);
        $this->authorize('impersonate', $target);

        $service->start($target);

        $this->notifySuccess("Anda sekarang beraksi sebagai {$target->name}.");
        $this->redirect(route('dashboard'), navigate: true);
    }

    public function render()
    {
        $query = User::with('roles')
            ->where('id', '!=', auth()->id())
            ->when($this->search, function ($q) {
                $q->where(function ($sub) {
                    $sub->where('name', 'like', "%{$this->search}%")
                        ->orWhere('email', 'like', "%{$this->search}%");
                });
            })
            ->when($this->roleFilter, function ($q) {
                $q->role($this->roleFilter);
            })
            ->orderBy('name');

        return view('livewire.profile.impersonate-user', [
            'users' => $query->paginate(10),
            'roles' => Role::orderBy('name')->get(),
        ]);
    }
}
