<?php

namespace App\Services;

use App\Models\SystemConfiguration;
use Illuminate\Pagination\LengthAwarePaginator;

class SystemConfigurationService
{
    public function getFiltered(
        ?string $search = null,
        int $perPage = 15
    ): LengthAwarePaginator {
        $query = SystemConfiguration::query();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('key', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('value', 'like', "%{$search}%");
            });
        }

        return $query->orderBy('category')->orderBy('key')->paginate($perPage);
    }

    public function update(SystemConfiguration $config, array $data): SystemConfiguration
    {
        $config->update($data);

        $this->applyRuntimeConfig($config->key, $config->value, $config->is_active);

        return $config;
    }

    public function toggleActive(SystemConfiguration $config): SystemConfiguration
    {
        $config->update(['is_active' => !$config->is_active]);

        $this->applyRuntimeConfig($config->key, $config->value, $config->is_active);

        return $config;
    }

    /**
     * Apply config change to Laravel runtime if it maps to a Laravel config key.
     */
    public function applyRuntimeConfig(string $key, mixed $value, bool $isActive): void
    {
        $configMap = [
            'app.name' => 'app.name',
            'app.timezone' => 'app.timezone',
        ];

        if (isset($configMap[$key]) && $isActive) {
            config([$configMap[$key] => $value]);

            if ($key === 'app.timezone') {
                date_default_timezone_set($value);
            }
        }
    }
}
