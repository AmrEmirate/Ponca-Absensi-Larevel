<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateEmployeeRequest extends FormRequest
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
            'nama' => 'nullable|string|max:255',
            'email' => 'required|string|max:255',
            'password' => 'nullable|string|min:6',
            'nik' => 'nullable|string|max:20',
            'jabatan' => 'nullable|string|max:100',
            'role' => 'nullable|string',
            'gajiPerhari' => 'nullable|numeric|min:0',
            'hariKerja' => 'nullable|string',
            'jamMasukKerja' => 'nullable|string',
            'jamKeluarKerja' => 'nullable|string',
            'masterLokasiId' => 'nullable|integer|exists:master_lokasis,id',
            'master_lokasi_id' => 'nullable|integer|exists:master_lokasis,id',
        ];
    }

    /**
     * Custom validation messages.
     */
    public function messages(): array
    {
        return [
            'nama.required' => 'Nama karyawan wajib diisi.',
            'email.required' => 'Email atau Username wajib diisi.',
            'password.required' => 'Password wajib diisi.',
            'password.min' => 'Password minimal 6 karakter.',
            'masterLokasiId.exists' => 'Lokasi yang dipilih tidak ditemukan.',
            'master_lokasi_id.exists' => 'Lokasi yang dipilih tidak ditemukan.',
        ];
    }
}
