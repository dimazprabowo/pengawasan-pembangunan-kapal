<?php

namespace App\Livewire\Laporan;

use App\Enums\LaporanTipe;
use App\Jobs\ProcessLaporanFile;
use App\Livewire\Traits\HasNotification;
use App\Models\Cuaca;
use App\Models\JenisKapal;
use App\Models\Kelembaban;
use App\Models\Laporan;
use App\Services\LaporanService;
use App\Services\QueueStatusService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app', ['title' => 'Edit Laporan'])]
class LaporanEdit extends Component
{
    use AuthorizesRequests, HasNotification, WithFileUploads;

    public Laporan $laporan;

    public ?int $jenis_kapal_id = null;
    public string $judul = '';
    public string $tanggal_laporan = '';
    public ?string $isi = '';
    public ?string $catatan = '';
    public ?float $suhu = null;
    public ?int $cuaca_pagi_id = null;
    public ?int $kelembaban_pagi_id = null;
    public ?int $cuaca_siang_id = null;
    public ?int $kelembaban_siang_id = null;
    public ?int $cuaca_sore_id = null;
    public ?int $kelembaban_sore_id = null;

    public $file;

    // Delete file confirmation
    public bool $showDeleteFileModal = false;

    public function mount(Laporan $laporan): void
    {
        $this->authorize('update', $laporan);

        $this->laporan = $laporan;
        $this->jenis_kapal_id = $laporan->jenis_kapal_id;
        $this->judul = $laporan->judul;
        $this->tanggal_laporan = $laporan->tanggal_laporan->format('Y-m-d');
        $this->isi = $laporan->isi ?? '';
        $this->catatan = $laporan->catatan ?? '';
        
        if ($laporan->tipe->value === 'harian') {
            $this->suhu = $laporan->suhu;
            $this->cuaca_pagi_id = $laporan->cuaca_pagi_id;
            $this->kelembaban_pagi_id = $laporan->kelembaban_pagi_id;
            $this->cuaca_siang_id = $laporan->cuaca_siang_id;
            $this->kelembaban_siang_id = $laporan->kelembaban_siang_id;
            $this->cuaca_sore_id = $laporan->cuaca_sore_id;
            $this->kelembaban_sore_id = $laporan->kelembaban_sore_id;
        }
    }

    public function rules(): array
    {
        $rules = [
            'jenis_kapal_id' => 'required|exists:jenis_kapal,id',
            'judul' => 'required|string|max:255',
            'tanggal_laporan' => 'required|date',
            'isi' => 'nullable|string',
            'catatan' => 'nullable|string|max:1000',
            'file' => file_upload_validation_rule(),
        ];

        if ($this->laporan->tipe->value === 'harian') {
            $rules['suhu'] = 'nullable|numeric|min:-50|max:100';
            $rules['cuaca_pagi_id'] = 'nullable|exists:cuaca,id';
            $rules['kelembaban_pagi_id'] = 'nullable|exists:kelembaban,id';
            $rules['cuaca_siang_id'] = 'nullable|exists:cuaca,id';
            $rules['kelembaban_siang_id'] = 'nullable|exists:kelembaban,id';
            $rules['cuaca_sore_id'] = 'nullable|exists:cuaca,id';
            $rules['kelembaban_sore_id'] = 'nullable|exists:kelembaban,id';
        }

        return $rules;
    }

    public function validationAttributes(): array
    {
        $attributes = [
            'jenis_kapal_id' => 'jenis kapal',
            'judul' => 'judul laporan',
            'tanggal_laporan' => 'tanggal laporan',
            'isi' => 'isi laporan',
            'catatan' => 'catatan',
            'file' => 'file lampiran',
        ];

        if ($this->laporan->tipe->value === 'harian') {
            $attributes['suhu'] = 'suhu';
            $attributes['cuaca_pagi_id'] = 'cuaca pagi';
            $attributes['kelembaban_pagi_id'] = 'kelembaban pagi';
            $attributes['cuaca_siang_id'] = 'cuaca siang';
            $attributes['kelembaban_siang_id'] = 'kelembaban siang';
            $attributes['cuaca_sore_id'] = 'cuaca sore';
            $attributes['kelembaban_sore_id'] = 'kelembaban sore';
        }

        return $attributes;
    }

