<?php

namespace App\Livewire\Profile;

use App\Livewire\Traits\HasNotification;
use App\Models\Owner;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class ManageOwner extends Component
{
    use HasNotification;

    public $showModal = false;

    public $isEditMode = false;

    public $code;

    public $name;

    public $email;

    public $phone;

    public $address;

    public $pic_name;

    public $pic_email;

    public $pic_phone;

    public $npwp;

    protected function rules()
    {
        $user = Auth::user();
        $ownerId = $user->owner_id;

        return [
            'code' => [
                'required',
                'string',
                'max:50',
                $this->isEditMode ? 'unique:owners,code,'.$ownerId : 'unique:owners,code',
            ],
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'pic_name' => 'nullable|string|max:255',
            'pic_email' => 'nullable|email|max:255',
            'pic_phone' => 'nullable|string|max:20',
            'npwp' => [
                'required',
                'string',
                'regex:/^[0-9]{15,16}$/',
                $this->isEditMode ? 'unique:owners,npwp,'.$ownerId : 'unique:owners,npwp',
            ],
        ];
    }

    protected $messages = [
        'code.required' => 'Kode owner wajib diisi',
        'code.unique' => 'Kode owner sudah digunakan',
        'name.required' => 'Nama owner wajib diisi',
        'email.email' => 'Format email tidak valid',
        'pic_email.email' => 'Format email PIC tidak valid',
        'npwp.required' => 'NPWP wajib diisi',
        'npwp.regex' => 'NPWP harus 15 digit (format lama) atau 16 digit (format baru)',
        'npwp.unique' => 'NPWP sudah terdaftar untuk owner lain',
    ];

    public function openModal()
    {
        $user = Auth::user();

        if (! $user->can('manage_own_owner')) {
            $this->notifyError('Anda tidak memiliki izin untuk mengelola owner');

            return;
        }

        if ($user->owner_id && $user->owner) {
            $this->isEditMode = true;
            $owner = $user->owner;

            $this->code = $owner->code;
            $this->name = $owner->name;
            $this->email = $owner->email;
            $this->phone = $owner->phone;
            $this->address = $owner->address;
            $this->pic_name = $owner->pic_name;
            $this->pic_email = $owner->pic_email;
            $this->pic_phone = $owner->pic_phone;
            $this->npwp = $owner->npwp;
        } else {
            $this->isEditMode = false;
            $this->resetForm();
        }

        $this->showModal = true;
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->resetForm();
        $this->resetErrorBag();
    }

    public function save()
    {
        if (! Auth::user()->can('manage_own_owner')) {
            $this->notifyError('Anda tidak memiliki izin untuk mengelola owner');

            return;
        }

        try {
            $this->validate();
        } catch (ValidationException $e) {
            $this->notifyValidationError($e);
            throw $e;
        }

        try {
            $user = Auth::user();

            if ($this->isEditMode) {
                $owner = $user->owner;
                $owner->update([
                    'code' => $this->code,
                    'name' => $this->name,
                    'email' => $this->email,
                    'phone' => $this->phone,
                    'address' => $this->address,
                    'pic_name' => $this->pic_name,
                    'pic_email' => $this->pic_email,
                    'pic_phone' => $this->pic_phone,
                    'npwp' => $this->npwp,
                ]);

                $message = 'Informasi owner berhasil diperbarui!';
            } else {
                $owner = Owner::create([
                    'code' => $this->code,
                    'name' => $this->name,
                    'email' => $this->email,
                    'phone' => $this->phone,
                    'address' => $this->address,
                    'pic_name' => $this->pic_name,
                    'pic_email' => $this->pic_email,
                    'pic_phone' => $this->pic_phone,
                    'npwp' => $this->npwp,
                    'status' => 'active',
                ]);

                $user->owner_id = $owner->id;
                $user->save();

                $message = 'Owner berhasil dibuat dan dihubungkan ke akun Anda!';
            }

            $this->notifySuccess($message);

            $this->closeModal();

            return $this->redirect(route('profile'), navigate: true);

        } catch (\Exception $e) {
            $this->notifyError('Terjadi kesalahan sistem. Silakan coba lagi.');
        }
    }

    private function resetForm()
    {
        $this->reset([
            'code',
            'name',
            'email',
            'phone',
            'address',
            'pic_name',
            'pic_email',
            'pic_phone',
            'npwp',
        ]);
    }

    public function render()
    {
        $user = Auth::user();
        $hasOwner = $user->owner_id && $user->owner;
        $canManage = $user->can('manage_own_owner');

        return view('livewire.profile.manage-owner', [
            'hasOwner' => $hasOwner,
            'canManage' => $canManage,
            'owner' => $hasOwner ? $user->owner : null,
        ]);
    }
}
