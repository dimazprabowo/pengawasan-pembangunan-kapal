import './bootstrap';
import './alpine-stores';
import flatpickr from 'flatpickr';
import 'flatpickr/dist/flatpickr.min.css';

window.flatpickr = flatpickr;

/**
 * Kompres gambar client-side (downscale sisi terpanjang ke maks 1600px, WebP q85)
 * sebelum di-upload via $wire.upload. Non-gambar dikembalikan apa adanya.
 * Dipakai upload foto lampiran aktivitas & foto bukti NC.
 *
 * @param {HTMLInputElement} input elemen <input type="file">
 * @returns {Promise<File|null>} File hasil kompresi (atau file asli), null bila tidak ada file
 */
window.lhCompressUpload = async function (input) {
    const file = input.files && input.files[0];
    if (!file) return null;
    input.value = '';
    if (!file.type.startsWith('image/')) return file;
    try {
        const bitmap = await createImageBitmap(file);
        const scale = Math.min(1, 1600 / Math.max(bitmap.width, bitmap.height));
        const canvas = document.createElement('canvas');
        canvas.width = Math.max(1, Math.round(bitmap.width * scale));
        canvas.height = Math.max(1, Math.round(bitmap.height * scale));
        canvas.getContext('2d').drawImage(bitmap, 0, 0, canvas.width, canvas.height);
        const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/webp', 0.85));
        if (blob && blob.type === 'image/webp') {
            return new File([blob], file.name.replace(/\.[^.]+$/, '') + '.webp', { type: 'image/webp' });
        }
    } catch (e) {
        // Fallback ke file asli bila decode/kompresi gagal
    }
    return file;
};

// Note: Alpine.js is already included in Livewire
// No need to import it separately to avoid multiple instances
