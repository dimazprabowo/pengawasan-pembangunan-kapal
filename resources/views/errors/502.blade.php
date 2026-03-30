@extends('errors.layout')

@section('title', '502 - Bad Gateway')

@section('particles')
    <!-- Red/Rose particles for gateway error -->
    <div class="particle w-4 h-4 bg-rose-400/30 dark:bg-rose-500/20 top-1/4 left-1/4" style="animation-delay: 0s;"></div>
    <div class="particle w-6 h-6 bg-red-400/30 dark:bg-red-500/20 top-1/3 right-1/4" style="animation-delay: 1s;"></div>
    <div class="particle w-3 h-3 bg-rose-500/30 dark:bg-rose-400/20 bottom-1/4 left-1/3" style="animation-delay: 2s;"></div>
    <div class="particle w-5 h-5 bg-red-500/30 dark:bg-red-400/20 top-1/2 right-1/3" style="animation-delay: 3s;"></div>
    <div class="particle w-4 h-4 bg-rose-300/30 dark:bg-rose-600/20 bottom-1/3 right-1/4" style="animation-delay: 4s;"></div>
@endsection

@section('content')
<div class="text-center">
    <!-- Animated Icon -->
    <div class="relative inline-block mb-8 animate-bounce-in">
        <!-- Background glow -->
        <div class="absolute inset-0 bg-gradient-to-r from-rose-500 to-red-500 rounded-full blur-2xl opacity-30 animate-pulse"></div>
        
        <!-- Server/Network Icon -->
        <div class="relative animate-float">
            <div class="w-32 h-32 sm:w-40 sm:h-40 mx-auto bg-gradient-to-br from-rose-500 to-red-600 rounded-full flex items-center justify-center shadow-2xl" style="box-shadow: 0 0 30px rgba(244, 63, 94, 0.4);">
                <svg class="w-16 h-16 sm:w-20 sm:h-20 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01"/>
                </svg>
            </div>
            
            <!-- Disconnect badge -->
            <div class="absolute -top-2 -right-2 w-8 h-8 bg-red-600 rounded-full flex items-center justify-center text-white shadow-lg animate-bounce">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                </svg>
            </div>
        </div>
    </div>

    <!-- Error Code -->
    <div class="animate-slide-up opacity-0" style="animation-delay: 0.2s;">
        <h1 class="text-7xl sm:text-8xl font-black bg-gradient-to-r from-rose-500 via-red-500 to-rose-600 bg-clip-text text-transparent animate-gradient">
            502
        </h1>
    </div>

    <!-- Error Title -->
    <div class="animate-slide-up opacity-0" style="animation-delay: 0.3s;">
        <h2 class="mt-4 text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">
            Bad Gateway
        </h2>
    </div>

    <!-- Error Description -->
    <div class="animate-slide-up opacity-0" style="animation-delay: 0.4s;">
        <p class="mt-4 text-gray-600 dark:text-gray-400 max-w-md mx-auto leading-relaxed">
            Server menerima respons yang tidak valid dari server upstream.
            Silakan coba lagi dalam beberapa saat.
        </p>
    </div>

    <!-- Action Buttons -->
    <div class="mt-8 flex flex-col sm:flex-row items-center justify-center gap-4 animate-slide-up opacity-0" style="animation-delay: 0.5s;">
        <button onclick="window.location.reload()" 
           class="group inline-flex items-center gap-2 px-6 py-3 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-xl font-semibold text-gray-700 dark:text-gray-300 shadow-lg hover:shadow-xl transition-all duration-300 hover:scale-105 hover:border-rose-300 dark:hover:border-rose-600">
            <svg class="w-5 h-5 transition-transform group-hover:rotate-180 duration-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
            </svg>
            Muat Ulang
        </button>
        
        <a href="{{ route('dashboard') }}" 
           class="group inline-flex items-center gap-2 px-6 py-3 bg-gradient-to-r from-rose-500 to-red-600 hover:from-rose-600 hover:to-red-700 rounded-xl font-semibold text-white shadow-lg hover:shadow-xl transition-all duration-300 hover:scale-105">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
            </svg>
            Dashboard
        </a>
    </div>
</div>
@endsection
