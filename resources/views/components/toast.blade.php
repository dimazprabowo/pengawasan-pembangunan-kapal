<div x-data
     x-show="$store.notification.items.length > 0"
     x-cloak
     class="fixed top-4 left-4 right-4 sm:left-auto sm:right-4 z-[100] flex flex-col-reverse gap-2.5 max-w-sm w-auto sm:w-full pointer-events-none">
    <template x-for="item in $store.notification.items" :key="item.id">
        <div x-data="{ progress: 100, startX: 0, currentX: 0, dragging: false, dismissed: false,
                        onTouchStart(e) { if (e.touches.length === 1) { this.startX = e.touches[0].clientX; this.currentX = this.startX; this.dragging = true; $store.notification.pause(item.id); } },
                        onTouchMove(e) { if (this.dragging && e.touches.length === 1) { this.currentX = e.touches[0].clientX; } },
                        onTouchEnd() { if (this.dragging) { this.dragging = false; const delta = this.currentX - this.startX; if (Math.abs(delta) > 80) { this.dismissed = true; $store.notification.remove(item.id); } else { this.startX = 0; this.currentX = 0; $store.notification.resume(item.id); } } },
                        onMouseDown(e) { this.startX = e.clientX; this.currentX = this.startX; this.dragging = true; $store.notification.pause(item.id); },
                        onMouseMove(e) { if (this.dragging) { this.currentX = e.clientX; } },
                        onMouseUp() { if (this.dragging) { this.dragging = false; const delta = this.currentX - this.startX; if (Math.abs(delta) > 80) { this.dismissed = true; $store.notification.remove(item.id); } else { this.startX = 0; this.currentX = 0; $store.notification.resume(item.id); } } },
                     }"
             x-init="progress = 100; const tick = setInterval(() => { if (!item.paused) { const elapsed = Date.now() - item.startTime; progress = Math.max(0, 100 - (elapsed / $store.notification.duration * 100)); } }, 50); $watch('$store.notification.items', val => { if (!val.find(i => i.id === item.id)) clearInterval(tick); })"
             @mouseenter="$store.notification.pause(item.id)"
             @mouseleave="$store.notification.resume(item.id)"
             @touchstart="onTouchStart($event)"
             @touchmove.prevent="onTouchMove($event)"
             @touchend="onTouchEnd()"
             @mousedown="onMouseDown($event)"
             @mousemove="onMouseMove($event)"
             @mouseup="onMouseUp()"
             @mouseleave.window="if (dragging) { dragging = false; startX = 0; currentX = 0; $store.notification.resume(item.id); }"
             :style="dragging ? `transform: translateX(${currentX - startX}px); transition: none;` : ''"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 translate-x-4 sm:translate-x-8 scale-95"
             x-transition:enter-end="opacity-100 translate-x-0 scale-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 translate-x-0 scale-100"
             x-transition:leave-end="opacity-0 translate-x-4 sm:translate-x-8 scale-95"
             class="bg-white dark:bg-gray-800 rounded-xl shadow-xl ring-1 ring-black/5 dark:ring-white/10 overflow-hidden border-l-4 pointer-events-auto transition-transform duration-200 hover:-translate-y-0.5 hover:shadow-2xl select-none"
             :class="{
                 'border-green-500': item.type === 'success',
                 'border-red-500': item.type === 'error',
                 'border-yellow-500': item.type === 'warning',
                 'border-blue-500': item.type === 'info'
             }">
            <div class="p-4">
                <div class="flex items-start">
                    <div class="flex-shrink-0"
                         x-transition:enter="transition ease-out duration-500 delay-100"
                         x-transition:enter-start="scale-0 rotate-[-90deg] opacity-0"
                         x-transition:enter-end="scale-100 rotate-0 opacity-100">
                        <svg x-show="item.type === 'success'" class="h-5 w-5 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <svg x-show="item.type === 'error'" class="h-5 w-5 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <svg x-show="item.type === 'warning'" class="h-5 w-5 text-yellow-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77-1.333.192 3 1.732 3z"/>
                        </svg>
                        <svg x-show="item.type === 'info'" class="h-5 w-5 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <div class="ml-3 flex-1 pt-0.5">
                        <p class="text-sm font-semibold text-gray-900 dark:text-white" x-text="item.title"></p>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5 leading-snug" x-text="item.message"></p>
                    </div>
                    <div class="ml-3 flex-shrink-0 flex">
                        <button @click="$store.notification.remove(item.id)"
                                class="inline-flex text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 focus:outline-none transition-colors duration-150">
                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
            <div class="h-0.5 bg-gray-100 dark:bg-gray-700 overflow-hidden">
                <div class="h-full transition-all duration-75 ease-linear"
                     :style="`width: ${progress}%`"
                     :class="{
                         'bg-green-500': item.type === 'success',
                         'bg-red-500': item.type === 'error',
                         'bg-yellow-500': item.type === 'warning',
                         'bg-blue-500': item.type === 'info'
                     }"></div>
            </div>
        </div>
    </template>
</div>
