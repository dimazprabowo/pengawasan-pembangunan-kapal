<?php

namespace App\Http\Controllers;

use App\Models\Laporan;
use App\Models\LaporanLampiran;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LaporanFileController extends Controller
{
    public function download(Laporan $laporan): StreamedResponse
    {
        Gate::authorize('download', $laporan);

        if (!$laporan->file_path || !Storage::disk('local')->exists($laporan->file_path)) {
            abort(404, 'File tidak ditemukan.');
        }

        return Storage::disk('local')->download($laporan->file_path, $laporan->file_name);
    }

    public function preview(Laporan $laporan)
    {
        Gate::authorize('view', $laporan);

        if (!$laporan->file_path || !Storage::disk('local')->exists($laporan->file_path)) {
            abort(404, 'File tidak ditemukan.');
        }

        $extension = strtolower(pathinfo($laporan->file_name, PATHINFO_EXTENSION));
        $mimeTypes = [
            'pdf'  => 'application/pdf',
            'doc'  => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls'  => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ];

        $mime = $mimeTypes[$extension] ?? 'application/octet-stream';

        // PDF can be displayed inline in the browser
        if ($extension === 'pdf') {
            return response(Storage::disk('local')->get($laporan->file_path))
                ->header('Content-Type', $mime)
                ->header('Content-Disposition', 'inline; filename="' . $laporan->file_name . '"');
        }

        // For Word/Excel, use Google Docs Viewer or Office Online via a temporary public URL
        // We serve the file inline and let the Livewire component handle the preview via iframe
        return response(Storage::disk('local')->get($laporan->file_path))
            ->header('Content-Type', $mime)
            ->header('Content-Disposition', 'inline; filename="' . $laporan->file_name . '"');
    }

    public function downloadLampiran(Laporan $laporan, LaporanLampiran $lampiran): StreamedResponse
    {
        Gate::authorize('view', $laporan);

        if ($lampiran->laporan_id !== $laporan->id) {
            abort(404, 'Lampiran tidak ditemukan.');
        }

        if (!$lampiran->file_path || !Storage::disk('local')->exists($lampiran->file_path)) {
            abort(404, 'File tidak ditemukan.');
        }

        return Storage::disk('local')->download($lampiran->file_path, $lampiran->file_name);
    }

    public function downloadWord(Laporan $laporan): BinaryFileResponse
    {
        Gate::authorize('downloadWord', $laporan);

        if (!$laporan->isDocCompleted() || !$laporan->doc_path) {
            abort(404, 'Dokumen belum tersedia.');
        }

        $fullPath = storage_path('app/' . $laporan->doc_path);

        if (!file_exists($fullPath)) {
            abort(404, 'File dokumen tidak ditemukan di server.');
        }

        $downloadName = $laporan->doc_name ?? 'laporan-harian.docx';

        return response()->download($fullPath, $downloadName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ]);
    }

    public function previewLampiran(Laporan $laporan, LaporanLampiran $lampiran)
    {
        Gate::authorize('view', $laporan);

        // Debug logging
        \Log::info('Preview Lampiran Debug:', [
            'laporan_id' => $laporan->id,
            'lampiran_id' => $lampiran->id,
            'lampiran_laporan_id' => $lampiran->laporan_id,
            'file_path' => $lampiran->file_path,
            'file_exists' => $lampiran->file_path ? Storage::disk('local')->exists($lampiran->file_path) : false,
        ]);

        if ($lampiran->laporan_id !== $laporan->id) {
            abort(404, 'Lampiran tidak ditemukan.');
        }

        if (!$lampiran->file_path || !Storage::disk('local')->exists($lampiran->file_path)) {
            abort(404, 'File tidak ditemukan.');
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
}
