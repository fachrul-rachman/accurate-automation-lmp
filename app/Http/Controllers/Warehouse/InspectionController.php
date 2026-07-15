<?php

namespace App\Http\Controllers\Warehouse;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\GoodsReceiptInspection;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class InspectionController extends Controller
{
    public function show(Request $request, GoodsReceiptInspection $inspection): Response
    {
        $user = $request->user();

        abort_unless(
            $user !== null && in_array($user->role, [UserRole::Warehouse, UserRole::Admin], true),
            403
        );

        if ($user->role === UserRole::Warehouse) {
            abort_unless(
                $user
                    ->locations()
                    ->where('locations.id', $inspection->location_id)
                    ->exists(),
                403
            );
        }

        $inspection->load('pdfExport');

        return Inertia::render('warehouse/inspections/show', [
            'inspection' => [
                'id' => $inspection->id,
                'pb_number' => $inspection->pb_number,
                'po_number' => $inspection->po_number,
                'supplier_name' => $inspection->supplier_name,
                'pb_date' => $inspection->pb_date?->toDateString(),
                'pb_status' => $inspection->pb_status->label(),
                'pdf_status' => $inspection->pdf_status->label(),
                'item_count' => is_array($inspection->accurate_raw_json['detailItem'] ?? null)
                    ? count($inspection->accurate_raw_json['detailItem'])
                    : 0,
                'pdf_download_url' => $inspection->pdfExport === null
                    ? null
                    : route('files.pdf-exports.show', $inspection->pdfExport),
            ],
        ]);
    }
}
