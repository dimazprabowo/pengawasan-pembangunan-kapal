<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Pagination\LengthAwarePaginator;

class UserService
{
    public function getFilteredUsers(
        ?string $search = null,
        ?string $roleFilter = null,
        ?string $statusFilter = null,
        int $perPage = 15
    ): LengthAwarePaginator {
        $query = User::with(['roles', 'company']);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('position', 'like', "%{$search}%");
            });
        }

        if ($roleFilter) {
            $query->role($roleFilter);
        }

        if ($statusFilter !== null && $statusFilter !== '') {
            $query->where('is_active', $statusFilter);
        }

        return $query->orderBy('id', 'desc')->paginate($perPage);
    }

    public function create(array $data, array $roles): User
    {
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'company_id' => $data['company_id'] ?? null,
            'phone' => $data['phone'] ?? null,
            'position' => $data['position'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'email_verified_at' => now(),
        ]);

        $user->syncRoles($roles);

        return $user;
    }

    public function update(User $user, array $data, array $roles): User
    {
        $updateData = [
            'name' => $data['name'],
            'email' => $data['email'],
            'company_id' => $data['company_id'] ?? null,
            'phone' => $data['phone'] ?? null,
            'position' => $data['position'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ];

        if (!empty($data['password'])) {
            $updateData['password'] = Hash::make($data['password']);
        }

        $user->update($updateData);
        $user->syncRoles($roles);

        if (!$user->is_active) {
            $this->invalidateSessions($user);
        }

        return $user;
    }

    public function delete(User $user): void
    {
        $user->delete();
    }

    public function toggleActive(User $user): User
    {
        $user->update(['is_active' => !$user->is_active]);

        if (!$user->is_active) {
            $this->invalidateSessions($user);
        }

        return $user;
    }

    public function resetPassword(User $user, string $newPassword): void
    {
        $user->update(['password' => Hash::make($newPassword)]);
    }

    public function isSelf(int $userId): bool
    {
        return $userId === (int) auth()->id();
    }

    private function invalidateSessions(User $user): void
    {
        DB::table('sessions')->where('user_id', $user->id)->delete();
    }
}
