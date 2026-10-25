<?php

namespace App\Services;

use App\Models\DataKapal;
use App\Models\LaporanBulanan;
use App\Models\LaporanMingguan;
use Carbon\Carbon;

/**
 * Single source of truth untuk penomoran periode laporan berdasarkan kontrak
 * (tanggal mulai + durasi hari kalender) pada data_kapal.
 *
 * - Minggu = "minggu proyek": blok 7 hari dari tanggal mulai kontrak.
 *   Sisa hari < 7 di akhir kontrak digabung ke minggu terakhir
 *   (minggu terakhir maksimal 13 hari), bukan dijadikan minggu baru.
 * - Bulan  = bulan kalender tempat MAYORITAS hari suatu minggu berada.
 *   Minggu 3 hari Jan + 4 hari Feb → masuk Februari.
 *   Bulan tengah (bukan pertama & terakhir) minimal menampung 4 minggu:
 *   bila kurang, minggu terdepan bulan berikutnya ditarik mundur.
 *   Bulan pertama & terakhir bebas (boleh 1 minggu); bulan tanpa minggu
 *   sama sekali dilewati (tidak muncul di daftar).
 */
class KontrakPeriodeService
{
    public function hasKontrak(DataKapal $dataKapal): bool
    {
        return $dataKapal->hasKontrak();
    }

    /**
     * Simpan data kontrak pada data kapal. Kedua field harus terisi bersama
     * atau sama-sama kosong (menghapus kontrak).
     */
    public function saveKontrak(DataKapal $dataKapal, ?string $tanggalMulai, ?int $durasiHari): void
    {
        $dataKapal->update([
            'tanggal_mulai_kontrak' => $tanggalMulai ?: null,
            'durasi_kontrak_hari' => $durasiHari ?: null,
        ]);
    }

    public function getTanggalSelesai(DataKapal $dataKapal): ?Carbon
    {
        return $dataKapal->tanggal_selesai_kontrak;
    }

    /**
     * Total minggu proyek = floor(durasi_hari / 7), minimal 1.
     * Sisa pembagian (< 7 hari) digabung ke minggu terakhir
     * sehingga minggu terakhir maksimal 13 hari. 0 jika kontrak belum diatur.
     */
    public function getTotalMinggu(DataKapal $dataKapal): int
    {
        if (! $dataKapal->hasKontrak()) {
            return 0;
        }

        return max(1, intdiv($dataKapal->durasi_kontrak_hari, 7));
    }

    /**
     * Total bulan kalender yang tersentuh rentang kontrak. 0 jika belum diatur.
     */
    public function getTotalBulan(DataKapal $dataKapal): int
    {
        return count($this->getBulanList($dataKapal));
    }

    /**
     * Rentang tanggal satu minggu proyek (inklusif). Null jika di luar kontrak.
     *
     * @return array{mulai: Carbon, selesai: Carbon}|null
     */
    public function getMingguPeriode(DataKapal $dataKapal, int $mingguKe): ?array
    {
        $total = $this->getTotalMinggu($dataKapal);
        if ($mingguKe < 1 || $mingguKe > $total) {
            return null;
        }

        $mulai = $dataKapal->tanggal_mulai_kontrak->copy()->addDays(($mingguKe - 1) * 7);
        // Minggu terakhir menyerap sisa hari (< 7) → berakhir tepat di akhir kontrak.
        $selesai = $mingguKe === $total
            ? $dataKapal->tanggal_selesai_kontrak->copy()
            : $mulai->copy()->addDays(6);

        return ['mulai' => $mulai, 'selesai' => $selesai];
    }

    /**
     * Daftar semua minggu proyek beserta rentang tanggalnya.
     *
     * @return array<int, array{minggu_ke: int, mulai: Carbon, selesai: Carbon, total_hari: int, label: string}>
     */
    public function getMingguList(DataKapal $dataKapal): array
    {
        if (! $dataKapal->hasKontrak()) {
            return [];
        }

        $list = [];
        foreach (range(1, $this->getTotalMinggu($dataKapal)) as $mingguKe) {
            $periode = $this->getMingguPeriode($dataKapal, $mingguKe);
            $list[] = $periode + [
                'minggu_ke' => $mingguKe,
                'total_hari' => (int) $periode['mulai']->diffInDays($periode['selesai']) + 1,
                'label' => $this->formatRange($periode['mulai'], $periode['selesai']),
            ];
        }

        return $list;
    }

