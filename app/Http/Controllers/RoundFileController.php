<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\GoodsReceiptRound;
use App\Models\GoodsReceiptRoundItemPhoto;
use App\Models\RoundAttachment;
use App\Models\User;
use App\Services\R2StorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class RoundFileController extends Controller
{
    public function __construct(private readonly R2StorageService $storage) {}

    public function attachment(Request $request, RoundAttachment $attachment): StreamedResponse
    {
        $attachment->loadMissing('round.inspection');
        $this->authorizeRoundFile($request, $attachment->round);

        return Storage::disk($this->diskForKey($attachment->r2_key))->response(
            $attachment->r2_key,
            $attachment->original_filename
        );
    }

    public function itemPhoto(Request $request, GoodsReceiptRoundItemPhoto $photo): StreamedResponse
    {
        $photo->loadMissing('roundItem.round.inspection');
        $this->authorizeRoundFile($request, $photo->roundItem->round);

        return Storage::disk($this->diskForKey($photo->r2_key))->response(
            $photo->r2_key,
            $photo->original_filename
        );
    }

    private function diskForKey(string $key): string
    {
        $activeDisk = $this->storage->disk();

        if (Storage::disk($activeDisk)->exists($key)) {
            return $activeDisk;
        }

        if ($activeDisk !== 'public' && Storage::disk('public')->exists($key)) {
            return 'public';
        }

        return $activeDisk;
    }

    private function authorizeRoundFile(Request $request, GoodsReceiptRound $round): void
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);
        $round->loadMissing('inspection');

        match ($user->role) {
            UserRole::Hod => abort_unless($round->selected_hod_id === $user->id, 403),
            UserRole::Warehouse => abort_unless(
                $user->locations()
                    ->where('locations.id', $round->inspection->location_id)
                    ->exists(),
                403
            ),
            UserRole::HeadWarehouse, UserRole::Admin => null,
        };
    }
}
