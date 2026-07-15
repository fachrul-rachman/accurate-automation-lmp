<?php

namespace App\Http\Requests\Warehouse;

use App\Enums\UserRole;
use App\Models\Location;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class SearchPbRequest extends FormRequest
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
            'location_id' => ['required', 'integer', Rule::exists('locations', 'id')->where('is_active', true)],
            'pb_number' => ['required', 'string', 'max:100', Rule::notIn([''])],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('location_id')) {
                    return;
                }

                $allowed = $this->user()
                    ?->locations()
                    ->where('locations.id', $this->integer('location_id'))
                    ->exists() ?? false;

                if (! $allowed) {
                    $validator->errors()->add('location_id', 'Kamu tidak memiliki akses ke lokasi ini.');
                }
            },
        ];
    }

    public function pbNumber(): string
    {
        return trim((string) $this->validated('pb_number'));
    }

    public function location(): Location
    {
        return $this->user()
            ->locations()
            ->where('locations.id', $this->integer('location_id'))
            ->firstOrFail();
    }
}