    public function confirmDeleteFile(): void
    {
        $this->showDeleteFileModal = true;
    }

    public function deleteFile(LaporanService $service): void
    {
        $this->authorize('update', $this->laporan);

        try {
            $service->removeFile($this->laporan);
            $this->laporan->refresh();
            $this->showDeleteFileModal = false;
            $this->notifySuccess('File berhasil dihapus.');
        } catch (\Exception $e) {
            $this->notifyError('Gagal menghapus file: ' . $e->getMessage());
        }
    }

    public function removeNewFile(): void
    {
        $this->file = null;
    }

    public function save(LaporanService $service): void
    {
        $this->authorize('update', $this->laporan);
        $this->validate();

        try {
            $data = [
                'jenis_kapal_id' => $this->jenis_kapal_id,
                'judul' => $this->judul,
                'tanggal_laporan' => $this->tanggal_laporan,
                'isi' => $this->isi ?: null,
                'catatan' => $this->catatan ?: null,
            ];

            if ($this->laporan->tipe->value === 'harian') {
                $data['suhu'] = $this->suhu;
                $data['cuaca_pagi_id'] = $this->cuaca_pagi_id;
                $data['kelembaban_pagi_id'] = $this->kelembaban_pagi_id;
                $data['cuaca_siang_id'] = $this->cuaca_siang_id;
                $data['kelembaban_siang_id'] = $this->kelembaban_siang_id;
                $data['cuaca_sore_id'] = $this->cuaca_sore_id;
                $data['kelembaban_sore_id'] = $this->kelembaban_sore_id;
            }

            $service->update($this->laporan, $data);

            // Handle new file upload via queue
            if ($this->file) {
                // Remove old file first
                if ($this->laporan->file_path) {
                    $service->removeFile($this->laporan);
                }

                $tempPath = 'laporan-temp/' . uniqid() . '_' . $this->file->getClientOriginalName();
                Storage::disk('local')->put($tempPath, file_get_contents($this->file->getRealPath()));

                // Set initial status as pending
                $this->laporan->update(['file_status' => 'pending']);

                ProcessLaporanFile::dispatch(
                    $this->laporan,
                    $tempPath,
                    $this->file->getClientOriginalName(),
                    $this->file->getSize(),
                );
            }

            $tipeLabel = $this->laporan->tipe->label();
            $message = "Laporan {$tipeLabel} berhasil diupdate!";
            if ($this->file) {
                $message .= ' File sedang diproses di background.';
            }

            session()->flash('notify', [
                'type' => 'success',
                'message' => $message,
            ]);

            $this->redirect(route('laporan.index'), navigate: true);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            $this->notifyError('Anda tidak memiliki izin untuk mengupdate laporan ini.');
        } catch (\Exception $e) {
            $this->notifyError('Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    public function render(QueueStatusService $queueStatusService)
    {
        $canViewAllJenisKapal = auth()->user()->can('laporan_view_all_jenis_kapal');
        
        $jenisKapalList = JenisKapal::with(['company', 'galangan'])
            ->active()
            ->when(!$canViewAllJenisKapal, function ($q) {
                $q->whereHas('company', function ($q) {
                    $q->where('id', auth()->user()->company_id);
                });
            })
            ->orderBy('nama')
            ->get();

        $cuacaList = Cuaca::active()->orderBy('nama')->get();
        $kelembabanList = Kelembaban::active()->orderBy('nama')->get();

        return view('livewire.laporan.laporan-edit', [
            'tipeEnum' => $this->laporan->tipe,
            'queueStatus' => $queueStatusService->getQueueStatusMessage(),
            'jenisKapalList' => $jenisKapalList,
            'cuacaList' => $cuacaList,
            'kelembabanList' => $kelembabanList,
        ]);
    }
}
