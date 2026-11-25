<?php

namespace App\Services;

use App\Models\LaporanBulanan;
use App\Models\LaporanHarian;
use App\Models\LaporanMingguan;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LaporanCompletenessService
{
    public function missing(LaporanMingguan|LaporanBulanan $laporan): array
    {
        $periode = app(KontrakPeriodeService::class);
        if ($laporan instanceof LaporanMingguan) {
            $mulai = $laporan->periode_mulai;
            $selesai = $laporan->periode_selesai;
        } else {
            $bulan = $periode->getBulanPeriode($laporan->dataKapal, (int) $laporan->bulan_ke);
            $mulai = $bulan['mulai'] ?? null;
            $selesai = $bulan['selesai'] ?? null;
        }
        if (! $mulai || ! $selesai || $mulai->gt($selesai)) {
            throw ValidationException::withMessages(['periode' => 'Periode laporan belum valid. Periksa pengaturan pelaksanaan dan periode laporan.']);
        }

        $missing = [];
        if ($laporan instanceof LaporanBulanan) {
            $available = LaporanMingguan::where('data_kapal_id', $laporan->data_kapal_id)
                ->whereBetween('minggu_ke', [$bulan['minggu_dari'], $bulan['minggu_sampai']])
                ->pluck('minggu_ke')->all();
            foreach (range($bulan['minggu_dari'], $bulan['minggu_sampai']) as $week) {
                if (! in_array($week, $available)) {
                    $range = $periode->getMingguPeriode($laporan->dataKapal, $week);
                    $missing[] = [
                        'type' => 'mingguan', 'value' => (string) $week, 'can_create' => true,
                        'label' => 'Laporan Mingguan ke-'.$week.' ('.$periode->formatRange($range['mulai'], $range['selesai']).')',
                    ];
                }
            }
        }

        $available = LaporanHarian::where('data_kapal_id', $laporan->data_kapal_id)
            ->where('tanggal_laporan', '>=', $mulai->toDateString())
            ->where('tanggal_laporan', '<', $selesai->copy()->addDay()->toDateString())
            ->pluck('tanggal_laporan')->map(fn ($date) => Carbon::parse($date)->toDateString())->flip();
        for ($date = $mulai->copy(); $date->lte($selesai); $date->addDay()) {
            if (! $available->has($date->toDateString())) {
                $missing[] = [
                    'type' => 'harian', 'value' => $date->toDateString(), 'can_create' => ! $date->isFuture(),
                    'label' => 'Laporan Harian '.$date->translatedFormat('d F Y'),
                ];
            }
        }

        return $missing;
    }

    public function prepareForGeneration(LaporanMingguan|LaporanBulanan $laporan): void
    {
        $missing = $this->missing($laporan);
        if ($missing) {
            throw ValidationException::withMessages(['laporan_sumber' => 'Laporan sumber belum lengkap: '.implode('; ', array_column($missing, 'label')).'.']);
        }

        $weeks = $laporan instanceof LaporanMingguan
            ? collect([$laporan])
            : app(LaporanBulananService::class)->getMingguanBulan($laporan);
        $daily = LaporanHarian::where('data_kapal_id', $laporan->data_kapal_id)
            ->where('tanggal_laporan', '>=', $weeks->min('periode_mulai')->toDateString())
            ->where('tanggal_laporan', '<', $weeks->max('periode_selesai')->copy()->addDay()->toDateString())
            ->get(['id', 'tanggal_laporan']);
        foreach ($weeks as $week) {
            $ids = $daily->filter(fn ($report) => $report->tanggal_laporan->betweenIncluded($week->periode_mulai, $week->periode_selesai))
                ->pluck('id')->all();
            $changes = DB::transaction(fn () => $week->laporanHarian()->syncWithoutDetaching($ids));
            if ($changes['attached'] && $laporan instanceof LaporanBulanan) {
                app(LaporanDocumentService::class)->clearDoc($week);
            }
        }
    }
}
