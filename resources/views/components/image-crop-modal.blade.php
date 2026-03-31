@props(['show' => false, 'imageUrl', 'cropData' => []])

@if($show && $imageUrl)
    <div class="fixed inset-0 z-50 overflow-y-auto"
         x-data="{
            cropper: null,
            savedCropData: @js($cropData),
            init() {
                this.$nextTick(() => {
                    const image = document.getElementById('cropper-image');
                    if (image && window.Cropper) {
                        // Destroy existing if any
                        if (this.cropper) {
                            this.cropper.destroy();
                            this.cropper = null;
                        }
                        
                        // Initialize cropper
                        this.cropper = new Cropper(image, {
                            aspectRatio: NaN,
                            viewMode: 1,
                            autoCropArea: 1,
                            responsive: true,
                            background: false,
                            ready: () => {
                                // Apply saved crop data after cropper is fully ready
                                if (this.savedCropData && this.savedCropData.width > 0 && this.savedCropData.height > 0) {
                                    setTimeout(() => {
                                        this.cropper.setData(this.savedCropData);
                                    }, 100);
                                }
                            }
                        });
                    }
                });
            },
            saveCrop() {
                if (!this.cropper) {
                    alert('Cropper tidak siap. Silakan tunggu gambar selesai dimuat.');
                    return;
                }
                const data = this.cropper.getData();
                $wire.set('cropData', {
                    x: Math.round(data.x),
                    y: Math.round(data.y),
                    width: Math.round(data.width),
                    height: Math.round(data.height)
                });
                $wire.saveCrop();
            }
         }"
         x-init="init()"
         x-cloak>
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20">
            {{-- Overlay --}}
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 dark:bg-gray-900 dark:bg-opacity-75 transition-opacity"
                 wire:click="closeCropper"></div>

            {{-- Modal Content --}}
            <div class="relative bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-4xl flex flex-col z-10 max-h-[90vh]">
                {{-- Modal Header --}}
                <div class="flex items-center justify-between px-5 py-3 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Crop Gambar</h3>
                    <button type="button" wire:click="closeCropper" 
                        wire:loading.attr="disabled"
                        wire:target="closeCropper"
                        class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 disabled:opacity-50">
                        <svg wire:loading.class="hidden" wire:target="closeCropper" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                        <svg wire:loading wire:target="closeCropper" class="animate-spin w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </button>
                </div>

                {{-- Modal Body --}}
                <div class="flex-1 overflow-auto p-4">
                    <div class="max-h-[60vh]">
                        <img id="cropper-image" src="{{ $imageUrl }}" class="max-w-full">
                    </div>
                </div>

                {{-- Modal Footer --}}
                <div class="flex items-center justify-end gap-2 px-5 py-3 border-t border-gray-200 dark:border-gray-700">
                    <button type="button" wire:click="closeCropper"
                        wire:loading.attr="disabled"
                        wire:target="closeCropper"
                        class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 disabled:opacity-50">
                        <svg wire:loading.class="hidden" wire:target="closeCropper" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                        <svg wire:loading wire:target="closeCropper" class="animate-spin w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span wire:loading.class="hidden" wire:target="closeCropper">Batal</span>
                        <span wire:loading wire:target="closeCropper">Menutup...</span>
                    </button>
                    <button type="button" x-on:click="saveCrop()"
                        wire:loading.attr="disabled"
                        wire:target="saveCrop"
                        class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 disabled:opacity-50">
                        <svg wire:loading.class="hidden" wire:target="saveCrop" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        <svg wire:loading wire:target="saveCrop" class="animate-spin w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span wire:loading.class="hidden" wire:target="saveCrop">Simpan Crop</span>
                        <span wire:loading wire:target="saveCrop">Menyimpan...</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
@endif
