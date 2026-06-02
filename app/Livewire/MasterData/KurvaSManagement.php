<?php

namespace App\Livewire\MasterData;

use App\Livewire\Traits\HasNotification;
use App\Models\JenisKapal;
use App\Services\KurvaSService;
use App\Services\JenisKapalService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

class KurvaSManagement extends Component
{
    use AuthorizesRequests, HasNotification, WithFileUploads;

    public bool $showModal  = false;
    public ?int $jenisKapalId   = null;
    public ?string $jenisKapalNama = null;

    // Import modal states
    public bool $showKurvaSImportModal = false;
    public $kurvaS_file;

    /**
     * Array of work groups:
     * [
     *   [
     *     'id'    => null|int,
     *     'nama'  => 'Engineering & Design',
     *     'bobot' => '5.00',
     *     'weeks' => [['minggu_ke' => 1, 'pct_rencana' => '3.00', 'keterangan' => ''], ...]
     *   ],
     *   ...
     * ]
     */
    public array $workGroups = [];

    /** Index work group yang sedang di-expand untuk edit minggu (-1 = none) */
    public int $expandedGroupIdx = -1;

    /** Total minggu untuk quick-set semua group */
    public int $totalMinggu = 12;

    /** Konfirmasi hapus work group */
    public ?int $confirmDeleteGroupIdx = null;

    /** Konfirmasi hapus minggu */
    public ?int $confirmDeleteWeekGroupIdx = null;
    public ?int $confirmDeleteWeekIdx      = null;

    #[On('open-kurvas-modal')]
    public function openModal(int $jenisKapalId): void
    {
        $jenisKapal = JenisKapal::findOrFail($jenisKapalId);
        $this->authorize('managekurvaSRencana', $jenisKapal);

        $this->jenisKapalId      = $jenisKapal->id;
        $this->jenisKapalNama    = $jenisKapal->nama;
        $this->expandedGroupIdx  = -1;
        $this->loadData();
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->reset(['jenisKapalId', 'jenisKapalNama', 'workGroups', 'expandedGroupIdx', 'totalMinggu']);
        $this->resetValidation();
    }

    private function loadData(): void
    {
        $jenisKapal = JenisKapal::findOrFail($this->jenisKapalId);
        $groups     = $jenisKapal->kurvaSWorkGroups()->with('kurvaSRencana')->get();

        $this->workGroups = $groups->map(fn($wg) => [
            'id'    => $wg->id,
            'nama'  => $wg->nama,
            'bobot' => number_format((float) $wg->bobot, 2, '.', ''),
            'weeks' => $wg->kurvaSRencana->map(fn($r) => [
                'minggu_ke'   => $r->minggu_ke,
                'pct_rencana' => number_format((float) $r->pct_rencana, 2, '.', ''),
                'keterangan'  => $r->keterangan ?? '',
            ])->toArray(),
        ])->toArray();

        if (!empty($this->workGroups)) {
            $this->totalMinggu = max(1, count($this->workGroups[0]['weeks'] ?? []));
        }
    }

    // ─── Work Group Actions ──────────────────────────────────────────────────

    public function addWorkGroup(): void
    {
        $weeks = [];
        for ($i = 1; $i <= max(1, $this->totalMinggu); $i++) {
            $weeks[] = ['minggu_ke' => $i, 'pct_rencana' => '0.00', 'keterangan' => ''];
        }

        $this->workGroups[] = [
            'id'    => null,
            'nama'  => '',
            'bobot' => '0.00',
            'weeks' => $weeks,
        ];

        $this->expandedGroupIdx = count($this->workGroups) - 1;
    }

    public function requestDeleteGroup(int $idx): void
    {
        $this->confirmDeleteGroupIdx = $idx;
    }

    public function cancelDeleteGroup(): void
    {
        $this->confirmDeleteGroupIdx = null;
    }

    public function confirmDeleteGroup(): void
    {
        if ($this->confirmDeleteGroupIdx !== null) {
            $this->removeWorkGroup($this->confirmDeleteGroupIdx);
        }
        $this->confirmDeleteGroupIdx = null;
    }

