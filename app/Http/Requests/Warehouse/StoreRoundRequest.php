<?php

namespace App\Http\Requests\Warehouse;

use App\Enums\QualityStatus;
use App\Enums\QuantityStatus;
use App\Enums\RoundAttachmentType;
use App\Enums\RoundItemPhotoType;
use App\Enums\RoundItemStatus;
use App\Enums\RoundStatus;
use App\Enums\UserRole;
use App\Models\GoodsReceiptRound;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class StoreRoundRequest extends FormRequest
{
    public function authorize(): bool
    {
        $round = $this->route('round');

        return $this->user()?->role === UserRole::Warehouse
            && (! ($round instanceof GoodsReceiptRound) || $round->round_status === RoundStatus::NeedsRevision);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'pb_number' => ['required', 'string', 'max:100'],
            'location_id' => ['required', 'integer', Rule::exists('locations', 'id')->where('is_active', true)],
            'selected_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'specification_matches_po' => ['required', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'delivery_note_number' => ['required', 'string', 'max:100'],
            'delivery_note_photo' => ['nullable', 'image', 'max:5120'],
            'receiver_photo' => ['nullable', 'image', 'max:5120'],
            'delete_attachment_ids' => ['nullable', 'array'],
            'delete_attachment_ids.*' => ['integer'],
            'delete_photo_ids' => ['nullable', 'array'],
            'delete_photo_ids.*' => ['integer'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.is_checked' => ['sometimes', 'boolean'],
            'items.*.accurate_detail_item_id' => ['required', 'string'],
            'items.*.selected_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'items.*.quality_status' => ['nullable', Rule::enum(QualityStatus::class)],
            'items.*.quantity_status' => ['nullable', Rule::enum(QuantityStatus::class)],
            'items.*.received_qty' => ['nullable', 'numeric', 'min:0'],
            'items.*.damaged_qty' => ['nullable', 'numeric', 'min:0'],
            'items.*.item_photo' => ['nullable', 'image', 'max:5120'],
            'items.*.item_photos' => ['nullable', 'array'],
            'items.*.item_photos.*' => ['image', 'max:5120'],
            'items.*.damage_photo' => ['nullable', 'image', 'max:5120'],
            'items.*.damage_photos' => ['nullable', 'array'],
            'items.*.damage_photos.*' => ['image', 'max:5120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.*.selected_user_id.required' => 'User item wajib dipilih.',
            'delivery_note_number.required' => 'Nomor surat jalan wajib diisi.',
            'delivery_note_photo.required' => 'Foto surat jalan wajib diupload.',
            'delivery_note_photo.image' => 'Foto surat jalan harus berupa gambar.',
            'receiver_photo.required' => 'Foto penerima wajib diupload.',
            'receiver_photo.image' => 'Foto penerima harus berupa gambar.',
            'items.required' => 'Minimal satu item harus dicek.',
            'items.*.item_photo.required' => 'Foto item wajib diupload.',
            'items.*.item_photo.image' => 'Foto item harus berupa gambar.',
            'items.*.item_photos.*.image' => 'Foto item harus berupa gambar.',
            'items.*.damage_photo.image' => 'Foto kerusakan harus berupa gambar.',
            'items.*.damage_photos.*.image' => 'Foto kerusakan harus berupa gambar.',
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

                $round = $this->route('round');
                $deletedAttachmentIds = array_map('intval', (array) $this->input('delete_attachment_ids', []));
                $deletedPhotoIds = array_map('intval', (array) $this->input('delete_photo_ids', []));

                if ($round instanceof GoodsReceiptRound) {
                    $round->loadMissing(['attachments', 'items.photos']);

                    if ($round->attachments->whereIn('id', $deletedAttachmentIds)->count() !== count(array_unique($deletedAttachmentIds))) {
                        $validator->errors()->add('delete_attachment_ids', 'Lampiran tidak valid.');
                    }

                    if ($round->items->flatMap(fn ($item) => $item->photos)->whereIn('id', $deletedPhotoIds)->count() !== count(array_unique($deletedPhotoIds))) {
                        $validator->errors()->add('delete_photo_ids', 'Foto item tidak valid.');
                    }

                    $lockedPhotoSelected = $round->items
                        ->reject(fn ($item): bool => $item->head_status === RoundItemStatus::Rejected || $item->user_status === RoundItemStatus::Rejected)
                        ->flatMap(fn ($item) => $item->photos)
                        ->whereIn('id', $deletedPhotoIds)
                        ->isNotEmpty();

                    if ($lockedPhotoSelected) {
                        $validator->errors()->add('delete_photo_ids', 'Foto item yang sudah disetujui tidak dapat dihapus.');
                    }
                }

                if (! $this->hasAnyFile(-1, 'delivery_note_photo', '')) {
                    $hasDeliveryNote = $round instanceof GoodsReceiptRound
                        && $round->attachments->contains(fn ($attachment): bool => $attachment->attachment_type === RoundAttachmentType::DeliveryNote
                            && ! in_array($attachment->id, $deletedAttachmentIds, true));

                    if (! $hasDeliveryNote) {
                        $validator->errors()->add('delivery_note_photo', 'Foto surat jalan wajib diupload.');
                    }
                }

                if (! $this->hasAnyFile(-1, 'receiver_photo', '')) {
                    $hasReceiverPhoto = $round instanceof GoodsReceiptRound
                        && $round->attachments->contains(fn ($attachment): bool => $attachment->attachment_type === RoundAttachmentType::ReceiverPhoto
                            && ! in_array($attachment->id, $deletedAttachmentIds, true));

                    if (! $hasReceiverPhoto) {
                        $validator->errors()->add('receiver_photo', 'Foto penerima wajib diupload.');
                    }
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

                    $selectedUserId = (int) ($item['selected_user_id'] ?? $this->input('selected_user_id'));
                    $userIsAllowed = User::query()
                        ->whereKey($selectedUserId)
                        ->where('role', UserRole::User->value)
                        ->where('is_active', true)
                        ->whereHas('locations', fn ($query) => $query->where('locations.id', $locationId))
                        ->exists();

                    if (! $userIsAllowed) {
                        $validator->errors()->add("items.{$index}.selected_user_id", 'User item tidak sesuai dengan lokasi ini.');
                    }

                    if (! isset($item['quality_status'])) {
                        $validator->errors()->add("items.{$index}.quality_status", 'Kualitas wajib dipilih.');
                    }

                    if (! isset($item['quantity_status'])) {
                        $validator->errors()->add("items.{$index}.quantity_status", 'Status quantity wajib dipilih.');
                    }

                    $existingItem = $round instanceof GoodsReceiptRound
                        ? $round->items->firstWhere('accurate_detail_item_id', (string) ($item['accurate_detail_item_id'] ?? ''))
                        : null;
                    $hasExistingItemPhoto = $existingItem?->photos->contains(
                        fn ($photo): bool => $photo->photo_type === RoundItemPhotoType::Item
                            && ! in_array($photo->id, $deletedPhotoIds, true)
                    ) ?? false;

                    if (! $hasExistingItemPhoto && ! $this->hasAnyFile($index, 'item_photo', 'item_photos')) {
                        $validator->errors()->add("items.{$index}.item_photo", 'Foto item wajib diupload.');
                    }

                    if (($item['quantity_status'] ?? null) !== QuantityStatus::Match->value && ! isset($item['received_qty'])) {
                        $validator->errors()->add("items.{$index}.received_qty", 'Received quantity wajib diisi.');
                    }

                    if (($item['quality_status'] ?? null) === QualityStatus::Damaged->value) {
                        if (! isset($item['damaged_qty'])) {
                            $validator->errors()->add("items.{$index}.damaged_qty", 'Damaged quantity wajib diisi.');
                        }

                        $hasExistingDamagePhoto = $existingItem?->photos->contains(
                            fn ($photo): bool => $photo->photo_type === RoundItemPhotoType::Damage
                                && ! in_array($photo->id, $deletedPhotoIds, true)
                        ) ?? false;

                        if (! $hasExistingDamagePhoto && ! $this->hasAnyFile($index, 'damage_photo', 'damage_photos')) {
                            $validator->errors()->add("items.{$index}.damage_photo", 'Foto kerusakan wajib diupload.');
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

    private function hasAnyFile(int $index, string $singularField, string $arrayField): bool
    {
        $prefix = $index < 0 ? '' : "items.{$index}.";
        $singular = $this->file($prefix.$singularField);

        if ($singular instanceof UploadedFile) {
            return true;
        }

        if ($arrayField === '') {
            return false;
        }

        $files = $this->file($prefix.$arrayField);

        if (! is_array($files)) {
            return false;
        }

        return $files !== [];
    }
}
