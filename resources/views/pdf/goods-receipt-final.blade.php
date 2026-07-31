<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Penerimaan Barang {{ $inspection->pb_number }}</title>
    <style>
        @page { margin: 28px; }
        body { color: #0f172a; font-family: DejaVu Sans, sans-serif; font-size: 11px; line-height: 1.45; }
        h1, h2, h3 { margin: 0; }
        h1 { font-size: 20px; }
        h2 { border-bottom: 1px solid #cbd5e1; font-size: 14px; margin-top: 22px; padding-bottom: 6px; }
        h3 { font-size: 12px; margin-top: 14px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #cbd5e1; padding: 6px; text-align: left; vertical-align: top; }
        th { background: #f1f5f9; font-weight: bold; }
        .muted { color: #64748b; }
        .grid { width: 100%; }
        .grid td { border: 0; padding: 3px 0; }
        .label { color: #64748b; width: 140px; }
        .status { display: inline-block; border: 1px solid #94a3b8; border-radius: 4px; padding: 2px 6px; }
        .photo { border: 1px solid #cbd5e1; display: inline-block; margin: 6px 6px 6px 0; padding: 5px; width: 150px; }
        .photo img { display: block; max-height: 120px; max-width: 138px; object-fit: contain; }
        .signature-table td { height: 78px; text-align: center; vertical-align: bottom; width: 33.33%; }
        .page-break { page-break-before: always; }
    </style>
</head>
<body>
    <header>
        <p class="muted">Final PDF Penerimaan Barang</p>
        <h1>Penerimaan Barang {{ $inspection->pb_number }}</h1>
        <p class="muted">Dibuat: {{ $generatedAt }}</p>
    </header>

    <h2>Informasi Penerimaan Barang</h2>
    <table class="grid">
        <tr><td class="label">Nomor Penerimaan Barang</td><td>{{ $inspection->pb_number }}</td></tr>
        <tr><td class="label">Nomor PO</td><td>{{ $inspection->po_number ?? '-' }}</td></tr>
        <tr><td class="label">Supplier</td><td>{{ $inspection->supplier_name ?? '-' }}</td></tr>
        <tr><td class="label">Tanggal Penerimaan Barang</td><td>{{ $inspection->pb_date?->format('d/m/Y') ?? '-' }}</td></tr>
        <tr><td class="label">Lokasi</td><td>{{ $inspection->location?->name ?? '-' }}</td></tr>
        <tr><td class="label">Status</td><td><span class="status">{{ $inspection->pb_status->label() }}</span></td></tr>
    </table>

    <h2>Ringkasan Item</h2>
    <table>
        <thead>
            <tr>
                <th>Kode</th>
                <th>Nama Item</th>
                <th>Qty Penerimaan</th>
                <th>Diterima</th>
                <th>Rusak</th>
                <th>Qty Baik</th>
                <th>Satuan</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($itemSummary as $item)
                <tr>
                    <td>{{ $item['item_code'] ?? '-' }}</td>
                    <td>{{ $item['item_name'] }}</td>
                    <td>{{ number_format($item['required_qty'], 4) }}</td>
                    <td>{{ number_format($item['received_qty'], 4) }}</td>
                    <td>{{ number_format($item['damaged_qty'], 4) }}</td>
                    <td>{{ number_format($item['good_qty'], 4) }}</td>
                    <td>{{ $item['unit'] ?? '-' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h2>Detail Round</h2>
    @foreach ($inspection->rounds as $round)
        <h3>Round {{ $round->round_number }}</h3>
        <table class="grid">
            <tr><td class="label">Status</td><td>{{ $round->round_status->label() }}</td></tr>
            <tr><td class="label">Hasil</td><td>{{ $round->completion_result ?? '-' }}</td></tr>
            <tr><td class="label">Sesuai PO</td><td>{{ $round->specification_matches_po ? 'Ya' : 'Tidak' }}</td></tr>
            <tr><td class="label">Catatan</td><td>{{ $round->notes ?? '-' }}</td></tr>
            <tr><td class="label">Warehouse</td><td>{{ $round->creator?->name ?? '-' }}</td></tr>
            <tr><td class="label">Head Warehouse</td><td>{{ $round->headWarehouseSigner?->name ?? '-' }} - {{ \App\Support\DateFormatter::wib($round->head_warehouse_signed_at) ?? '-' }}</td></tr>
            <tr><td class="label">User</td><td>{{ $round->userSigner?->name ?? $round->selectedUser?->name ?? '-' }} - {{ \App\Support\DateFormatter::wib($round->user_signed_at) ?? '-' }}</td></tr>
        </table>

        <table>
            <thead>
                <tr>
                    <th>No</th>
                    <th>Item</th>
                    <th>Qty Penerimaan</th>
                    <th>Diterima</th>
                    <th>Rusak</th>
                    <th>Kualitas</th>
                    <th>Kuantitas</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($round->items as $item)
                    <tr>
                        <td>{{ $item->line_number }}</td>
                        <td>{{ $item->item_code ?? '-' }}<br>{{ $item->item_name }}</td>
                        <td>{{ number_format((float) $item->qty, 4) }} {{ $item->unit }}</td>
                        <td>{{ number_format((float) $item->received_qty, 4) }}</td>
                        <td>{{ number_format((float) $item->damaged_qty, 4) }}</td>
                        <td>{{ $item->quality_status->label() }}</td>
                        <td>{{ $item->quantity_status->label() }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <h3>Foto Round</h3>
        @forelse ($round->attachments as $attachment)
            <div class="photo">
                <strong>{{ $attachment->attachment_type->value === 'RECEIVER_PHOTO' ? 'Foto Penerima' : 'Foto Surat Jalan' }}</strong>
                @if ($uri = $photoDataUri($attachment->r2_key, $attachment->mime_type))
                    <img src="{{ $uri }}" alt="">
                @else
                    <p class="muted">{{ $attachment->original_filename ?? $attachment->r2_key }}</p>
                @endif
            </div>
        @empty
            <p class="muted">Tidak ada foto round.</p>
        @endforelse

        <h3>Foto Item</h3>
        @foreach ($round->items as $item)
            @foreach ($item->photos as $photo)
                <div class="photo">
                    <strong>{{ $item->item_code ?? 'Item' }}</strong><br>
                    <span class="muted">{{ $photo->photo_type->value === 'DAMAGE' ? 'Foto Kerusakan' : 'Foto Item' }}</span>
                    @if ($uri = $photoDataUri($photo->r2_key, $photo->mime_type))
                        <img src="{{ $uri }}" alt="">
                    @else
                        <p class="muted">{{ $photo->original_filename ?? $photo->r2_key }}</p>
                    @endif
                </div>
            @endforeach
        @endforeach
    @endforeach

    @if ($finalRound !== null)
        <div class="page-break"></div>
        <h2>Blok Tanda Tangan Final</h2>
        <table class="signature-table">
            <tr>
                <td>
                    Warehouse<br>
                    <strong>{{ $finalRound->creator?->name ?? '-' }}</strong>
                </td>
                <td>
                    Head Warehouse<br>
                    <strong>{{ $finalRound->headWarehouseSigner?->name ?? '-' }}</strong>
                </td>
                <td>
                    User<br>
                    <strong>{{ $finalRound->userSigner?->name ?? '-' }}</strong>
                </td>
            </tr>
        </table>
    @endif
</body>
</html>
