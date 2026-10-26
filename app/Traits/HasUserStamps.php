<?php

namespace App\Traits;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Stamp created_by/updated_by otomatis dari user yang sedang login.
 * Model bisa membatasi kolom via protected array $userStamps (mis. ['updated_by']).
 */
trait HasUserStamps
{
    public static function bootHasUserStamps(): void
    {
        static::creating(function ($model) {
            $userId = auth()->id();
            if (! $userId) {
                return;
            }
            if (in_array('created_by', $model->userStampColumns(), true) && ! $model->created_by) {
                $model->created_by = $userId;
            }
            if (in_array('updated_by', $model->userStampColumns(), true) && ! $model->updated_by) {
                $model->updated_by = $userId;
            }
        });

        static::updating(function ($model) {
            $userId = auth()->id();
            if ($userId && in_array('updated_by', $model->userStampColumns(), true)) {
                $model->updated_by = $userId;
            }
        });
    }

    /**
     * @return list<string>
     */
    protected function userStampColumns(): array
    {
        return $this->userStamps ?? ['created_by', 'updated_by'];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
