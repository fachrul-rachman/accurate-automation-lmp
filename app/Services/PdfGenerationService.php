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

        $this->loadPdfRelations($inspection);

        $pdfBinary = $this->renderPdf($inspection);
        $fileName = 'Penerimaan-Barang-'.$this->safePathPart($inspection->pb_number).'-final.pdf';
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
            "PDF final Penerimaan Barang {$inspection->pb_number} dibuat.",
            metadata: ['pdf_export_id' => $pdfExport->id],
        );

        return $pdfExport;
    }

    public function preview(GoodsReceiptInspection $inspection): string
    {
        $this->loadPdfRelations($inspection);

        return $this->renderPdf($inspection);
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
            'company' => $this->companyDetails($inspection),
            'checks' => $this->checks($inspection),
            'logoDataUri' => $this->logoDataUri(),
            'formatQuantity' => fn ($quantity): string => $this->formatQuantity((float) $quantity),
            'photoDataUri' => fn (string $key, ?string $mimeType): ?string => $this->photoDataUri($key, $mimeType),
        ])->render());
        $dompdf->setPaper('a4');
        $dompdf->render();

        return $dompdf->output();
    }

    /**
     * @return array<int, array{item_code: string|null, item_name: string, po_number: string|null, unit: string|null, required_qty: float, received_qty: float, damaged_qty: float, good_qty: float}>
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
                    'po_number' => $item->po_number,
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

    /**
     * @return array{name: string, address: array<int, string>, phone: string}
     */
    private function companyDetails(GoodsReceiptInspection $inspection): array
    {
        if (Str::contains(Str::lower($inspection->location?->name ?? ''), 'karawang')) {
            return [
                'name' => 'PT. ALAM HIJAU LESTARI',
                'address' => [
                    'Jl. Kuta Tandingan, KIIC',
                    'Desa Margakaya, Kec. Teluk Jambe Timur,',
                    'Karawang Barat, Jawa Barat 41361',
                ],
                'phone' => '0267-8605555 / 0821 6333 2227',
            ];
        }

        return [
            'name' => 'PT. KARYAPRIMA UTAMASUKSES',
            'address' => [
                'Desa Ranca Buaya Kecamatan Jambe',
                'Kabupaten Tangerang',
                'Provinsi Banten, 15720',
            ],
            'phone' => '+62-21 6230.5858',
        ];
    }

    /**
     * @return array{specification: bool, quantity: bool, delivery_note: bool}
     */
    private function checks(GoodsReceiptInspection $inspection): array
    {
        $itemSummary = $this->itemSummary($inspection);

        return [
            'specification' => $inspection->rounds->every(
                fn ($round): bool => $round->specification_matches_po
            ),
            'quantity' => collect($itemSummary)->every(
                fn (array $item): bool => abs($item['received_qty'] - $item['required_qty']) < 0.0001
            ),
            'delivery_note' => $inspection->rounds->every(
                fn ($round): bool => $round->delivery_note_number !== null
                    && $round->attachments->contains(
                        fn ($attachment): bool => $attachment->attachment_type->value === 'DELIVERY_NOTE'
                    )
            ),
        ];
    }

    private function logoDataUri(): ?string
    {
        $path = public_path('logo pdf.png');

        if (! is_file($path)) {
            return null;
        }

        $content = file_get_contents($path);

        return $content === false ? null : 'data:image/png;base64,'.base64_encode($content);
    }

    private function formatQuantity(float $quantity): string
    {
        return rtrim(rtrim(number_format($quantity, 4, '.', ','), '0'), '.');
    }

    private function loadPdfRelations(GoodsReceiptInspection $inspection): void
    {
        $inspection->load([
            'location',
            'rounds.items.photos',
            'rounds.attachments',
            'rounds.creator',
            'rounds.selectedUser',
            'rounds.userSigner',
            'rounds.headWarehouseSigner',
        ]);
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
