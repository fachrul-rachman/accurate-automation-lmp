<?php

namespace App\Http\Requests\Warehouse;

use App\Enums\QualityStatus;
use App\Enums\QuantityStatus;
use App\Enums\UserRole;
use App\Models\Location;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class StoreRoundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === UserRole::Warehouse;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'pb_number' => ['required', 'string', 'max:100'],
            'location_id' => ['required', 'integer', Rule::exists('locations', 'id')->where('is_active', true)],
            'selected_hod_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'specification_matches_po' => ['required', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'delivery_note_photo' => ['required', 'image', 'max:5120'],
            'receiver_photo' => ['required', 'image', 'max:5120'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.is_checked' => ['sometimes', 'boolean'],
            'items.*.accurate_detail_item_id' => ['required', 'string'],
            'items.*.quality_status' => ['nullable', Rule::enum(QualityStatus::class)],
            'items.*.quantity_status' => ['nullable', Rule::enum(QuantityStatus::class)],
            'items.*.received_qty' => ['nullable', 'numeric', 'min:0'],
            'items.*.damaged_qty' => ['nullable', 'numeric', 'min:0'],
            'items.*.item_photo' => ['nullable', 'image', 'max:5120'],
            'items.*.damage_photo' => ['nullable', 'image', 'max:5120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'selected_hod_id.required' => 'User wajib dipilih.',
            'delivery_note_photo.required' => 'Foto surat jalan wajib diupload.',
            'delivery_note_photo.image' => 'Foto surat jalan harus berupa gambar.',
            'receiver_photo.required' => 'Foto penerima wajib diupload.',
            'receiver_photo.image' => 'Foto penerima harus berupa gambar.',
            'items.required' => 'Minimal satu item harus dicek.',
            'items.*.item_photo.required' => 'Foto item wajib diupload.',
            'items.*.item_photo.image' => 'Foto item harus berupa gambar.',
            'items.*.damage_photo.image' => 'Foto kerusakan harus berupa gambar.',
            'items.*.received_qty.required' => 'Quantity diterima wajib diisi.',
            'items.*.received_qty.numeric' => 'Quantity diterima harus berupa angka.',
            'items.*.damaged_qty.numeric' => 'Quantity rusak harus berupa angka.',
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $locationId = $this->integer('location_id');
                $hasLocationAccess = $this->user()
                    ?->locations()
                    ->where('locations.id', $locationId)
                    ->exists() ?? false;

                if (! $hasLocationAccess) {
                    $validator->errors()->add('location_id', 'Kamu tidak memiliki akses ke lokasi ini.');
                }

                $hodIsAllowed = $this->user()
                    ?->newQuery()
                    ->where('id', $this->integer('selected_hod_id'))
                    ->where('role', UserRole::Hod->value)
                    ->where('is_active', true)
                    ->whereHas('locations', fn ($query) => $query->where('locations.id', $locationId))
                    ->exists() ?? false;

                if (! $hodIsAllowed) {
                    $validator->errors()->add('selected_hod_id', 'User tidak sesuai dengan lokasi ini.');
                }

                $checkedCount = 0;

                foreach ($this->input('items', []) as $index => $item) {
                    if (! is_array($item)) {
                        continue;
                    }

                    $isChecked = (bool) ($item['is_checked'] ?? true);

                    if (! $isChecked) {
                        continue;
                    }

                    $checkedCount++;

                    if (! isset($item['quality_status'])) {
                        $validator->errors()->add("items.{$index}.quality_status", 'Kualitas wajib dipilih.');
                    }

                    if (! isset($item['quantity_status'])) {
                        $validator->errors()->add("items.{$index}.quantity_status", 'Status quantity wajib dipilih.');
                    }

                    if (! $this->hasFile("items.{$index}.item_photo")) {
                        $validator->errors()->add("items.{$index}.item_photo", 'Foto item wajib diupload.');
                    }

                    if (($item['quantity_status'] ?? null) !== QuantityStatus::Match->value && ! isset($item['received_qty'])) {
                        $validator->errors()->add("items.{$index}.received_qty", 'Received quantity wajib diisi.');
                    }

                    if (($item['quality_status'] ?? null) === QualityStatus::Damaged->value) {
                        if (! isset($item['damaged_qty'])) {
                            $validator->errors()->add("items.{$index}.damaged_qty", 'Damaged quantity wajib diisi.');
                        }

                        if (! $this->hasFile("items.{$index}.damage_photo")) {
                            $validator->errors()->add("items.{$index}.damage_photo", 'Damage photo wajib diisi.');
                        }
                    }
                }

                if ($checkedCount === 0) {
                    $validator->errors()->add('items', 'Minimal satu item datang harus dicentang.');
                }
            },
        ];
    }

    public function location(): Location
    {
        return $this->user()
            ->locations()
            ->where('locations.id', $this->integer('location_id'))
            ->firstOrFail();
    }
}
