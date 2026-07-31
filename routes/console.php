<?php

use App\Models\GoodsReceiptInspection;
use App\Services\PdfGenerationService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('pdf:preview {pb_number}', function (string $pb_number) {
    $inspection = GoodsReceiptInspection::query()
        ->where('pb_number', $pb_number)
        ->first();

    if ($inspection === null) {
        $this->error("Penerimaan Barang {$pb_number} tidak ditemukan.");

        return 1;
    }

    Storage::disk('local')->put(
        'pdf-preview.pdf',
        app(PdfGenerationService::class)->preview($inspection),
    );

    $this->info('Preview dibuat: '.Storage::disk('local')->path('pdf-preview.pdf'));

    return 0;
})->purpose('Membuat ulang preview PDF tanpa mengubah data operasional');
