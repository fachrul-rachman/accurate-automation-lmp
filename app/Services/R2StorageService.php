<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class R2StorageService
{
    /**
     * @return array{r2_key: string, original_filename: string|null, mime_type: string|null, file_size: int|null}
     */
    public function storePhoto(UploadedFile $file, string $directory): array
    {
        $extension = $file->getClientOriginalExtension() ?: 'jpg';
        $safeDirectory = trim($directory, '/');
        $key = "{$safeDirectory}/".Str::uuid()->toString().".{$extension}";

        Storage::disk($this->disk())->putFileAs($safeDirectory, $file, basename($key));

        return [
            'r2_key' => $key,
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'file_size' => $file->getSize() === false ? null : $file->getSize(),
        ];
    }

    public function disk(): string
    {
        if (app()->runningUnitTests()) {
            return 'public';
        }

        return (string) config('filesystems.goods_receipts_disk', 'public');
    }
}
