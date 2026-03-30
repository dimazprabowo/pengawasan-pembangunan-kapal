<nav class="sticky top-0 z-40 bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 shadow-sm">
    <div class="px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">
            {{-- Left Side: Burger + Logo (navbar mode) --}}
            <div class="flex items-center space-x-3">
                {{-- Burger Button: mobile = sidebar toggle (both modes), desktop sidebar mode = collapse toggle --}}
                <button x-show="Alpine.store('layout').isSidebar() || window.innerWidth < 1024"
                        x-on:resize.window="$el.style.display = (Alpine.store('layout').isSidebar() || window.innerWidth < 1024) ? '' : 'none'"
                        @click="window.innerWidth >= 1024 ? Alpine.store('sidebar').toggleCollapse() : Alpine.store('sidebar').toggle()"
                        class="inline-flex items-center justify-center p-2 rounded-lg text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                        :title="window.innerWidth >= 1024 ? (Alpine.store('sidebar').collapsed ? 'Expand sidebar' : 'Collapse sidebar') : 'Toggle menu'">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                </button>

                {{-- Navbar mode: Logo + App Name (desktop only, mobile uses sidebar logo) --}}
                <a href="{{ route('dashboard') }}" class="hidden lg:flex items-center space-x-2" x-show="Alpine.store('layout').isNavbar()" x-cloak>
                    <div class="w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M10.894 2.553a1 1 0 00-1.788 0l-7 14a1 1 0 001.169 1.409l5-1.429A1 1 0 009 15.571V11a1 1 0 112 0v4.571a1 1 0 00.725.962l5 1.428a1 1 0 001.17-1.408l-7-14z"/>
                        </svg>
                    </div>
                    <span class="text-sm font-bold text-gray-900 dark:text-white hidden sm:inline">{{ config('app.name', 'Boilerplate') }}</span>
                </a>

                {{-- Navbar mode: Horizontal Menu Items --}}
                <div class="hidden lg:flex items-center space-x-1 ml-4" x-show="Alpine.store('layout').isNavbar()" x-cloak>
                        @foreach($menuItems as $item)
                            @if(isset($item['children']))
                                <div x-data="{ open: false }" @click.outside="open = false" class="relative">
                                    <button @click="open = !open"
                                            class="flex items-center space-x-1.5 px-3 py-2 text-sm font-medium rounded-lg transition-colors
                                                   {{ ($item['active'] ?? false) ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700' }}">
                                        <x-icon name="{{ $item['icon'] }}" class="w-4 h-4" />
                                        <span>{{ $item['name'] }}</span>
                                        <svg class="w-3.5 h-3.5 transition-transform" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                        </svg>
                                    </button>
                                    <div x-show="open"
                                         x-transition:enter="transition ease-out duration-200"
                                         x-transition:enter-start="opacity-0 scale-95"
                                         x-transition:enter-end="opacity-100 scale-100"
                                         x-transition:leave="transition ease-in duration-150"
                                         x-transition:leave-start="opacity-100 scale-100"
                                         x-transition:leave-end="opacity-0 scale-95"
                                         class="absolute left-0 mt-1 w-52 py-1 bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 z-50"
                                         style="display: none;">
                                        @foreach(array_filter($item['children']) as $child)
                                            <a href="{{ route($child['route']) }}"
                                               wire:navigate
                                               class="block px-4 py-2 text-sm transition-colors
                                                      {{ ($child['active'] ?? false) ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400 font-medium' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700' }}">
                                                {{ $child['name'] }}
                                            </a>
                                        @endforeach
                                    </div>
                                </div>
                            @else
                                <a href="{{ route($item['route']) }}"
                                   wire:navigate
                                   class="flex items-center space-x-1.5 px-3 py-2 text-sm font-medium rounded-lg transition-colors
                                          {{ ($item['active'] ?? false) ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700' }}">
                                    <x-icon name="{{ $item['icon'] }}" class="w-4 h-4" />
                                    <span>{{ $item['name'] }}</span>
                                </a>
                            @endif
                        @endforeach
                </div>
            </div>

            {{-- Center: Page Title (mobile only) --}}
            <div class="flex-1 lg:hidden">
                <h1 class="text-lg font-semibold text-gray-900 dark:text-white text-center">
                    {{ $title ?? 'Dashboard' }}
                </h1>
            </div>

            {{-- Right Side: Layout Toggle, Dark Mode & Profile --}}
            <div class="flex items-center space-x-1 sm:space-x-2">
                {{-- Layout Mode Toggle --}}
                <button @click="Alpine.store('layout').toggleMode()"
                        class="hidden lg:inline-flex p-2 text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors"
                        :title="Alpine.store('layout').isSidebar() ? 'Switch to navbar layout' : 'Switch to sidebar layout'">
                    {{-- Show "switch to navbar" icon when in sidebar mode --}}
                    <svg x-show="Alpine.store('layout').isSidebar()" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        <rect x="3" y="3" width="18" height="18" rx="2"/>
                        <line x1="3" y1="9" x2="21" y2="9"/>
                        <line x1="9" y1="9" x2="9" y2="21" opacity="0.4"/>
                    </svg>
                    {{-- Show "switch to sidebar" icon when in navbar mode --}}
                    <svg x-show="Alpine.store('layout').isNavbar()" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        <rect x="3" y="3" width="18" height="18" rx="2"/>
                        <line x1="9" y1="3" x2="9" y2="21"/>
                        <line x1="3" y1="9" x2="9" y2="9" opacity="0.4"/>
                    </svg>
                </button>

                {{-- Dark Mode Toggle --}}
                <button @click="darkMode = !darkMode"
                        class="p-2 text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors">
                    <svg x-show="!darkMode" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>
                    </svg>
                    <svg x-show="darkMode" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/>
                    </svg>
                </button>

                {{-- Notification Bell --}}
                @can('notifications_view')
                    <livewire:notifications.notification-bell />
                @endcan

                {{-- Profile Dropdown --}}
                <div x-data="{ open: false }" @click.outside="open = false" class="relative">
                    <button @click="open = !open"
                            class="flex items-center space-x-2 sm:space-x-3 p-1.5 sm:p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                        <div class="w-8 h-8 sm:w-9 sm:h-9 rounded-full bg-gradient-to-br from-blue-500 to-blue-600 flex items-center justify-center text-white font-semibold text-sm">
                            {{ substr($authUser->name, 0, 1) }}
                        </div>
                        <div class="hidden sm:block text-left">
                            <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $authUser->name }}</div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">{{ $authUserRole }}</div>
                        </div>
                        <svg class="hidden sm:block w-4 h-4 text-gray-600 dark:text-gray-400 transition-transform"
                             :class="{ 'rotate-180': open }"
                             fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>

                    <div x-show="open"
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0 scale-95"
                         x-transition:enter-end="opacity-100 scale-100"
                         x-transition:leave="transition ease-in duration-150"
                         x-transition:leave-start="opacity-100 scale-100"
                         x-transition:leave-end="opacity-0 scale-95"
                         class="absolute right-0 mt-2 w-56 rounded-lg shadow-lg bg-white dark:bg-gray-800 ring-1 ring-black ring-opacity-5 divide-y divide-gray-100 dark:divide-gray-700"
                         style="display: none;">

                        <div class="px-4 py-3 sm:hidden">
                            <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $authUser->name }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ $authUser->email }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ $authUserRole }}</p>
                        </div>

                        <div class="py-1">
                            <a href="{{ route('profile') }}"
                               wire:navigate
                               class="flex items-center px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                                <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                </svg>
                                Profil Saya
                            </a>

                            @can('configuration_view')
                            <a href="{{ route('settings.system') }}"
                               wire:navigate
                               class="flex items-center px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                                <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                                Pengaturan
                            </a>
                            @endcan
                        </div>

                        <div class="py-1">
                            <button wire:click="logout"
                                    class="flex items-center w-full px-4 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors">
                                <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                                </svg>
                                Keluar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</nav>
