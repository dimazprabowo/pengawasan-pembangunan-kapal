@extends('errors.layout')

@section('title', '403 - Akses Ditolak')

@section('particles')
    <!-- Red/Orange particles for forbidden -->
    <div class="particle w-4 h-4 bg-red-400/30 dark:bg-red-500/20 top-1/4 left-1/4" style="animation-delay: 0s;"></div>
    <div class="particle w-6 h-6 bg-orange-400/30 dark:bg-orange-500/20 top-1/3 right-1/4" style="animation-delay: 1s;"></div>
    <div class="particle w-3 h-3 bg-red-500/30 dark:bg-red-400/20 bottom-1/4 left-1/3" style="animation-delay: 2s;"></div>
    <div class="particle w-5 h-5 bg-orange-500/30 dark:bg-orange-400/20 top-1/2 right-1/3" style="animation-delay: 3s;"></div>
    <div class="particle w-4 h-4 bg-red-300/30 dark:bg-red-600/20 bottom-1/3 right-1/4" style="animation-delay: 4s;"></div>
@endsection

@section('content')
<div class="text-center">
    <!-- Animated Icon -->
    <div class="relative inline-block mb-8 animate-bounce-in">
        <!-- Background glow -->
        <div class="absolute inset-0 bg-gradient-to-r from-red-500 to-orange-500 rounded-full blur-2xl opacity-30 animate-pulse"></div>
        
        <!-- Shield Icon with Lock -->
        <div class="relative animate-float">
            <div class="w-32 h-32 sm:w-40 sm:h-40 mx-auto bg-gradient-to-br from-red-500 to-orange-600 rounded-full flex items-center justify-center shadow-2xl animate-pulse-glow" style="box-shadow: 0 0 30px rgba(239, 68, 68, 0.4);">
                <svg class="w-16 h-16 sm:w-20 sm:h-20 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                </svg>
            </div>
            
            <!-- Floating X marks -->
            <div class="absolute -top-2 -right-2 w-8 h-8 bg-red-600 rounded-full flex items-center justify-center text-white text-sm font-bold shadow-lg animate-bounce">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </div>
        </div>
    </div>

    <!-- Error Code -->
    <div class="animate-slide-up opacity-0" style="animation-delay: 0.2s;">
        <h1 class="text-7xl sm:text-8xl font-black bg-gradient-to-r from-red-500 via-orange-500 to-red-600 bg-clip-text text-transparent animate-gradient">
            403
        </h1>
    </div>

    <!-- Error Title -->
    <div class="animate-slide-up opacity-0" style="animation-delay: 0.3s;">
        <h2 class="mt-4 text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">
            Akses Ditolak
        </h2>
    </div>

    <!-- Error Description -->
    <div class="animate-slide-up opacity-0" style="animation-delay: 0.4s;">
        <p class="mt-4 text-gray-600 dark:text-gray-400 max-w-md mx-auto leading-relaxed">
            Maaf, Anda tidak memiliki izin untuk mengakses halaman ini. 
            Silakan hubungi administrator jika Anda merasa ini adalah kesalahan.
        </p>
    </div>

    <!-- Action Buttons -->
    <div class="mt-8 flex flex-col sm:flex-row items-center justify-center gap-4 animate-slide-up opacity-0" style="animation-delay: 0.5s;">
        <a href="{{ url()->previous() }}" 
           class="group inline-flex items-center gap-2 px-6 py-3 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-xl font-semibold text-gray-700 dark:text-gray-300 shadow-lg hover:shadow-xl transition-all duration-300 hover:scale-105 hover:border-red-300 dark:hover:border-red-600">
            <svg class="w-5 h-5 transition-transform group-hover:-translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Kembali
        </a>
        
        <a href="{{ route('dashboard') }}" 
           class="group inline-flex items-center gap-2 px-6 py-3 bg-gradient-to-r from-red-500 to-orange-600 hover:from-red-600 hover:to-orange-700 rounded-xl font-semibold text-white shadow-lg hover:shadow-xl transition-all duration-300 hover:scale-105">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
            </svg>
            Dashboard
        </a>
    </div>
</div>
@endsection
