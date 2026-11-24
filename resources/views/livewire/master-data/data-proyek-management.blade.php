<div>
    @php
        $tabs = [
            'penandatangan' => 'Penandatangan',
            'drawing' => 'Drawing Register',
            'vendor' => 'Vendor',
            'material' => 'Material',
            'pengujian' => 'Pengujian',
            'rapat' => 'Rapat',
        ];
    @endphp

    {{-- Header --}}
    <div class="mb-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div class="flex items-center gap-2 min-w-0">
                <a href="{{ route('master-data.data-kapal') }}" wire:navigate
                    x-data="{ loading: false }" x-on:click="loading = true"
                    x-bind:class="loading ? 'opacity-75 pointer-events-none' : ''"
                    class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 transition-colors"
                    title="Kembali ke Data Proyek">
                    <svg x-show="!loading" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    <svg x-show="loading" x-cloak class="animate-spin w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </a>
                <div class="min-w-0">
                    <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Data Umum</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400 truncate">
                        {{ $dataKapal->nama }}{{ $dataKapal->owner ? ' · '.$dataKapal->owner->name : '' }} — register drawing, vendor, material, pengujian, rapat &amp; penandatangan untuk laporan bulanan
                    </p>
                </div>
            </div>
            @can('laporan_view')
                <a href="{{ route('laporan-bulanan.index', $dataKapal) }}" wire:navigate
                    x-data="{ loading: false }" x-on:click="loading = true"
                    x-bind:class="loading ? 'opacity-75 pointer-events-none' : ''"
                    class="inline-flex items-center justify-center font-medium rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 dark:focus:ring-offset-gray-800 bg-blue-600 hover:bg-blue-700 text-white focus:ring-blue-500 px-3 py-2 text-sm gap-1.5 flex-shrink-0 whitespace-nowrap">
                    <svg x-show="!loading" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-6a2 2 0 012-2h2a2 2 0 012 2v6m-6 0h6m-9 4h12a2 2 0 002-2V5a2 2 0 00-2-2H6a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                    </svg>
                    <svg x-show="loading" x-cloak class="animate-spin w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Laporan Bulanan
                </a>
            @endcan
        </div>
    </div>

    {{-- Tab Navigation --}}
    <div class="mb-4 flex flex-wrap gap-1 bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-1">
        @foreach($tabs as $key => $label)
            <button type="button" wire:click="setTab('{{ $key }}')" wire:target="setTab('{{ $key }}')" wire:loading.attr="disabled"
                class="flex-1 min-w-[120px] px-4 py-2 text-sm font-medium rounded-md transition-colors disabled:opacity-60
                    {{ $tab === $key
                        ? 'bg-blue-600 text-white shadow-sm'
                        : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700' }}">
                <span class="inline-flex items-center justify-center gap-1.5">
                    {{ $label }}
                    <svg wire:loading wire:target="setTab('{{ $key }}')" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </span>
            </button>
        @endforeach
    </div>

    @if($tab === 'drawing')
        <livewire:master-data.drawing-register-management :data-kapal="$dataKapal" :key="'drawing-register-'.$dataKapal->id" />
    @elseif($tab === 'vendor')
        <livewire:master-data.project-vendor-management :data-kapal="$dataKapal" :key="'project-vendor-'.$dataKapal->id" />
    @elseif($tab === 'material')
        <livewire:master-data.project-material-management :data-kapal="$dataKapal" :key="'project-material-'.$dataKapal->id" />
    @elseif($tab === 'pengujian')
        <livewire:master-data.project-pengujian-management :data-kapal="$dataKapal" :key="'project-pengujian-'.$dataKapal->id" />
    @elseif($tab === 'rapat')
        <livewire:master-data.project-rapat-management :data-kapal="$dataKapal" :key="'project-rapat-'.$dataKapal->id" />
    @elseif($tab === 'penandatangan')
        <livewire:master-data.project-penandatangan-management :data-kapal="$dataKapal" :key="'project-penandatangan-'.$dataKapal->id" />
    @endif
</div>
