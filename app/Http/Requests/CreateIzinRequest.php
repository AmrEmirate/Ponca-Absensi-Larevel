<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateIzinRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'jenisIzin' => 'required|string',
            'deskripsi' => 'required|string|max:1000',
            'fotoBase64' => 'nullable|string',
            'tanggal' => 'nullable|date_format:Y-m-d',
            'targetUserId' => 'nullable|integer',
        ];
    }

    /**
     * Custom validation messages.
     */
    public function messages(): array
    {
        return [
            'jenisIzin.required' => 'Jenis izin wajib diisi.',
            'jenisIzin.in' => 'Jenis izin tidak valid (pilih: IZIN, SAKIT, DINAS, atau CUTI).',
            'deskripsi.required' => 'Deskripsi izin wajib diisi.',
            'deskripsi.max' => 'Deskripsi izin maksimal 1000 karakter.',
            'tanggal.date_format' => 'Format tanggal harus YYYY-MM-DD.',
        ];
    }
}
