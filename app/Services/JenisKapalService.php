<?php

namespace App\Services;

use App\Enums\JenisKapalStatus;
use App\Exports\KurvaSTemplateExport;
use App\Imports\KurvaSTemplateImport;
use App\Imports\KurvaSWorkGroupsImport;
use App\Imports\KurvaSRencanaImport;
use App\Models\JenisKapal;
use App\Models\KurvaSWorkGroup;
use App\Models\KurvaSRencana;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Maatwebsite\Excel\Facades\Excel;

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
            ->withCount('laporanHarian')
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

    public function exportKurvaSTemplate(JenisKapal $jenisKapal, bool $withData = true)
    {
        $filename = 'kurva-s-template-' . \Str::slug($jenisKapal->nama) . '-' . now()->format('Y-m-d-His') . '.xlsx';
        
        return Excel::download(
            new KurvaSTemplateExport($jenisKapal, $withData),
            $filename
        );
    }

    public function importKurvaSTemplate(JenisKapal $jenisKapal, TemporaryUploadedFile $file): array
    {
        DB::beginTransaction();

        try {
            $import = new KurvaSTemplateImport($jenisKapal);
            Excel::import($import, $file->getRealPath());

            if ($import->hasErrors()) {
                DB::rollBack();
                return [
                    'success' => false,
                    'errors' => $import->getErrors(),
                ];
            }

            $rencanaImport = $import->sheets()['Rencana'];

            if (!($rencanaImport instanceof KurvaSRencanaImport)) {
                DB::rollBack();
                return [
                    'success' => false,
                    'errors' => ['Format file tidak valid. Pastikan menggunakan template yang benar.'],
                ];
            }

            $workGroupsData = $import->getRencanaData();

            if (empty($workGroupsData)) {
                DB::rollBack();
                return [
                    'success' => false,
                    'errors' => ['Tidak ada data work group yang valid untuk diimport.'],
                ];
            }

            KurvaSRencana::whereHas('workGroup', function ($q) use ($jenisKapal) {
                $q->where('jenis_kapal_id', $jenisKapal->id);
            })->delete();
            
            KurvaSWorkGroup::where('jenis_kapal_id', $jenisKapal->id)->delete();

            $now = now();
            foreach ($workGroupsData as $wgName => $wgData) {
                $workGroup = KurvaSWorkGroup::create([
                    'jenis_kapal_id' => $jenisKapal->id,
                    'nama' => $wgName,
                    'bobot' => $wgData['bobot'],
                    'sort_order' => $wgData['sort_order'],
                ]);

                if (isset($wgData['weekly_data'])) {
                    $rencanaInsert = [];
                    foreach ($wgData['weekly_data'] as $rencana) {
                        $rencanaInsert[] = [
                            'work_group_id' => $workGroup->id,
                            'minggu_ke' => $rencana['minggu_ke'],
                            'pct_rencana' => $rencana['pct_rencana'],
                            'keterangan' => $rencana['keterangan'],
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                    
                    if (!empty($rencanaInsert)) {
                        KurvaSRencana::insert($rencanaInsert);
                    }
                }
            }

            DB::commit();

            return [
                'success' => true,
                'message' => 'Data Kurva S berhasil diimport! Total work groups: ' . count($workGroupsData),
                'work_groups_count' => count($workGroupsData),
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            
            return [
                'success' => false,
                'errors' => ['Terjadi kesalahan saat import: ' . $e->getMessage()],
            ];
        }
    }
}
