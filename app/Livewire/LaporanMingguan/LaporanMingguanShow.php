<?php

namespace App\Livewire\LaporanMingguan;

use App\Livewire\Traits\HasNotification;
use App\Models\LaporanLampiran;
use App\Models\LaporanMingguan;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app', ['title' => 'Detail Laporan Mingguan'])]
class LaporanMingguanShow extends Component
{
    use AuthorizesRequests, HasNotification;

    public LaporanMingguan $laporan;

    // Lampiran Harian Modal
    public bool $showLampiranModal = false;
    public array $lampiranHarianList = [];
    public bool $loadingLampiran = false;
    public bool $showPreviewModal = false;
    public ?int $previewLampiranId = null;

    public function mount(LaporanMingguan $laporanMingguan): void
    {
        $this->authorize('view', $laporanMingguan);
        $this->loadLaporan($laporanMingguan);
    }

    private function loadLaporan(LaporanMingguan $laporanMingguan): void
    {
        $this->laporan = $laporanMingguan->load([
            'user',
            'jenisKapal.company',
            'jenisKapal.galangan',
            'laporanHarian' => function ($query) {
                $query->orderBy('tanggal_laporan', 'asc');
            },
            'laporanHarian.lampiran',
            'laporanHarian.personel',
            'laporanHarian.peralatan',
            'laporanHarian.consumable',
            'laporanHarian.aktivitas',
            'laporanHarian.cuacaPagi',
            'laporanHarian.kelembabanPagi',
            'laporanHarian.cuacaSiang',
            'laporanHarian.kelembabanSiang',
            'laporanHarian.cuacaSore',
            'laporanHarian.kelembabanSore',
            'lampiran',
        ]);
        
        // Force reload laporanHarian to ensure correct count
        $this->laporan->load('laporanHarian');
        
        // Load lampiran list if periode exists
        if ($this->laporan->periode_mulai && $this->laporan->periode_selesai) {
            $this->loadLampiranHarian();
        }
    }

    public function openLampiranModal(): void
    {
        $this->showLampiranModal = true;
        $this->loadLampiranHarian();
    }

    public function closeLampiranModal(): void
    {
        $this->showLampiranModal = false;
    }

    private function loadLampiranHarian(): void
    {
        $this->loadingLampiran = true;

        $query = LaporanLampiran::with(['laporanHarian'])
            ->whereHas('laporanHarian', function ($q) {
                $q->whereBetween('tanggal_laporan', [$this->laporan->periode_mulai, $this->laporan->periode_selesai]);
                
                if ($this->laporan->jenis_kapal_id) {
                    $q->where('jenis_kapal_id', $this->laporan->jenis_kapal_id);
                }
            })
            ->where('file_status', 'completed')
            ->orderBy('created_at', 'desc');

        $this->lampiranHarianList = $query->get()->map(function ($item) {
            $extension = strtolower(pathinfo($item->file_name, PATHINFO_EXTENSION));
            $isImage = in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
            
            return [
                'id' => $item->id,
                'file_name' => $item->file_name,
                'file_size_formatted' => number_format($item->file_size / 1024, 1) . ' KB',
                'keterangan' => $item->keterangan,
                'extension' => $extension,
                'is_image' => $isImage,
                'laporan_tanggal' => $item->laporanHarian->tanggal_laporan->format('d M Y'),
                'is_selected' => $this->laporan->lampiran->contains('id', $item->id),
            ];
        })->toArray();

        $this->loadingLampiran = false;
    }

    public function getLampiranPreview(int $lampiranId): ?string
    {
        $lampiran = LaporanLampiran::find($lampiranId);
        
        if (!$lampiran || !$lampiran->file_path || !Storage::disk('local')->exists($lampiran->file_path)) {
            return null;
        }

        if (!$lampiran->isImage()) {
            return null;
        }

        return $this->getLampiranImageDataUrl($lampiran);
    }

    private function getLampiranImageDataUrl(LaporanLampiran $lampiran): ?string
    {
        if (!$lampiran->file_path || !Storage::disk('local')->exists($lampiran->file_path)) {
            return null;
        }

        $extension = strtolower(pathinfo($lampiran->file_name, PATHINFO_EXTENSION));
        $mimeTypes = [
            'png'  => 'image/png',
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif'  => 'image/gif',
            'webp' => 'image/webp',
        ];

        if (!isset($mimeTypes[$extension])) {
            return null;
        }

        $fileContent = Storage::disk('local')->get($lampiran->file_path);
        $base64 = base64_encode($fileContent);
        $mime = $mimeTypes[$extension];

        return "data:{$mime};base64,{$base64}";
    }

    public function previewLampiranHarian(int $lampiranId): void
    {
        $this->previewLampiranId = $lampiranId;
        $this->showPreviewModal = true;
    }

    public function closePreviewModal(): void
    {
        $this->showPreviewModal = false;
        $this->previewLampiranId = null;
    }

    public function getPreviewLampiranProperty()
    {
        if (!$this->previewLampiranId) {
            return null;
        }
        return LaporanLampiran::find($this->previewLampiranId);
    }

    public function getPreviewLampiranImageUrlProperty(): ?string
    {
        if (!$this->previewLampiran) {
            return null;
        }

        return $this->getLampiranImageDataUrl($this->previewLampiran);
    }

    public function downloadLampiran(int $lampiranId)
    {
        $lampiran = LaporanLampiran::find($lampiranId);

        if (!$lampiran || !$lampiran->file_path || !Storage::disk('local')->exists($lampiran->file_path)) {
            $this->notifyError('File tidak ditemukan.');
            return;
        }

        return response()->download(Storage::disk('local')->path($lampiran->file_path), $lampiran->file_name);
    }

    public function previewLampiran(int $lampiranId)
    {
        $lampiran = LaporanLampiran::find($lampiranId);

        if (!$lampiran || !$lampiran->file_path || !Storage::disk('local')->exists($lampiran->file_path)) {
            $this->notifyError('File tidak ditemukan.');
            return;
        }

        $extension = strtolower(pathinfo($lampiran->file_name, PATHINFO_EXTENSION));
        $mimeTypes = [
            'pdf'  => 'application/pdf',
            'doc'  => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls'  => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'png'  => 'image/png',
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif'  => 'image/gif',
            'webp' => 'image/webp',
        ];

        $mime = $mimeTypes[$extension] ?? 'application/octet-stream';

        return response(Storage::disk('local')->get($lampiran->file_path))
            ->header('Content-Type', $mime)
            ->header('Content-Disposition', 'inline; filename="' . $lampiran->file_name . '"');
    }

    public function render()
    {
        return view('livewire.laporan-mingguan.laporan-mingguan-show');
    }
}
