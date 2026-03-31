<?php

namespace App\Services;

use App\Models\Laporan;
use App\Models\LaporanLampiran;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class LaporanService
{
    public function getFiltered(
        string $tipe,
        ?string $search = null,
        ?int $jenisKapalId = null,
        int $perPage = 15
    ): LengthAwarePaginator {
        $query = Laporan::with(['user', 'jenisKapal.company', 'jenisKapal.galangan', 'lampiran'])
            ->where('tipe', $tipe)
            ->byJenisKapal($jenisKapalId);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('judul', 'like', "%{$search}%")
                  ->orWhere('isi', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($q) use ($search) {
                      $q->where('name', 'like', "%{$search}%");
                  });
            });
        }

        return $query->orderByDesc('tanggal_laporan')
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function create(array $data): Laporan
    {
        return Laporan::create($data);
    }

    public function createMany(array $items): array
    {
        $created = [];

        DB::transaction(function () use ($items, &$created) {
            foreach ($items as $data) {
                $created[] = Laporan::create($data);
            }
        });

        return $created;
    }

    public function update(Laporan $laporan, array $data): Laporan
    {
        $laporan->update($data);
        return $laporan;
    }

    public function delete(Laporan $laporan): void
    {
        // Delete all lampiran files first
        foreach ($laporan->lampiran as $lampiran) {
            $this->deleteLampiranFile($lampiran);
        }

        // Delete old single file if exists (backward compatibility)
        $this->deleteFile($laporan);

        $laporan->delete();
    }

    public function removeFile(Laporan $laporan): void
    {
        $this->deleteFile($laporan);
        $laporan->update([
            'file_path' => null,
            'file_name' => null,
            'file_size' => null,
        ]);
    }

    public function addLampiran(Laporan $laporan, array $lampiranData): LaporanLampiran
    {
        return $laporan->lampiran()->create([
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

    private function deleteFile(Laporan $laporan): void
    {
        if ($laporan->file_path && Storage::disk('local')->exists($laporan->file_path)) {
            Storage::disk('local')->delete($laporan->file_path);
        }
    }

    private function deleteLampiranFile(LaporanLampiran $lampiran): void
    {
        if ($lampiran->file_path && Storage::disk('local')->exists($lampiran->file_path)) {
            Storage::disk('local')->delete($lampiran->file_path);
        }
    }
}
