<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\PdfExport;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\R2StorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class PdfExportController extends Controller
{
    public function __construct(
        private readonly R2StorageService $storage,
        private readonly AuditLogService $auditLogs,
    ) {}

    public function show(Request $request, PdfExport $pdfExport): StreamedResponse
    {
        $pdfExport->loadMissing('inspection.rounds');
        $this->authorizePdfDownload($request, $pdfExport);
        $user = $request->user();

        $this->auditLogs->write(
            'PDF_DOWNLOADED',
            $user instanceof User ? $user : null,
            $pdfExport->inspection,
            "PDF final PB {$pdfExport->inspection->pb_number} diunduh.",
            metadata: ['pdf_export_id' => $pdfExport->id],
            request: $request,
        );

        return Storage::disk($this->diskForKey($pdfExport->r2_key))->download(
            $pdfExport->r2_key,
            $pdfExport->file_name,
            ['Content-Type' => 'application/pdf']
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

    private function authorizePdfDownload(Request $request, PdfExport $pdfExport): void
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        match ($user->role) {
            UserRole::Admin => null,
            UserRole::Warehouse => abort_unless(
                $user->locations()
                    ->where('locations.id', $pdfExport->inspection->location_id)
                    ->exists(),
                403
            ),
            UserRole::Hod, UserRole::HeadWarehouse => abort(403),
        };
    }
}
