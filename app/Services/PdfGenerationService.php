<?php

namespace App\Services;

use App\Models\GoodsReceiptInspection;
use App\Models\PdfExport;
use App\Models\User;
use App\Support\DateFormatter;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class PdfGenerationService
{
    public function __construct(
        private readonly R2StorageService $storage,
        private readonly AuditLogService $auditLogs,
    ) {}

    public function generateFinalPdf(GoodsReceiptInspection $inspection, ?User $generatedBy = null): PdfExport
    {
        $existing = $inspection->pdfExport()->first();

        if ($existing !== null) {
            return $existing;
        }

        $inspection->load([
            'location',
            'rounds.items.photos',
            'rounds.attachments',
            'rounds.creator',
            'rounds.selectedHod',
            'rounds.hodSigner',
            'rounds.headWarehouseSigner',
        ]);

        $pdfBinary = $this->renderPdf($inspection);
        $fileName = 'PB-'.$this->safePathPart($inspection->pb_number).'-final.pdf';
        $key = 'goods-receipts/'.$this->safePathPart($inspection->pb_number).'/pdf/final.pdf';

        Storage::disk($this->storage->disk())->put($key, $pdfBinary);

        $pdfExport = PdfExport::query()->create([
            'inspection_id' => $inspection->id,
            'generated_by' => $generatedBy?->id,
            'r2_key' => $key,
            'file_name' => $fileName,
            'file_size' => strlen($pdfBinary),
            'generated_at' => now(),
        ]);

        $this->auditLogs->write(
            'PDF_GENERATED',
            $generatedBy,
            $inspection,
            "PDF final PB {$inspection->pb_number} dibuat.",
            metadata: ['pdf_export_id' => $pdfExport->id],
        );

        return $pdfExport;
    }

    private function renderPdf(GoodsReceiptInspection $inspection): string
    {
        $options = new Options;
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(view('pdf.goods-receipt-final', [
            'inspection' => $inspection,
            'generatedAt' => DateFormatter::wib(now()),
            'itemSummary' => $this->itemSummary($inspection),
            'finalRound' => $inspection->rounds->last(),
            'photoDataUri' => fn (string $key, ?string $mimeType): ?string => $this->photoDataUri($key, $mimeType),
        ])->render());
        $dompdf->setPaper('a4');
        $dompdf->render();

        return $dompdf->output();
    }

    /**
     * @return array<int, array{item_code: string|null, item_name: string, unit: string|null, required_qty: float, received_qty: float, damaged_qty: float, good_qty: float}>
     */
    private function itemSummary(GoodsReceiptInspection $inspection): array
    {
        $summary = [];

        foreach ($inspection->rounds as $round) {
            foreach ($round->items as $item) {
                $key = $item->accurate_detail_item_id ?? $item->item_code ?? (string) $item->line_number;

                $summary[$key] ??= [
                    'item_code' => $item->item_code,
                    'item_name' => $item->item_name,
                    'unit' => $item->unit,
                    'required_qty' => 0.0,
                    'received_qty' => 0.0,
                    'damaged_qty' => 0.0,
                    'good_qty' => 0.0,
                ];

                $receivedQty = (float) $item->received_qty;
                $damagedQty = (float) $item->damaged_qty;

                $summary[$key]['required_qty'] = max($summary[$key]['required_qty'], (float) $item->qty);
                $summary[$key]['received_qty'] += $receivedQty;
                $summary[$key]['damaged_qty'] += $damagedQty;
                $summary[$key]['good_qty'] += max(0, $receivedQty - $damagedQty);
            }
        }

        return array_values($summary);
    }

    private function photoDataUri(string $key, ?string $mimeType): ?string
    {
        $disk = $this->storage->disk();

        if (! Storage::disk($disk)->exists($key)) {
            return null;
        }

        $content = Storage::disk($disk)->get($key);

        if ($content === null || $content === '') {
            return null;
        }

        return 'data:'.($mimeType ?: 'image/jpeg').';base64,'.base64_encode($content);
    }

    private function safePathPart(string $value): string
    {
        return trim((string) Str::of($value)->replaceMatches('/[^A-Za-z0-9_-]+/', '-'), '-') ?: 'file';
    }
}
