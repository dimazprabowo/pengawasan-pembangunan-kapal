<?php

namespace App\Livewire\Traits;

trait StabilizesSnapshot
{
    public function dehydrateStabilizesSnapshot(): void
    {
        foreach ($this->all() as $property => $value) {
            if (is_array($value)) {
                $this->{$property} = $this->stabilizeSnapshotArray($value);
            }
        }
    }

    private function stabilizeSnapshotArray(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->stabilizeSnapshotArray($value);
            } elseif (is_float($value) && floor($value) === $value && $value >= PHP_INT_MIN && $value <= PHP_INT_MAX) {
                $data[$key] = (int) $value;
            }
        }

        uksort($data, function ($a, $b) {
            $aIndex = is_int($a) && $a >= 0 && $a < 4294967295;
            $bIndex = is_int($b) && $b >= 0 && $b < 4294967295;

            if ($aIndex && $bIndex) {
                return $a <=> $b;
            }

            if ($aIndex) {
                return -1;
            }

            if ($bIndex) {
                return 1;
            }

            return 0;
        });

        return $data;
    }
}
