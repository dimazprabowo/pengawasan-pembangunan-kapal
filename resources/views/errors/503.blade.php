@extends('errors.layout')

@section('title', '503 - Layanan Tidak Tersedia')

@section('particles')
    <!-- Gray/Slate particles for maintenance -->
    <div class="particle w-4 h-4 bg-slate-400/30 dark:bg-slate-500/20 top-1/4 left-1/4" style="animation-delay: 0s;"></div>
    <div class="particle w-6 h-6 bg-gray-400/30 dark:bg-gray-500/20 top-1/3 right-1/4" style="animation-delay: 1s;"></div>
    <div class="particle w-3 h-3 bg-slate-500/30 dark:bg-slate-400/20 bottom-1/4 left-1/3" style="animation-delay: 2s;"></div>
    <div class="particle w-5 h-5 bg-gray-500/30 dark:bg-gray-400/20 top-1/2 right-1/3" style="animation-delay: 3s;"></div>
    <div class="particle w-4 h-4 bg-slate-300/30 dark:bg-slate-600/20 bottom-1/3 right-1/4" style="animation-delay: 4s;"></div>
@endsection

@section('content')
<div class="text-center">
    <!-- Animated Icon -->
    <div class="relative inline-block mb-8 animate-bounce-in">
        <!-- Background glow -->
        <div class="absolute inset-0 bg-gradient-to-r from-slate-500 to-gray-500 rounded-full blur-2xl opacity-30 animate-pulse"></div>
        
        <!-- Wrench/Tools Icon -->
        <div class="relative animate-float">
            <div class="w-32 h-32 sm:w-40 sm:h-40 mx-auto bg-gradient-to-br from-slate-500 to-gray-600 rounded-full flex items-center justify-center shadow-2xl" style="box-shadow: 0 0 30px rgba(100, 116, 139, 0.4);">
                <svg class="w-16 h-16 sm:w-20 sm:h-20 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
            </div>
            
            <!-- Wrench badge -->
            <div class="absolute -bottom-2 -right-2 w-10 h-10 bg-gray-600 rounded-full flex items-center justify-center text-white shadow-lg animate-rotate" style="animation-duration: 4s;">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                </svg>
            </div>
        </div>
    </div>

    <!-- Error Code -->
    <div class="animate-slide-up opacity-0" style="animation-delay: 0.2s;">
        <h1 class="text-7xl sm:text-8xl font-black bg-gradient-to-r from-slate-500 via-gray-500 to-slate-600 bg-clip-text text-transparent animate-gradient">
            503
        </h1>
    </div>

    <!-- Error Title -->
    <div class="animate-slide-up opacity-0" style="animation-delay: 0.3s;">
        <h2 class="mt-4 text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">
            Sedang Dalam Pemeliharaan
        </h2>
    </div>

    <!-- Error Description -->
    <div class="animate-slide-up opacity-0" style="animation-delay: 0.4s;">
        <p class="mt-4 text-gray-600 dark:text-gray-400 max-w-md mx-auto leading-relaxed">
            Sistem sedang dalam pemeliharaan untuk meningkatkan layanan kami.
            Kami akan segera kembali. Terima kasih atas kesabaran Anda.
        </p>
    </div>

    <!-- Status Badge -->
    <div class="mt-6 animate-slide-up opacity-0" style="animation-delay: 0.45s;">
        <div class="inline-flex items-center gap-2 px-4 py-2 bg-slate-100 dark:bg-slate-800/50 rounded-full">
            <span class="relative flex h-3 w-3">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-slate-400 opacity-75"></span>
                <span class="relative inline-flex rounded-full h-3 w-3 bg-slate-500"></span>
            </span>
            <span class="text-sm font-medium text-slate-700 dark:text-slate-300">Pemeliharaan sedang berlangsung</span>
        </div>
    </div>

    <!-- Action Button -->
    <div class="mt-8 animate-slide-up opacity-0" style="animation-delay: 0.5s;">
        <button onclick="window.location.reload()" 
           class="group inline-flex items-center gap-2 px-6 py-3 bg-gradient-to-r from-slate-500 to-gray-600 hover:from-slate-600 hover:to-gray-700 rounded-xl font-semibold text-white shadow-lg hover:shadow-xl transition-all duration-300 hover:scale-105">
            <svg class="w-5 h-5 transition-transform group-hover:rotate-180 duration-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
            </svg>
            Coba Lagi
        </button>
    </div>
</div>
@endsection