    /**
     * Opsi minggu untuk dropdown laporan mingguan.
     * Minggu yang sudah memiliki laporan dikecualikan (kecuali $excludeMingguKe
     * untuk mode edit). Label menyertakan rentang tanggal.
     */
    public function getMingguOptions(DataKapal $dataKapal, ?int $excludeMingguKe = null): array
    {
        if (! $dataKapal->hasKontrak()) {
            return [];
        }

        $usedWeeks = LaporanMingguan::where('data_kapal_id', $dataKapal->id)
            ->whereNotNull('minggu_ke')
            ->when($excludeMingguKe, fn ($q) => $q->where('minggu_ke', '!=', $excludeMingguKe))
            ->pluck('minggu_ke')
            ->toArray();

        return collect($this->getMingguList($dataKapal))
            ->reject(fn ($m) => in_array($m['minggu_ke'], $usedWeeks))
            ->map(fn ($m) => [
                'value' => $m['minggu_ke'],
                'label' => 'Minggu ke-'.$m['minggu_ke'].' ('.$m['label'].')',
            ])
            ->values()
            ->toArray();
    }

    /**
     * Opsi bulan untuk dropdown laporan bulanan.
     * Bulan yang sudah memiliki laporan dikecualikan (kecuali $excludeBulanKe
     * untuk mode edit). Label menyertakan nama bulan kalender & rentang tanggal.
     */
    public function getBulanOptions(DataKapal $dataKapal, ?int $excludeBulanKe = null): array
    {
        if (! $dataKapal->hasKontrak()) {
            return [];
        }

        $usedMonths = LaporanBulanan::where('data_kapal_id', $dataKapal->id)
            ->whereNotNull('bulan_ke')
            ->when($excludeBulanKe, fn ($q) => $q->where('bulan_ke', '!=', $excludeBulanKe))
            ->pluck('bulan_ke')
            ->toArray();

        return collect($this->getBulanList($dataKapal))
            ->reject(fn ($b) => in_array($b['bulan_ke'], $usedMonths))
            ->map(fn ($b) => [
                'value' => $b['bulan_ke'],
                'label' => 'Bulan ke-'.$b['bulan_ke'].' ('.$b['label'].', '.$this->formatRange($b['mulai'], $b['selesai']).')',
            ])
            ->values()
            ->toArray();
    }

    /**
     * Satu entri periode bulan berdasarkan nomor bulan ke-. Null jika di luar kontrak.
     *
     * @return array{bulan_ke: int, mulai: Carbon, selesai: Carbon, minggu_dari: int, minggu_sampai: int, total_minggu: int, total_hari: int, label: string}|null
     */
    public function getBulanPeriode(DataKapal $dataKapal, int $bulanKe): ?array
    {
        return collect($this->getBulanList($dataKapal))->firstWhere('bulan_ke', $bulanKe);
    }

