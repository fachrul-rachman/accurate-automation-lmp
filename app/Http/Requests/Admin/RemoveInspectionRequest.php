<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserRole;
use App\Models\GoodsReceiptInspection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class RemoveInspectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === UserRole::Admin;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $inspection = $this->route('inspection');

        return [
            'confirmation_number' => ['required', 'string', Rule::in([
                $inspection instanceof GoodsReceiptInspection ? $inspection->pb_number : '',
            ])],
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'confirmation_number.in' => 'Nomor Penerimaan Barang tidak sesuai.',
            'reason.required' => 'Alasan penghapusan wajib diisi.',
        ];
    }
}
