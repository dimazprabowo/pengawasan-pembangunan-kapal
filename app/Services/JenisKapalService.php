<?php

namespace App\Services;

use App\Enums\JenisKapalStatus;
use App\Models\JenisKapal;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class JenisKapalService
{
    public function getFiltered(
        string $search = '',
        string $statusFilter = '',
        ?int $companyFilter = null,
        ?int $galanganFilter = null,
        int $perPage = 15
    ): LengthAwarePaginator {
        return JenisKapal::with(['company', 'galangan'])
            ->withCount('laporan')
            ->when($search, function ($q) use ($search) {
                $q->where(function ($q) use ($search) {
                    $q->where('nama', 'like', "%{$search}%")
                      ->orWhere('deskripsi', 'like', "%{$search}%")
                      ->orWhereHas('company', function ($q) use ($search) {
                          $q->where('name', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%");
                      });
                });
            })
            ->when($statusFilter !== null && $statusFilter !== '', function ($q) use ($statusFilter) {
                $q->where('status', $statusFilter);
            })
            ->when($companyFilter, function ($q) use ($companyFilter) {
                $q->where('company_id', $companyFilter);
            })
            ->when($galanganFilter, function ($q) use ($galanganFilter) {
                $q->where('galangan_id', $galanganFilter);
            })
            ->orderBy('id', 'desc')
            ->paginate($perPage);
    }

    public function create(array $data): JenisKapal
    {
        return JenisKapal::create($data);
    }

    public function update(JenisKapal $jenisKapal, array $data): bool
    {
        return $jenisKapal->update($data);
    }

    public function delete(JenisKapal $jenisKapal): bool
    {
        return $jenisKapal->delete();
    }

    public function toggleStatus(JenisKapal $jenisKapal): bool
    {
        $newStatus = $jenisKapal->status === JenisKapalStatus::Active
            ? JenisKapalStatus::Inactive
            : JenisKapalStatus::Active;

        return $jenisKapal->update(['status' => $newStatus]);
    }

    public function uploadTemplate(JenisKapal $jenisKapal, TemporaryUploadedFile $file): bool
    {
        if ($jenisKapal->template_path && Storage::disk('local')->exists($jenisKapal->template_path)) {
            Storage::disk('local')->delete($jenisKapal->template_path);
        }

        $filename = 'template-' . $jenisKapal->id . '-' . time() . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs('templates/laporan-jenis-kapal', $filename, 'local');

        return $jenisKapal->update(['template_path' => $path]);
    }

    public function deleteTemplate(JenisKapal $jenisKapal): bool
    {
        if ($jenisKapal->template_path && Storage::disk('local')->exists($jenisKapal->template_path)) {
            Storage::disk('local')->delete($jenisKapal->template_path);
        }

        return $jenisKapal->update(['template_path' => null]);
    }

    public function downloadTemplate(JenisKapal $jenisKapal): ?string
    {
        if (!$jenisKapal->hasTemplate()) {
            return null;
        }

        return $jenisKapal->getTemplateFullPath();
    }
}
