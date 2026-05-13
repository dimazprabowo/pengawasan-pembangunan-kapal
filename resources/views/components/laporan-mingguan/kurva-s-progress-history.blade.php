@props([
    'workGroups' => [],
    'progressHistory' => [],
    'fullProgressHistory' => null, // Pre-calculated full history from server (includes current week)
])

<x-laporan-mingguan.riwayat-progress-card
    :workGroups="$workGroups"
    :progressHistory="$progressHistory"
    :fullProgressHistory="$fullProgressHistory"
/>
