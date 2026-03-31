<?php

namespace App\Livewire\Traits;

use Illuminate\Validation\ValidationException;

trait HasNotification
{
    protected function notifySuccess(string $message): void
    {
        $this->dispatch('notify', type: 'success', message: $message);
    }

    protected function notifyError(string $message): void
    {
        $this->dispatch('notify', type: 'error', message: $message);
    }

    protected function notifyWarning(string $message): void
    {
        $this->dispatch('notify', type: 'warning', message: $message);
    }

    protected function notifyInfo(string $message): void
    {
        $this->dispatch('notify', type: 'info', message: $message);
    }

    /**
     * Show validation error notification with first error message from exception
     * This helps users see validation errors even when error fields are not visible
     */
    protected function notifyValidationError(?ValidationException $exception = null): void
    {
        if ($exception) {
            // Get errors from exception (works on first submit)
            $errors = $exception->validator->errors();
            if ($errors->isNotEmpty()) {
                $firstError = $errors->first();
                $this->dispatch('notify', type: 'failed', message: 'Validasi gagal: ' . $firstError);
            }
        } else {
            // Fallback to error bag (for backward compatibility)
            $errors = $this->getErrorBag();
            if ($errors->isNotEmpty()) {
                $firstError = $errors->first();
                $this->dispatch('notify', type: 'failed', message: 'Validasi gagal: ' . $firstError);
            }
        }
    }

    /**
     * Override validate method to automatically show notification on validation failure
     */
    protected function validateWithNotification(array $rules = [], array $messages = [], array $attributes = []): array
    {
        try {
            return $this->validate($rules, $messages, $attributes);
        } catch (ValidationException $e) {
            $this->notifyValidationError($e);
            throw $e;
        }
    }
}
