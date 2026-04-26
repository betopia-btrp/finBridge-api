<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterMfiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // User fields
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'required|string|max:20|unique:users,phone',
            'password' => 'required|min:6',

            // MFI fields
            'mfi_name' => 'required|string|max:255',
            'mfi_email' => 'nullable|email',
            'mfi_phone' => 'nullable|string|max:20',
        ];
    }
}