    private function removeWorkGroup(int $idx): void
    {
        array_splice($this->workGroups, $idx, 1);
        if ($this->expandedGroupIdx === $idx) {
            $this->expandedGroupIdx = -1;
        } elseif ($this->expandedGroupIdx > $idx) {
            $this->expandedGroupIdx--;
        }
    }

    public function toggleGroup(int $idx): void
    {
        $this->expandedGroupIdx = $this->expandedGroupIdx === $idx ? -1 : $idx;
    }

    // ─── Week Actions ────────────────────────────────────────────────────────

    public function setTotalMinggu(int $total): void
    {
        $total = max(1, min(100, $total));
        $this->totalMinggu = $total;

        $count = count($this->workGroups);

        if ($count === 0) {
            $this->notifyWarning('Tidak ada work group. Tambahkan work group terlebih dahulu.');
            return;
        }

        foreach ($this->workGroups as $gi => $wg) {
            $current = count($wg['weeks']);
            if ($total > $current) {
                for ($i = $current + 1; $i <= $total; $i++) {
                    $this->workGroups[$gi]['weeks'][] = [
                        'minggu_ke'   => $i,
                        'pct_rencana' => '0.00',
                        'keterangan'  => '',
                    ];
                }
            } elseif ($total < $current) {
                $this->workGroups[$gi]['weeks'] = array_slice($wg['weeks'], 0, $total);
            }
        }

        $this->notifyInfo("{$count} work group berhasil diperbarui ke {$total} minggu.");
    }

    public function addWeekToGroup(int $groupIdx): void
    {
        $next = count($this->workGroups[$groupIdx]['weeks']) + 1;
        $this->workGroups[$groupIdx]['weeks'][] = [
            'minggu_ke'   => $next,
            'pct_rencana' => '0.00',
            'keterangan'  => '',
        ];
    }

    public function requestDeleteWeek(int $groupIdx, int $weekIdx): void
    {
        $this->confirmDeleteWeekGroupIdx = $groupIdx;
        $this->confirmDeleteWeekIdx      = $weekIdx;
    }

    public function cancelDeleteWeek(): void
    {
        $this->confirmDeleteWeekGroupIdx = null;
        $this->confirmDeleteWeekIdx      = null;
    }

    public function confirmDeleteWeek(): void
    {
        if ($this->confirmDeleteWeekGroupIdx !== null && $this->confirmDeleteWeekIdx !== null) {
            $this->removeWeekFromGroup($this->confirmDeleteWeekGroupIdx, $this->confirmDeleteWeekIdx);
        }
        $this->confirmDeleteWeekGroupIdx = null;
        $this->confirmDeleteWeekIdx      = null;
    }

    private function removeWeekFromGroup(int $groupIdx, int $weekIdx): void
    {
        array_splice($this->workGroups[$groupIdx]['weeks'], $weekIdx, 1);
        foreach ($this->workGroups[$groupIdx]['weeks'] as $i => &$w) {
            $w['minggu_ke'] = $i + 1;
        }
    }

    // ─── Computed ────────────────────────────────────────────────────────────

    public function getTotalBobotProperty(): float
    {
        return array_reduce($this->workGroups, fn($c, $wg) => $c + (float) ($wg['bobot'] ?? 0), 0.0);
    }

    // ─── Validation ─────────────────────────────────────────────────────────

    protected function rules(): array
    {
        return [
            'workGroups'                         => 'required|array|min:1',
            'workGroups.*.nama'                  => 'required|string|max:255',
            'workGroups.*.bobot'                 => 'required|numeric|min:0|max:100',
            'workGroups.*.weeks'                 => 'array',
            'workGroups.*.weeks.*.minggu_ke'     => 'required|integer|min:1',
            'workGroups.*.weeks.*.pct_rencana'   => 'required|numeric|min:0|max:100',
            'workGroups.*.weeks.*.keterangan'    => 'nullable|string|max:255',
        ];
    }

