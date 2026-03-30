<?php

namespace App\Traits;

use Illuminate\Support\Facades\Crypt;

trait HasEncryptedRouteKey
{
    /**
     * Get the value of the model's route key.
     */
    public function getRouteKey(): mixed
    {
        return Crypt::encryptString($this->getKey());
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
