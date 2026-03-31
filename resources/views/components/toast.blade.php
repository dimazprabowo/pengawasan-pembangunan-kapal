<div x-data="{
    show: false,
    type: 'success',
    title: '',
    message: '',
    timeout: null,
    
    showNotification(data) {
        // Handle if data is array (Livewire sometimes wraps in array)
        const notification = Array.isArray(data) ? data[0] : data;
        
        this.type = notification.type || 'success';
        this.title = notification.title || (notification.type === 'success' ? 'Berhasil' : notification.type === 'failed' ? 'Gagal' : notification.type === 'error' ? 'Error' : notification.type === 'warning' ? 'Peringatan' : 'Informasi');
        this.message = notification.message || '';
        this.show = true;
        
        clearTimeout(this.timeout);
        this.timeout = setTimeout(() => {
            this.show = false;
        }, 5000);
    }
}"
@notify.window="showNotification($event.detail)"
x-show="show"
x-transition:enter="transform ease-out duration-300 transition"
x-transition:enter-start="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
x-transition:enter-end="translate-y-0 opacity-100 sm:translate-x-0"
x-transition:leave="transition ease-in duration-100"
x-transition:leave-start="opacity-100"
x-transition:leave-end="opacity-0"
class="fixed top-4 left-4 right-4 sm:left-auto sm:right-4 z-50 max-w-sm w-auto sm:w-full"
style="display: none;">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg overflow-hidden border-l-4"
         :class="{
             'border-green-500': type === 'success',
             'border-red-500': type === 'error' || type === 'failed',
             'border-yellow-500': type === 'warning',
             'border-blue-500': type === 'info'
         }">
        <div class="p-4">
            <div class="flex items-start">
                <div class="flex-shrink-0">
                    <!-- Success Icon -->
                    <svg x-show="type === 'success'" class="h-6 w-6 text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <!-- Error/Failed Icon -->
                    <svg x-show="type === 'error' || type === 'failed'" class="h-6 w-6 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <!-- Warning Icon -->
                    <svg x-show="type === 'warning'" class="h-6 w-6 text-yellow-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                    <!-- Info Icon -->
                    <svg x-show="type === 'info'" class="h-6 w-6 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div class="ml-3 flex-1 pt-0.5">
                    <p class="text-sm font-medium text-gray-900 dark:text-white" x-text="title"></p>
                    <p class="text-sm text-gray-600 dark:text-gray-300 mt-0.5" x-text="message"></p>
                </div>
                <div class="ml-4 flex-shrink-0 flex">
                    <button @click="show = false" class="inline-flex text-gray-400 hover:text-gray-500 focus:outline-none">
                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
