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
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app', ['title' => 'Tambah Laporan'])]
class LaporanCreate extends Component
{
    use AuthorizesRequests, HasNotification, WithFileUploads;

    public string $tipe = 'harian';

    public ?int $jenis_kapal_id = null;

    public array $items = [];

    public array $files = [];

    // Delete card confirmation modal
    public bool $showDeleteCardModal = false;
    public ?int $deletingCardIndex = null;

    public function mount(string $tipe): void
    {
        $this->authorize('create', Laporan::class);

        if (!in_array($tipe, LaporanTipe::values())) {
            abort(404);
        }

        $this->tipe = $tipe;
        $this->jenis_kapal_id = session('laporan_jenis_kapal_id');
        $this->addItem();
    }

    public function addItem(): void
    {
        $this->items[] = [
            'judul' => '',
            'tanggal_laporan' => now()->format('Y-m-d'),
            'isi' => '',
            'catatan' => '',
            'suhu' => '',
            'cuaca_pagi_id' => null,
            'kelembaban_pagi_id' => null,
            'cuaca_siang_id' => null,
            'kelembaban_siang_id' => null,
            'cuaca_sore_id' => null,
            'kelembaban_sore_id' => null,
        ];
    }

    public function confirmRemoveItem(int $index): void
    {
        if (count($this->items) <= 1) {
            $this->notifyWarning('Minimal harus ada 1 laporan.');
            return;
        }

        $this->deletingCardIndex = $index;
        $this->showDeleteCardModal = true;
    }

    public function removeItem(): void
    {
        if ($this->deletingCardIndex === null) {
            return;
        }

        $index = $this->deletingCardIndex;

        unset($this->items[$index]);
        unset($this->files[$index]);
        $this->items = array_values($this->items);
        $this->files = array_values($this->files);

        $this->showDeleteCardModal = false;
        $this->deletingCardIndex = null;
    }

    public function removeFile(int $index): void
    {
        unset($this->files[$index]);
    }

    public function rules(): array
    {
        $rules = [
            'jenis_kapal_id' => 'required|exists:jenis_kapal,id',
            'items' => 'required|array|min:1',
            'items.*.judul' => 'required|string|max:255',
            'items.*.tanggal_laporan' => 'required|date',
            'items.*.isi' => 'nullable|string',
            'items.*.catatan' => 'nullable|string|max:1000',
            'files.*' => file_upload_validation_rule(),
        ];

        if ($this->tipe === 'harian') {
            $rules['items.*.suhu'] = 'nullable|numeric|min:-50|max:100';
            $rules['items.*.cuaca_pagi_id'] = 'nullable|exists:cuaca,id';
            $rules['items.*.kelembaban_pagi_id'] = 'nullable|exists:kelembaban,id';
            $rules['items.*.cuaca_siang_id'] = 'nullable|exists:cuaca,id';
            $rules['items.*.kelembaban_siang_id'] = 'nullable|exists:kelembaban,id';
            $rules['items.*.cuaca_sore_id'] = 'nullable|exists:cuaca,id';
            $rules['items.*.kelembaban_sore_id'] = 'nullable|exists:kelembaban,id';
        }

        return $rules;
    }

    public function validationAttributes(): array
    {
        $attributes = [
            'jenis_kapal_id' => 'jenis kapal',
        ];
        
        foreach ($this->items as $index => $item) {
            $num = $index + 1;
            $attributes["items.{$index}.judul"] = "judul laporan #{$num}";
            $attributes["items.{$index}.tanggal_laporan"] = "tanggal laporan #{$num}";
            $attributes["items.{$index}.isi"] = "isi laporan #{$num}";
            $attributes["items.{$index}.catatan"] = "catatan laporan #{$num}";
            $attributes["files.{$index}"] = "file laporan #{$num}";
            
            if ($this->tipe === 'harian') {
                $attributes["items.{$index}.suhu"] = "suhu laporan #{$num}";
                $attributes["items.{$index}.cuaca_pagi_id"] = "cuaca pagi laporan #{$num}";
                $attributes["items.{$index}.kelembaban_pagi_id"] = "kelembaban pagi laporan #{$num}";
                $attributes["items.{$index}.cuaca_siang_id"] = "cuaca siang laporan #{$num}";
                $attributes["items.{$index}.kelembaban_siang_id"] = "kelembaban siang laporan #{$num}";
                $attributes["items.{$index}.cuaca_sore_id"] = "cuaca sore laporan #{$num}";
                $attributes["items.{$index}.kelembaban_sore_id"] = "kelembaban sore laporan #{$num}";
            }
        }
        return $attributes;
    }

    public function save(LaporanService $service): void
    {
        $this->authorize('create', Laporan::class);
        $this->validate();

        try {
            $userId = auth()->id();
            $dataItems = [];

            foreach ($this->items as $item) {
                $itemData = [
                    'user_id' => $userId,
                    'jenis_kapal_id' => $this->jenis_kapal_id,
                    'tipe' => $this->tipe,
                    'judul' => $item['judul'],
                    'tanggal_laporan' => $item['tanggal_laporan'],
                    'isi' => $item['isi'] ?: null,
                    'catatan' => $item['catatan'] ?: null,
                ];

                if ($this->tipe === 'harian') {
                    $itemData['suhu'] = $item['suhu'] ?: null;
                    $itemData['cuaca_pagi_id'] = $item['cuaca_pagi_id'] ?: null;
                    $itemData['kelembaban_pagi_id'] = $item['kelembaban_pagi_id'] ?: null;
                    $itemData['cuaca_siang_id'] = $item['cuaca_siang_id'] ?: null;
                    $itemData['kelembaban_siang_id'] = $item['kelembaban_siang_id'] ?: null;
                    $itemData['cuaca_sore_id'] = $item['cuaca_sore_id'] ?: null;
                    $itemData['kelembaban_sore_id'] = $item['kelembaban_sore_id'] ?: null;
                }

                $dataItems[] = $itemData;
            }

            $createdLaporans = $service->createMany($dataItems);

            // Dispatch file processing jobs for items that have files
            foreach ($createdLaporans as $index => $laporan) {
                if (isset($this->files[$index]) && $this->files[$index]) {
                    $file = $this->files[$index];
                    $tempPath = 'laporan-temp/' . uniqid() . '_' . $file->getClientOriginalName();
                    Storage::disk('local')->put($tempPath, file_get_contents($file->getRealPath()));

                    // Set initial status as pending
                    $laporan->update(['file_status' => 'pending']);

                    ProcessLaporanFile::dispatch(
                        $laporan,
                        $tempPath,
                        $file->getClientOriginalName(),
                        $file->getSize(),
                    );
                }
            }

            $count = count($dataItems);
            $hasFiles = collect($this->files)->filter()->isNotEmpty();
            $tipeLabel = LaporanTipe::from($this->tipe)->label();

            $message = "{$count} laporan {$tipeLabel} berhasil ditambahkan!";
            if ($hasFiles) {
                $message .= ' File sedang diproses di background.';
            }

            session()->flash('notify', [
                'type' => 'success',
                'message' => $message,
            ]);

            $this->redirect(route('laporan.index'), navigate: true);
        } catch (\Exception $e) {
            $this->notifyError('Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    public function render()
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

        return view('livewire.laporan.laporan-create', [
            'tipeEnum' => LaporanTipe::from($this->tipe),
            'jenisKapalList' => $jenisKapalList,
            'cuacaList' => $cuacaList,
            'kelembabanList' => $kelembabanList,
        ]);
    }
}
