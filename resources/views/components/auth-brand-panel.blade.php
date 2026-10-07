<!-- Left Side - Branding (dipakai semua halaman guest via layouts.guest) -->
<div class="hidden lg:flex lg:w-1/2 xl:w-2/5 bg-gradient-to-br from-slate-900 via-blue-950 to-slate-900 dark:from-gray-950 dark:via-slate-900 dark:to-gray-950 p-8 lg:p-10 flex-col justify-between relative overflow-hidden">
    <!-- Decorative Background Elements (Animated Shipyard Blueprint) -->
    <x-shipyard-animation class="order-2 max-w-xs mx-auto my-8 lg:my-10" />

    <div class="relative z-10 order-1">
        <div class="flex items-center space-x-3 mb-8">
            <div class="w-14 h-14 bg-white rounded-xl flex items-center justify-center shadow-lg p-1.5 overflow-hidden">
                <img src="{{ email_logo_url() }}" alt="BKI Logo" class="w-full h-full object-contain rounded-lg">
            </div>
            <div class="text-white">
                <h1 class="text-2xl lg:text-3xl font-bold">{{ config('app.name', 'SIMPRO') }}</h1>
                <p class="text-sm text-blue-100">PT. Biro Klasifikasi Indonesia</p>
            </div>
        </div>
        <div class="space-y-4 max-w-lg">
            <p class="text-xs font-semibold uppercase tracking-widest text-cyan-300">Pengawasan Pembangunan Kapal</p>
            <h2 class="text-3xl font-semibold text-white leading-tight tracking-tight">
                Setiap tahap terpantau.<br>Setiap progres tercatat.
            </h2>
            <p class="text-sm text-slate-300 leading-relaxed">
                Sistem informasi pengawasan pembangunan kapal — pantau progres konstruksi di galangan melalui laporan berkala dan Kurva-S.
            </p>
        </div>
    </div>

    <div class="relative z-10 order-3 space-y-3 text-sm">
        <div class="flex items-start space-x-4 text-blue-50">
            <div class="flex-shrink-0 w-8 h-8 bg-blue-500/30 rounded-lg flex items-center justify-center">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                </svg>
            </div>
            <div>
                <h3 class="font-semibold text-white mb-1">Laporan Harian & Mingguan</h3>
                <p class="text-sm text-blue-200">Pencatatan progres pembangunan dengan lampiran foto & dokumen</p>
            </div>
        </div>
        <div class="flex items-start space-x-4 text-blue-50">
            <div class="flex-shrink-0 w-8 h-8 bg-blue-500/30 rounded-lg flex items-center justify-center">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3v15a3 3 0 003 3h15M7 15l4-6 3 3 5-8"/>
                </svg>
            </div>
            <div>
                <h3 class="font-semibold text-white mb-1">Kurva-S Progress</h3>
                <p class="text-sm text-blue-200">Perbandingan rencana vs realisasi pembangunan per minggu</p>
            </div>
        </div>
        <div class="flex items-start space-x-4 text-blue-50">
            <div class="flex-shrink-0 w-8 h-8 bg-blue-500/30 rounded-lg flex items-center justify-center">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3.5 14.5h17l-1.8 5H5.3l-1.8-5z"/>
                    <path d="M15 14.5V9.5h3.5v5"/>
                    <path d="M16.25 9.5V8h1.5v1.5"/>
                    <path d="M5.5 14.5v-2h4v2"/>
                    <path d="M2.5 21.5h19"/>
                </svg>
            </div>
            <div>
                <h3 class="font-semibold text-white mb-1">Kapal, Galangan & Perusahaan</h3>
                <p class="text-sm text-blue-200">Master data jenis kapal dan lokasi pembangunan terpusat</p>
            </div>
        </div>
    </div>

    <div class="relative z-10 order-4 mt-8 pt-4 border-t border-white/10 text-slate-400 text-xs">
        <p>&copy; {{ date('Y') }} {{ config('app.name', 'SIMPRO') }}. Hak cipta dilindungi undang-undang.</p>
    </div>
</div>
