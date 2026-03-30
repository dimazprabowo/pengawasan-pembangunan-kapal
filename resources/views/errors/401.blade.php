@extends('errors.layout')

@section('title', '401 - Tidak Terautentikasi')

@section('particles')
    <!-- Yellow/Amber particles for unauthorized -->
    <div class="particle w-4 h-4 bg-amber-400/30 dark:bg-amber-500/20 top-1/4 left-1/4" style="animation-delay: 0s;"></div>
    <div class="particle w-6 h-6 bg-yellow-400/30 dark:bg-yellow-500/20 top-1/3 right-1/4" style="animation-delay: 1s;"></div>
    <div class="particle w-3 h-3 bg-amber-500/30 dark:bg-amber-400/20 bottom-1/4 left-1/3" style="animation-delay: 2s;"></div>
    <div class="particle w-5 h-5 bg-yellow-500/30 dark:bg-yellow-400/20 top-1/2 right-1/3" style="animation-delay: 3s;"></div>
    <div class="particle w-4 h-4 bg-amber-300/30 dark:bg-amber-600/20 bottom-1/3 right-1/4" style="animation-delay: 4s;"></div>
@endsection

@section('content')
<div class="text-center">
    <!-- Animated Icon -->
    <div class="relative inline-block mb-8 animate-bounce-in">
        <!-- Background glow -->
        <div class="absolute inset-0 bg-gradient-to-r from-amber-500 to-yellow-500 rounded-full blur-2xl opacity-30 animate-pulse"></div>
        
        <!-- Key Icon -->
        <div class="relative animate-float">
            <div class="w-32 h-32 sm:w-40 sm:h-40 mx-auto bg-gradient-to-br from-amber-500 to-yellow-600 rounded-full flex items-center justify-center shadow-2xl" style="box-shadow: 0 0 30px rgba(245, 158, 11, 0.4);">
                <svg class="w-16 h-16 sm:w-20 sm:h-20 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                </svg>
            </div>
            
            <!-- Question mark badge -->
            <div class="absolute -top-2 -right-2 w-8 h-8 bg-yellow-500 rounded-full flex items-center justify-center text-white text-lg font-bold shadow-lg animate-bounce">
                ?
            </div>
        </div>
    </div>

    <!-- Error Code -->
    <div class="animate-slide-up opacity-0" style="animation-delay: 0.2s;">
        <h1 class="text-7xl sm:text-8xl font-black bg-gradient-to-r from-amber-500 via-yellow-500 to-amber-600 bg-clip-text text-transparent animate-gradient">
            401
        </h1>
    </div>

    <!-- Error Title -->
    <div class="animate-slide-up opacity-0" style="animation-delay: 0.3s;">
        <h2 class="mt-4 text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">
            Tidak Terautentikasi
        </h2>
    </div>

    <!-- Error Description -->
    <div class="animate-slide-up opacity-0" style="animation-delay: 0.4s;">
        <p class="mt-4 text-gray-600 dark:text-gray-400 max-w-md mx-auto leading-relaxed">
            Anda perlu login terlebih dahulu untuk mengakses halaman ini.
            Silakan masuk dengan akun Anda.
        </p>
    </div>

    <!-- Action Buttons -->
    <div class="mt-8 flex flex-col sm:flex-row items-center justify-center gap-4 animate-slide-up opacity-0" style="animation-delay: 0.5s;">
        <a href="{{ route('login') }}" 
           class="group inline-flex items-center gap-2 px-6 py-3 bg-gradient-to-r from-amber-500 to-yellow-600 hover:from-amber-600 hover:to-yellow-700 rounded-xl font-semibold text-white shadow-lg hover:shadow-xl transition-all duration-300 hover:scale-105">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/>
            </svg>
            Login
        </a>
        
        <a href="{{ url('/') }}" 
           class="group inline-flex items-center gap-2 px-6 py-3 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-xl font-semibold text-gray-700 dark:text-gray-300 shadow-lg hover:shadow-xl transition-all duration-300 hover:scale-105 hover:border-amber-300 dark:hover:border-amber-600">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
            </svg>
            Beranda
        </a>
    </div>
</div>
@endsection
