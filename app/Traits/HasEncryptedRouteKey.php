<?php

namespace App\Traits;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

trait HasEncryptedRouteKey
{
    /**
     * Get the value of the model's route key.
     *
     * Crypt::encryptString memakai random IV — ciphertext baru tiap panggil,
     * sehingga route() menghasilkan URL berbeda tiap render (<img>/<iframe>
     * reload terus, signed URL gagal). Di-cache per model agar URL stabil;
     * ciphertext lama tetap valid karena semuanya decrypt ke id yang sama.
     */
    public function getRouteKey(): mixed
    {
        return Cache::remember(
            'route-key:'.static::class.':'.$this->getKey(),
            now()->addDays(30),
            fn () => Crypt::encryptString($this->getKey())
        );
    }

    /**
     * Retrieve the model for a bound value.
     */
    public function resolveRouteBinding($value, $field = null): ?static
    {
        try {
            $decryptedId = Crypt::decryptString($value);

            return $this->where($this->getKeyName(), $decryptedId)->first();
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Retrieve the child model for a bound value.
     */
    public function resolveChildRouteBinding($childType, $value, $field): ?static
    {
        try {
            $decryptedId = Crypt::decryptString($value);

            return parent::resolveChildRouteBinding($childType, $decryptedId, $field);
        } catch (\Exception $e) {
            return null;
        }
    }
}
