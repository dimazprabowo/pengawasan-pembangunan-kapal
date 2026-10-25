<?php

namespace App\Services;

use App\Enums\DataKapalStatus;
use App\Exports\KurvaSTemplateExport;
use App\Imports\KurvaSRencanaImport;
use App\Imports\KurvaSTemplateImport;
use App\Models\DataKapal;
use App\Models\KurvaSRencana;
use App\Models\KurvaSWorkGroup;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Maatwebsite\Excel\Facades\Excel;

class DataKapalService
{
    public function getFiltered(
        string $search = '',
        string $statusFilter = '',
        ?int $companyFilter = null,
        ?int $galanganFilter = null,
        int $perPage = 15
    ): LengthAwarePaginator {
        return DataKapal::with(['company', 'galangan'])
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

    public function create(array $data): DataKapal
    {
        return DataKapal::create($data);
    }

    public function update(DataKapal $dataKapal, array $data): bool
    {
        return $dataKapal->update($data);
    }

    public function delete(DataKapal $dataKapal): bool
    {
        return $dataKapal->delete();
    }

    public function toggleStatus(DataKapal $dataKapal): bool
    {
        $newStatus = $dataKapal->status === DataKapalStatus::Active
            ? DataKapalStatus::Inactive
            : DataKapalStatus::Active;

        return $dataKapal->update(['status' => $newStatus]);
    }

    public function uploadTemplate(DataKapal $dataKapal, TemporaryUploadedFile $file, string $tipe = 'harian'): bool
    {
        $column = 'template_path_'.$tipe;

        // Delete old template if exists
        if ($dataKapal->$column && Storage::disk('local')->exists($dataKapal->$column)) {
            Storage::disk('local')->delete($dataKapal->$column);
        }

        // Store new template in specific folder based on tipe
        $folder = 'templates/laporan-'.$tipe;
        $filename = 'template-'.$dataKapal->id.'-'.time().'.'.$file->getClientOriginalExtension();
        $path = $file->storeAs($folder, $filename, 'local');

        return $dataKapal->update([$column => $path]);
    }

    public function deleteTemplate(DataKapal $dataKapal, string $tipe): bool
    {
        $column = 'template_path_'.$tipe;
        if ($dataKapal->$column && Storage::disk('local')->exists($dataKapal->$column)) {
            Storage::disk('local')->delete($dataKapal->$column);
        }

        return $dataKapal->update([$column => null]);
    }

    public function downloadTemplate(DataKapal $dataKapal, string $tipe): ?string
    {
        if (! $dataKapal->hasTemplate($tipe)) {
            return null;
        }

        return $dataKapal->getTemplateFullPath($tipe);
    }

    public function downloadDefaultTemplate(string $tipe = 'harian'): ?string
    {
        $templatePath = storage_path('app/templates/laporan-'.$tipe.'/template-laporan-'.$tipe.'.docx');

        if (! file_exists($templatePath)) {
            return null;
        }

        return $templatePath;
    }

    public function exportKurvaSTemplate(DataKapal $dataKapal, bool $withData = true)
    {
        $prefix = $withData ? 'kurva-s-data' : 'kurva-s-template-kosong';
        $filename = $prefix.'-'.\Str::slug($dataKapal->nama).'-'.now()->format('Y-m-d-His').'.xlsx';

        return Excel::download(
            new KurvaSTemplateExport($dataKapal, $withData),
            $filename
        );
    }

    public function importKurvaSTemplate(DataKapal $dataKapal, TemporaryUploadedFile $file): array
    {
        DB::beginTransaction();

        try {
            $import = new KurvaSTemplateImport($dataKapal);
            Excel::import($import, $file->getRealPath());

            if ($import->hasErrors()) {
                DB::rollBack();

                return [
                    'success' => false,
                    'errors' => $import->getErrors(),
                ];
            }

            $rencanaImport = $import->sheets()['Rencana'];

            if (! ($rencanaImport instanceof KurvaSRencanaImport)) {
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

            // Guard: rencana tidak boleh melebihi total bulan kontrak
            if ($dataKapal->hasKontrak()) {
                $totalBulanKontrak = app(KontrakPeriodeService::class)->getTotalBulan($dataKapal);
                $maxBulanImport = 0;
                foreach ($workGroupsData as $wgData) {
                    foreach ($wgData['monthly_data'] ?? [] as $rencana) {
                        $maxBulanImport = max($maxBulanImport, (int) ($rencana['bulan_ke'] ?? 0));
                    }
                }
                if ($maxBulanImport > $totalBulanKontrak) {
                    DB::rollBack();

                    return [
                        'success' => false,
                        'errors' => ["Import ditolak: template berisi hingga bulan ke-{$maxBulanImport}, melebihi total {$totalBulanKontrak} bulan kontrak."],
                    ];
                }
            }

            KurvaSRencana::whereHas('workGroup', function ($q) use ($dataKapal) {
                $q->where('data_kapal_id', $dataKapal->id);
            })->delete();

            KurvaSWorkGroup::where('data_kapal_id', $dataKapal->id)->delete();

            $now = now();
            foreach ($workGroupsData as $wgName => $wgData) {
                $workGroup = KurvaSWorkGroup::create([
                    'data_kapal_id' => $dataKapal->id,
                    'nama' => $wgName,
                    'bobot' => $wgData['bobot'],
                    'sort_order' => $wgData['sort_order'],
                ]);

                if (isset($wgData['monthly_data'])) {
                    $rencanaInsert = [];
                    foreach ($wgData['monthly_data'] as $rencana) {
                        $rencanaInsert[] = [
                            'work_group_id' => $workGroup->id,
                            'bulan_ke' => $rencana['bulan_ke'],
                            'pct_rencana' => $rencana['pct_rencana'],
                            'keterangan' => $rencana['keterangan'],
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }

                    if (! empty($rencanaInsert)) {
                        KurvaSRencana::insert($rencanaInsert);
                    }
                }
            }

            DB::commit();

            return [
                'success' => true,
                'message' => 'Data Kurva S berhasil diimport! Total work groups: '.count($workGroupsData),
                'work_groups_count' => count($workGroupsData),
            ];

        } catch (\Exception $e) {
            DB::rollBack();

            return [
                'success' => false,
                'errors' => ['Terjadi kesalahan saat import: '.$e->getMessage()],
            ];
        }
    }
}
