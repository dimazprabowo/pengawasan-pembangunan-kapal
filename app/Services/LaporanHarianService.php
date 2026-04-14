<?php

namespace App\Services;

use App\Models\LaporanHarian;
use App\Models\LaporanLampiran;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class LaporanHarianService
{
    public function getFiltered(
        ?string $search = null,
        ?int $jenisKapalId = null,
        int $perPage = 15
    ): LengthAwarePaginator {
        $query = LaporanHarian::with(['user', 'jenisKapal.company', 'jenisKapal.galangan', 'lampiran'])
            ->byJenisKapal($jenisKapalId);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('judul', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($q) use ($search) {
                      $q->where('name', 'like', "%{$search}%");
                  });
            });
        }

        return $query->orderByDesc('tanggal_laporan')
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function create(array $data): LaporanHarian
    {
        return LaporanHarian::create($data);
    }

    public function createMany(array $items): array
    {
        $created = [];

        DB::transaction(function () use ($items, &$created) {
            foreach ($items as $data) {
                $created[] = LaporanHarian::create($data);
            }
        });

        return $created;
    }

    public function update(LaporanHarian $laporanHarian, array $data): LaporanHarian
    {
        $laporanHarian->update($data);
        return $laporanHarian;
    }

    public function delete(LaporanHarian $laporanHarian): void
    {
        // Delete all lampiran files first
        foreach ($laporanHarian->lampiran as $lampiran) {
            $this->deleteLampiranFile($lampiran);
        }

        // Delete old single file if exists (backward compatibility)
        $this->deleteFile($laporanHarian);

        // Delete generated Word document if exists
        $this->deleteDocFile($laporanHarian);

        $laporanHarian->delete();
    }

    public function removeFile(LaporanHarian $laporanHarian): void
    {
        $this->deleteFile($laporanHarian);
        $laporanHarian->update([
            'file_path' => null,
            'file_name' => null,
            'file_size' => null,
        ]);
    }

    public function addLampiran(LaporanHarian $laporanHarian, array $lampiranData): LaporanLampiran
    {
        return $laporanHarian->lampiran()->create([
            'file_name' => $lampiranData['file_name'],
            'file_size' => $lampiranData['file_size'] ?? null,
            'keterangan' => $lampiranData['keterangan'] ?? null,
            'file_status' => 'pending',
        ]);
    }

    public function updateLampiranKeterangan(LaporanLampiran $lampiran, ?string $keterangan): void
    {
        $lampiran->update(['keterangan' => $keterangan]);
    }

    public function deleteLampiran(LaporanLampiran $lampiran): void
    {
        $this->deleteLampiranFile($lampiran);
        $lampiran->delete();
    }

    public function removeDoc(LaporanHarian $laporanHarian): void
    {
        $this->deleteDocFile($laporanHarian);
        $laporanHarian->update([
            'doc_path'         => null,
            'doc_name'         => null,
            'doc_status'       => null,
            'doc_generated_at' => null,
            'doc_error'        => null,
        ]);
    }

    private function deleteFile(LaporanHarian $laporanHarian): void
    {
        if ($laporanHarian->file_path && Storage::disk('local')->exists($laporanHarian->file_path)) {
            Storage::disk('local')->delete($laporanHarian->file_path);
        }
    }

    private function deleteDocFile(LaporanHarian $laporanHarian): void
    {
        if ($laporanHarian->doc_path) {
            $path = storage_path('app/' . $laporanHarian->doc_path);
            if (file_exists($path)) {
                @unlink($path);
            }
        }
    }

    private function deleteLampiranFile(LaporanLampiran $lampiran): void
    {
        if ($lampiran->file_path && Storage::disk('local')->exists($lampiran->file_path)) {
            Storage::disk('local')->delete($lampiran->file_path);
        }
    }
}
