@props(['editMode' => false, 'showStatus' => false])

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    {{-- Code --}}
    <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            Kode <span class="text-red-500">*</span>
        </label>
        <input wire:model="code" type="text" 
            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"
            placeholder="Contoh: COMP001">
        @error('code') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
    </div>

    {{-- Name --}}
    <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            Nama Perusahaan <span class="text-red-500">*</span>
        </label>
        <input wire:model="name" type="text" 
            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"
            placeholder="Nama perusahaan">
        @error('name') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
    </div>

    {{-- NPWP --}}
    <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            NPWP <span class="text-red-500">*</span>
        </label>
        <input 
            type="text" 
            x-data="{
                npwp: @entangle('npwp'),
                formatNpwp(value) {
                    let digits = value.replace(/\D/g, '').substring(0, 16);
                    let len = digits.length;
                    
                    if (len <= 15) {
                        let formatted = '';
                        if (len > 0) formatted += digits.substring(0, Math.min(2, len));
                        if (len > 2) formatted += '.' + digits.substring(2, Math.min(5, len));
                        if (len > 5) formatted += '.' + digits.substring(5, Math.min(8, len));
                        if (len > 8) formatted += '.' + digits.substring(8, Math.min(9, len));
                        if (len > 9) formatted += '-' + digits.substring(9, Math.min(12, len));
                        if (len > 12) formatted += '.' + digits.substring(12, 15);
                        return formatted;
                    } else {
                        let formatted = '';
                        if (len > 0) formatted += digits.substring(0, Math.min(4, len));
                        if (len > 4) formatted += '.' + digits.substring(4, Math.min(8, len));
                        if (len > 8) formatted += '.' + digits.substring(8, Math.min(12, len));
                        if (len > 12) formatted += '.' + digits.substring(12, 16);
                        return formatted;
                    }
                },
                displayValue() {
                    return this.npwp ? this.formatNpwp(this.npwp) : '';
                }
            }"
            x-init="$watch('npwp', value => { if(value) npwp = value.replace(/\D/g, '').substring(0, 16); })"
            :value="displayValue()"
            @input="npwp = $event.target.value.replace(/\D/g, '').substring(0, 16); $event.target.value = formatNpwp($event.target.value)"
            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white font-mono"
            placeholder="XX.XXX.XXX.X-XXX.XXX">
        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Format lama (15 digit) atau baru (16 digit)</p>
        @error('npwp') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
    </div>

    {{-- Email --}}
    <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email</label>
        <input wire:model="email" type="email" 
            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"
            placeholder="email@perusahaan.com">
        @error('email') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
    </div>

    {{-- Phone --}}
    <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Telepon</label>
        <input wire:model="phone" type="text" 
            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"
            placeholder="021-1234567">
        @error('phone') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
    </div>

    {{-- Address --}}
    <div class="md:col-span-2">
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Alamat</label>
        <textarea wire:model="address" rows="2" 
            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"
            placeholder="Alamat lengkap perusahaan"></textarea>
        @error('address') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
    </div>

    {{-- Divider --}}
    <div class="md:col-span-2 border-t border-gray-200 dark:border-gray-700 pt-4">
        <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300">Person In Charge (PIC)</h4>
    </div>

    {{-- PIC Name --}}
    <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nama PIC</label>
        <input wire:model="pic_name" type="text" 
            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"
            placeholder="Nama person in charge">
        @error('pic_name') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
    </div>

    {{-- PIC Email --}}
    <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email PIC</label>
        <input wire:model="pic_email" type="email" 
            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"
            placeholder="pic@perusahaan.com">
        @error('pic_email') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
    </div>

    {{-- PIC Phone --}}
    <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Telepon PIC</label>
        <input wire:model="pic_phone" type="text" 
            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"
            placeholder="08123456789">
        @error('pic_phone') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
    </div>

    {{-- Status (only for admin) --}}
    @if($showStatus)
    <div class="md:col-span-2 border-t border-gray-200 dark:border-gray-700 pt-4">
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            Status <span class="text-red-500">*</span>
        </label>
        <select wire:model="status" 
            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
            <option value="active">Aktif</option>
            <option value="inactive">Tidak Aktif</option>
            <option value="suspended">Suspended</option>
        </select>
        @error('status') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
    </div>
    @endif
</div>