    protected function messages(): array
    {
        return [
            'workGroups.required'                      => 'Minimal harus ada 1 work group.',
            'workGroups.min'                           => 'Minimal harus ada 1 work group.',
            'workGroups.*.nama.required'               => 'Nama work group wajib diisi.',
            'workGroups.*.bobot.required'              => 'Bobot wajib diisi.',
            'workGroups.*.bobot.numeric'               => 'Bobot harus berupa angka.',
            'workGroups.*.weeks.*.pct_rencana.required'=> 'Rencana % wajib diisi.',
            'workGroups.*.weeks.*.pct_rencana.max'     => 'Rencana % per minggu maks 100%.',
        ];
    }

    // ─── Save ────────────────────────────────────────────────────────────────

    public function save(KurvaSService $service): void
    {
        $jenisKapal = JenisKapal::findOrFail($this->jenisKapalId);
        $this->authorize('managekurvaSRencana', $jenisKapal);

        try {
            $this->validate();
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->notifyValidationError($e);
            throw $e;
        }

        $total = $this->totalBobot;
        if ($total > 100.01) {
            $this->notifyError('Total bobot semua work group tidak boleh melebihi 100%. Saat ini: ' . number_format($total, 2) . '%');
            return;
        }

        try {
            $service->saveWorkGroups($jenisKapal, $this->workGroups);

            $warning = abs($total - 100.0) > 0.01
                ? ' (Total bobot: ' . number_format($total, 2) . '% — belum 100%)'
                : '';

            $this->notifySuccess('Kurva S berhasil disimpan!' . $warning);
            $this->closeModal();
            $this->dispatch('kurvas-saved');
        } catch (\Illuminate\Auth\Access\AuthorizationException) {
            $this->notifyError('Anda tidak memiliki izin untuk mengatur Kurva S.');
        } catch (\Exception $e) {
            $this->notifyError('Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    // ─── Export & Import ─────────────────────────────────────────────────────

    public function exportKurvaSTemplate(bool $withData, JenisKapalService $service)
    {
        try {
            $jenisKapal = JenisKapal::findOrFail($this->jenisKapalId);
            $this->authorize('exportKurvaSTemplate', $jenisKapal);

            return $service->exportKurvaSTemplate($jenisKapal, $withData);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            $this->notifyError('Anda tidak memiliki izin untuk export template Kurva S.');
        } catch (\Exception $e) {
            $this->notifyError('Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    public function openKurvaSImportModal()
    {
        try {
            $jenisKapal = JenisKapal::findOrFail($this->jenisKapalId);
            $this->authorize('importKurvaSTemplate', $jenisKapal);

            $this->showKurvaSImportModal = true;
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            $this->notifyError('Anda tidak memiliki izin untuk import template Kurva S.');
        }
    }

    public function closeKurvaSImportModal()
    {
        $this->showKurvaSImportModal = false;
        $this->reset(['kurvaS_file']);
        $this->resetValidation();
    }

    public function importKurvaSTemplate(JenisKapalService $service)
    {
        try {
            $this->validate([
                'kurvaS_file' => 'required|file|mimes:xlsx,xls|max:5120',
            ], [
                'kurvaS_file.required' => 'File template wajib diupload',
                'kurvaS_file.file' => 'File tidak valid',
                'kurvaS_file.mimes' => 'File harus berformat Excel (.xlsx atau .xls)',
                'kurvaS_file.max' => 'Ukuran file maksimal 5MB',
            ], [
                'kurvaS_file' => 'file template',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->notifyValidationError($e);
            throw $e;
        }

        try {
            $jenisKapal = JenisKapal::findOrFail($this->jenisKapalId);
            $this->authorize('importKurvaSTemplate', $jenisKapal);

            $result = $service->importKurvaSTemplate($jenisKapal, $this->kurvaS_file);

            if ($result['success']) {
                $this->notifySuccess($result['message'] . ' Total work groups: ' . $result['work_groups_count']);
                $this->closeKurvaSImportModal();
                $this->loadData(); // Reload data to show imported data
                $this->dispatch('kurvas-imported');
            } else {
                $errorMessage = 'Import gagal:';
                foreach ($result['errors'] as $error) {
                    $errorMessage .= "\n• " . $error;
                }
                $this->notifyError($errorMessage);
            }
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            $this->notifyError('Anda tidak memiliki izin untuk import template Kurva S.');
        } catch (\Exception $e) {
            $this->notifyError('Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.master-data.kurva-s-management');
    }
}
