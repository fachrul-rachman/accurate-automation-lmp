<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Collection;

final class UserDisplay
{
    /**
     * @param  Collection<int, mixed>|null  $locations
     * @return array{name: string, locations: string}
     */
    public static function summary(User $user, ?Collection $locations = null): array
    {
        $locationNames = ($locations ?? $user->locations()->where('is_active', true)->orderBy('name')->get(['locations.name']))
            ->pluck('name')
            ->map(fn ($name): string => (string) $name)
            ->filter()
            ->values()
            ->all();

        return [
            'name' => $user->name,
            'locations' => self::humanList($locationNames) ?: 'Semua lokasi',
        ];
    }

    /**
     * @param  array<int, string>  $items
     */
    private static function humanList(array $items): string
    {
        $items = array_values($items);
        $count = count($items);

        if ($count === 0) {
            return '';
        }

        if ($count === 1) {
            return $items[0];
        }

        if ($count === 2) {
            return "{$items[0]} dan {$items[1]}";
        }

        return implode(', ', array_slice($items, 0, -1)).', dan '.$items[$count - 1];
    }
}
