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

    public function uploadTemplate(JenisKapal $jenisKapal, TemporaryUploadedFile $file, string $tipe = 'harian'): bool
    {
        $column = 'template_path_' . $tipe;
        
        // Delete old template if exists
        if ($jenisKapal->$column && Storage::disk('local')->exists($jenisKapal->$column)) {
            Storage::disk('local')->delete($jenisKapal->$column);
        }

        // Store new template in specific folder based on tipe
        $folder = 'templates/laporan-' . $tipe;
        $filename = 'template-' . $jenisKapal->id . '-' . time() . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs($folder, $filename, 'local');

        return $jenisKapal->update([$column => $path]);
    }

    public function deleteTemplate(JenisKapal $jenisKapal, string $tipe): bool
    {
        $column = 'template_path_' . $tipe;
        if ($jenisKapal->$column && Storage::disk('local')->exists($jenisKapal->$column)) {
            Storage::disk('local')->delete($jenisKapal->$column);
        }

        return $jenisKapal->update([$column => null]);
    }

    public function downloadTemplate(JenisKapal $jenisKapal, string $tipe): ?string
    {
        if (!$jenisKapal->hasTemplate($tipe)) {
            return null;
        }

        return $jenisKapal->getTemplateFullPath($tipe);
    }

    public function downloadDefaultTemplate(string $tipe = 'harian'): ?string
    {
        $templatePath = storage_path('app/templates/laporan-' . $tipe . '/template-laporan-' . $tipe . '.docx');

        if (!file_exists($templatePath)) {
            return null;
        }

        return $templatePath;
    }
}
