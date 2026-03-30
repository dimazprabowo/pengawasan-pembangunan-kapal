@extends('errors.layout')

@section('title', '400 - Permintaan Tidak Valid')

@section('particles')
    <!-- Yellow particles for bad request -->
    <div class="particle w-4 h-4 bg-yellow-400/30 dark:bg-yellow-500/20 top-1/4 left-1/4" style="animation-delay: 0s;"></div>
    <div class="particle w-6 h-6 bg-amber-400/30 dark:bg-amber-500/20 top-1/3 right-1/4" style="animation-delay: 1s;"></div>
    <div class="particle w-3 h-3 bg-yellow-500/30 dark:bg-yellow-400/20 bottom-1/4 left-1/3" style="animation-delay: 2s;"></div>
    <div class="particle w-5 h-5 bg-amber-500/30 dark:bg-amber-400/20 top-1/2 right-1/3" style="animation-delay: 3s;"></div>
    <div class="particle w-4 h-4 bg-yellow-300/30 dark:bg-yellow-600/20 bottom-1/3 right-1/4" style="animation-delay: 4s;"></div>
@endsection

@section('content')
<div class="text-center">
    <!-- Animated Icon -->
    <div class="relative inline-block mb-8 animate-bounce-in">
        <!-- Background glow -->
        <div class="absolute inset-0 bg-gradient-to-r from-yellow-500 to-amber-500 rounded-full blur-2xl opacity-30 animate-pulse"></div>
        
        <!-- Warning Icon -->
        <div class="relative animate-float">
            <div class="w-32 h-32 sm:w-40 sm:h-40 mx-auto bg-gradient-to-br from-yellow-500 to-amber-600 rounded-full flex items-center justify-center shadow-2xl" style="box-shadow: 0 0 30px rgba(234, 179, 8, 0.4);">
                <svg class="w-16 h-16 sm:w-20 sm:h-20 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
            </div>
            
            <!-- X badge -->
            <div class="absolute -top-2 -right-2 w-8 h-8 bg-amber-600 rounded-full flex items-center justify-center text-white shadow-lg animate-bounce">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </div>
        </div>
    </div>

    <!-- Error Code -->
    <div class="animate-slide-up opacity-0" style="animation-delay: 0.2s;">
        <h1 class="text-7xl sm:text-8xl font-black bg-gradient-to-r from-yellow-500 via-amber-500 to-yellow-600 bg-clip-text text-transparent animate-gradient">
            400
        </h1>
    </div>

    <!-- Error Title -->
    <div class="animate-slide-up opacity-0" style="animation-delay: 0.3s;">
        <h2 class="mt-4 text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">
            Permintaan Tidak Valid
        </h2>
    </div>

    <!-- Error Description -->
    <div class="animate-slide-up opacity-0" style="animation-delay: 0.4s;">
        <p class="mt-4 text-gray-600 dark:text-gray-400 max-w-md mx-auto leading-relaxed">
            Server tidak dapat memproses permintaan Anda karena ada kesalahan pada data yang dikirim.
            Silakan periksa kembali dan coba lagi.
        </p>
    </div>

    <!-- Action Buttons -->
    <div class="mt-8 flex flex-col sm:flex-row items-center justify-center gap-4 animate-slide-up opacity-0" style="animation-delay: 0.5s;">
        <a href="{{ url()->previous() }}" 
           class="group inline-flex items-center gap-2 px-6 py-3 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-xl font-semibold text-gray-700 dark:text-gray-300 shadow-lg hover:shadow-xl transition-all duration-300 hover:scale-105 hover:border-yellow-300 dark:hover:border-yellow-600">
            <svg class="w-5 h-5 transition-transform group-hover:-translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Kembali
        </a>
        
        <a href="{{ route('dashboard') }}" 
           class="group inline-flex items-center gap-2 px-6 py-3 bg-gradient-to-r from-yellow-500 to-amber-600 hover:from-yellow-600 hover:to-amber-700 rounded-xl font-semibold text-white shadow-lg hover:shadow-xl transition-all duration-300 hover:scale-105">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
            </svg>
            Dashboard
        </a>
    </div>
</div>
@endsection