    /**
     * Daftar periode bulan — setiap minggu masuk ke bulan kalender yang
     * memuat MAYORITAS harinya (mis. minggu 3 hari Jan + 4 hari Feb → Feb).
     * Bulan tanpa minggu dilewati. Bulan TENGAH (bukan pertama & terakhir)
     * minimal menampung 4 minggu: bila kurang, minggu terdepan bulan
     * berikutnya ditarik mundur hingga cukup (berantai ke bulan selanjutnya).
     * Bulan pertama & terakhir bebas berapapun jumlah minggunya.
     *
     * @return array<int, array{bulan_ke: int, mulai: Carbon, selesai: Carbon, minggu_dari: int, minggu_sampai: int, total_minggu: int, total_hari: int, label: string}>
     */
    public function getBulanList(DataKapal $dataKapal): array
    {
        $mingguList = $this->getMingguList($dataKapal);
        if (empty($mingguList)) {
            return [];
        }

        // Pass 1 — kelompokkan minggu berurutan ke bulan mayoritasnya.
        $groups = [];
        foreach ($mingguList as $week) {
            $key = $this->getBulanMayoritasKey($week['mulai'], $week['selesai']);
            if (empty($groups) || $groups[array_key_last($groups)]['key'] !== $key) {
                $groups[] = ['key' => $key, 'weeks' => []];
            }
            $groups[array_key_last($groups)]['weeks'][] = $week;
        }

        // Pass 2 — bulan tengah wajib ≥ 4 minggu: tarik minggu terdepan dari
        // bulan berikutnya; bulan yang kehabisan minggu dihapus (dilewati).
        for ($i = 1; $i < count($groups) - 1; $i++) {
            while (count($groups[$i]['weeks']) < 4 && isset($groups[$i + 1])) {
                $groups[$i]['weeks'][] = array_shift($groups[$i + 1]['weeks']);
                if (empty($groups[$i + 1]['weeks'])) {
                    array_splice($groups, $i + 1, 1);
                }
            }
        }

        $list = [];
        foreach ($groups as $bulanKe => $group) {
            $first = $group['weeks'][0];
            $last = $group['weeks'][count($group['weeks']) - 1];

            $list[] = [
                'bulan_ke' => $bulanKe + 1,
                'mulai' => $first['mulai'],
                'selesai' => $last['selesai'],
                'minggu_dari' => $first['minggu_ke'],
                'minggu_sampai' => $last['minggu_ke'],
                'total_minggu' => count($group['weeks']),
                'total_hari' => (int) $first['mulai']->diffInDays($last['selesai']) + 1,
                'label' => Carbon::parse($group['key'].'-01')->translatedFormat('F Y'),
            ];
        }

        return $list;
    }

    /**
     * Key bulan ('Y-m') yang memuat mayoritas hari sebuah minggu.
     * Minggu maksimal 13 hari sehingga selalu menjangkau ≤ 2 bulan kalender.
     */
    private function getBulanMayoritasKey(Carbon $mulai, Carbon $selesai): string
    {
        $totalHari = (int) $mulai->diffInDays($selesai) + 1;
        $hariDiBulanMulai = min($mulai->daysInMonth - $mulai->day + 1, $totalHari);
        $hariDiBulanBerikut = $totalHari - $hariDiBulanMulai;

        return $hariDiBulanMulai >= $hariDiBulanBerikut
            ? $mulai->format('Y-m')
            : $selesai->format('Y-m');
    }

    /**
     * Map minggu_ke → bulan_ke mengikuti aturan pengelompokan bulan.
     * Dipakai untuk membagi rencana bulanan ke minggu-minggunya.
     *
     * @return array<int, int>
     */
    public function getMingguBulanMap(DataKapal $dataKapal): array
    {
        $map = [];
        foreach ($this->getBulanList($dataKapal) as $b) {
            for ($m = $b['minggu_dari']; $m <= $b['minggu_sampai']; $m++) {
                $map[$m] = $b['bulan_ke'];
            }
        }

        return $map;
    }

    /**
     * Cari nomor minggu proyek yang memuat sebuah tanggal. Null jika di luar kontrak.
     */
    public function getMingguKeFromDate(DataKapal $dataKapal, Carbon $tanggal): ?int
    {
        if (! $dataKapal->hasKontrak()) {
            return null;
        }

        if ($tanggal->lt($dataKapal->tanggal_mulai_kontrak) || $tanggal->gt($dataKapal->tanggal_selesai_kontrak)) {
            return null;
        }

        // Tanggal pada "ekor" minggu terakhir (gabungan sisa hari) memetakan
        // melewati total minggu — clamp ke minggu terakhir.
        return min(
            intdiv((int) $dataKapal->tanggal_mulai_kontrak->diffInDays($tanggal), 7) + 1,
            $this->getTotalMinggu($dataKapal)
        );
    }

    /**
     * Label rentang tanggal ringkas ("01 - 07 Mar 2025", "28 Feb - 06 Mar 2025").
     */
    public function formatRange(Carbon $mulai, Carbon $selesai): string
    {
        if ($mulai->isSameMonth($selesai)) {
            return $mulai->format('d').' - '.$selesai->translatedFormat('d M Y');
        }

        if ($mulai->isSameYear($selesai)) {
            return $mulai->translatedFormat('d M').' - '.$selesai->translatedFormat('d M Y');
        }

        return $mulai->translatedFormat('d M Y').' - '.$selesai->translatedFormat('d M Y');
    }
}
