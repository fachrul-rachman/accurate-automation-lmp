<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Checklist Penerimaan Barang {{ $inspection->pb_number }}</title>
    <style>
        @page { margin: 12mm; size: A4; }
        * { box-sizing: border-box; }
        body { color: #111; font-family: DejaVu Sans, sans-serif; font-size: 10px; line-height: 1.35; margin: 0; }
        h1, h2, h3, p { margin: 0; }
        .header { border-bottom: 2px solid #0a2472; margin-bottom: 10px; padding-bottom: 8px; width: 100%; }
        .header td { border: 0; vertical-align: top; }
        .logo-cell { width: 20%; }
        .logo-cell img { display: block; max-height: 50px; max-width: 95px; }
        .title-cell { text-align: center; width: 47%; }
        .title-cell h1 { font-size: 18px; letter-spacing: .4px; text-decoration: underline; }
        .company-cell { color: #0a2472; font-size: 8.5px; line-height: 1.45; text-align: right; width: 33%; }
        .company-name { font-weight: bold; }
        .info-box { border: 1.5px solid #0a2472; margin-bottom: 10px; padding: 8px 12px; }
        .info-table { width: 100%; }
        .info-table td { border: 0; padding: 2px 0; }
        .info-label { color: #0a2472; font-weight: bold; text-decoration: underline; width: 145px; }
        .info-value { border-bottom: 1px solid #111 !important; }
        table { border-collapse: collapse; width: 100%; }
        .main-table { margin-bottom: 10px; }
        .main-table th { background: #0a2472; border: 1px solid #0a2472; color: #fff; font-size: 8.5px; padding: 5px 3px; text-align: center; vertical-align: middle; }
        .main-table td { border: 1px solid #111; font-size: 8.5px; padding: 4px 3px; vertical-align: middle; }
        .center { text-align: center; }
        .additional-check { border-bottom: 1px solid #333; border-top: 1px solid #333; margin-bottom: 10px; padding: 8px 0; }
        .additional-title { color: #0a2472; font-weight: bold; margin-bottom: 5px; text-decoration: underline; }
        .check-table td { border: 0; padding: 2px 8px; }
        .check-options { text-align: right; white-space: nowrap; width: 145px; }
        .checkbox { border: 1px solid #333; display: inline-block; font-size: 8px; height: 10px; line-height: 9px; margin: 0 3px 0 7px; text-align: center; vertical-align: middle; width: 10px; }
        .notes-title { font-style: italic; font-weight: bold; margin-bottom: 3px; }
        .notes-box { border: 1.5px solid #111; min-height: 45px; padding: 6px; }
        .signature-table { margin-top: 18px; table-layout: fixed; }
        .signature-table td { border: 0; padding: 0 12px; text-align: center; vertical-align: top; width: 33.33%; }
        .signature-role { font-weight: bold; margin-bottom: 24px; }
        .signature-name { font-weight: bold; }
        .signature-line { border-top: 1px solid #111; margin-top: 5px; padding-top: 3px; }
        .muted { color: #64748b; }
        .page-break { page-break-before: always; }
        .round-title { border-bottom: 2px solid #0a2472; color: #0a2472; font-size: 15px; margin-bottom: 10px; padding-bottom: 5px; }
        .section-title { border-bottom: 1px solid #cbd5e1; font-size: 12px; margin: 14px 0 6px; padding-bottom: 4px; }
        .detail-grid td { border: 0; padding: 2px 0; vertical-align: top; }
        .detail-grid .label { color: #64748b; width: 145px; }
        .detail-table th, .detail-table td { border: 1px solid #cbd5e1; padding: 4px; text-align: left; vertical-align: top; }
        .detail-table th { background: #f1f5f9; }
        .photo-table { table-layout: fixed; }
        .photo-table td { border: 0; padding: 0 6px 8px 0; vertical-align: top; width: 33.33%; }
        .photo-card { border: 1px solid #cbd5e1; page-break-inside: avoid; padding: 6px; }
        .photo-title { font-weight: bold; margin-bottom: 2px; }
        .photo-card img { display: block; margin: 6px auto 0; max-height: 170px; max-width: 100%; }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td class="logo-cell">
                @if ($logoDataUri)
                    <img src="{{ $logoDataUri }}" alt="">
                @endif
            </td>
            <td class="title-cell"><h1>CHECKLIST PENERIMAAN BARANG</h1></td>
            <td class="company-cell">
                <div class="company-name">{{ $company['name'] }}</div>
                @foreach ($company['address'] as $line)
                    <div>{{ $line }}</div>
                @endforeach
                <div>Telepon: {{ $company['phone'] }}</div>
            </td>
        </tr>
    </table>

    <div class="info-box">
        <table class="info-table">
            <tr><td class="info-label">No. Checklist:</td><td class="info-value">{{ $inspection->pb_number }}</td></tr>
            <tr><td class="info-label">Tanggal Checklist:</td><td class="info-value">{{ $generatedAt }}</td></tr>
            <tr>
                <td class="info-label">No. Surat Jalan:</td>
                <td class="info-value">
                    @foreach ($inspection->rounds as $round)
                        Round {{ $round->round_number }}: {{ $round->delivery_note_number ?? '-' }}{{ ! $loop->last ? ' | ' : '' }}
                    @endforeach
                </td>
            </tr>
            <tr><td class="info-label">No. PO:</td><td class="info-value">{{ $inspection->po_number ?? '-' }}</td></tr>
            <tr><td class="info-label">Supplier:</td><td class="info-value">{{ $inspection->supplier_name ?? '-' }}</td></tr>
            <tr><td class="info-label">Lokasi Penerima:</td><td class="info-value">{{ $inspection->location?->name ?? '-' }}</td></tr>
        </table>
    </div>

    <table class="main-table">
        <thead>
            <tr>
                <th style="width:25px">No</th>
                <th>Kode Barang</th>
                <th>Nama Barang</th>
                <th style="width:48px">Qty Pesan</th>
                <th style="width:48px">Qty Terima</th>
                <th style="width:45px">Satuan</th>
                <th>Nomor PO</th>
                <th style="width:62px">Kondisi</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($itemSummary as $item)
                <tr>
                    <td class="center">{{ $loop->iteration }}</td>
                    <td>{{ $item['item_code'] ?? '-' }}</td>
                    <td>{{ $item['item_name'] }}</td>
                    <td class="center">{{ $formatQuantity($item['required_qty']) }}</td>
                    <td class="center">{{ $formatQuantity($item['received_qty']) }}</td>
                    <td class="center">{{ $item['unit'] ?? '-' }}</td>
                    <td>{{ $item['po_number'] ?? $inspection->po_number ?? '-' }}</td>
                    <td class="center">{{ $item['damaged_qty'] > 0 ? 'Rusak' : 'Baik' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="additional-check">
        <div class="additional-title">Pengecekan Tambahan:</div>
        <table class="check-table">
            <tr>
                <td>Spesifikasi barang sesuai dengan PO</td>
                <td class="check-options"><span class="checkbox">{{ $checks['specification'] ? '✓' : '' }}</span>Ya <span class="checkbox">{{ ! $checks['specification'] ? '✓' : '' }}</span>Tidak</td>
            </tr>
            <tr>
                <td>Jumlah kuantitas barang sesuai dengan PO</td>
                <td class="check-options"><span class="checkbox">{{ $checks['quantity'] ? '✓' : '' }}</span>Ya <span class="checkbox">{{ ! $checks['quantity'] ? '✓' : '' }}</span>Tidak</td>
            </tr>
            <tr>
                <td>Ada dokumen Surat Jalan/DO/Packing List</td>
                <td class="check-options"><span class="checkbox">{{ $checks['delivery_note'] ? '✓' : '' }}</span>Ya <span class="checkbox">{{ ! $checks['delivery_note'] ? '✓' : '' }}</span>Tidak</td>
            </tr>
        </table>
    </div>

    <div class="notes-title">Catatan:</div>
    <div class="notes-box"></div>

    @if ($finalRound !== null)
        <table class="signature-table">
            <tr>
                <td>
                    <div class="signature-role">Prepared By</div>
                    <div class="signature-name">{{ $finalRound->creator?->name ?? '-' }}</div>
                    <div>{{ \App\Support\DateFormatter::wib($finalRound->submitted_at) ?? '-' }}</div>
                    <div class="signature-line"></div>
                </td>
                <td>
                    <div class="signature-role">Approved By</div>
                    <div class="signature-name">{{ $finalRound->headWarehouseSigner?->name ?? '-' }}</div>
                    <div>{{ \App\Support\DateFormatter::wib($finalRound->head_warehouse_signed_at) ?? '-' }}</div>
                    <div class="signature-line"></div>
                </td>
                <td>
                    <div class="signature-role">Approved By</div>
                    <div class="signature-name">{{ $finalRound->userSigner?->name ?? '-' }}</div>
                    <div>{{ \App\Support\DateFormatter::wib($finalRound->user_signed_at) ?? '-' }}</div>
                    <div class="signature-line"></div>
                </td>
            </tr>
        </table>
    @endif

    @foreach ($inspection->rounds as $round)
        <div class="page-break"></div>
        <h2 class="round-title">Detail Round {{ $round->round_number }}</h2>
        <table class="detail-grid">
            <tr><td class="label">Nomor Surat Jalan</td><td>{{ $round->delivery_note_number ?? '-' }}</td></tr>
            <tr><td class="label">Status</td><td>{{ $round->round_status->label() }}</td></tr>
            <tr><td class="label">Hasil</td><td>{{ $round->completion_result ?? '-' }}</td></tr>
            <tr><td class="label">Spesifikasi sesuai PO</td><td>{{ $round->specification_matches_po ? 'Ya' : 'Tidak' }}</td></tr>
            <tr><td class="label">Warehouse</td><td>{{ $round->creator?->name ?? '-' }} - {{ \App\Support\DateFormatter::wib($round->submitted_at) ?? '-' }}</td></tr>
            <tr><td class="label">Head Warehouse</td><td>{{ $round->headWarehouseSigner?->name ?? '-' }} - {{ \App\Support\DateFormatter::wib($round->head_warehouse_signed_at) ?? '-' }}</td></tr>
            <tr><td class="label">User</td><td>{{ $round->userSigner?->name ?? $round->selectedUser?->name ?? '-' }} - {{ \App\Support\DateFormatter::wib($round->user_signed_at) ?? '-' }}</td></tr>
        </table>

        <h3 class="section-title">Item Round</h3>
        <table class="detail-table">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Item</th>
                    <th>Nomor PO</th>
                    <th>Qty Pesan</th>
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
                        <td>{{ $item->po_number ?? '-' }}</td>
                        <td>{{ $formatQuantity($item->qty) }} {{ $item->unit }}</td>
                        <td>{{ $formatQuantity($item->received_qty) }}</td>
                        <td>{{ $formatQuantity($item->damaged_qty) }}</td>
                        <td>{{ $item->quality_status->label() }}</td>
                        <td>{{ $item->quantity_status->label() }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <h3 class="section-title">Foto Round</h3>
        @forelse ($round->attachments->chunk(3) as $attachmentRow)
            <table class="photo-table">
                <tr>
                    @foreach ($attachmentRow as $attachment)
                        <td>
                            <div class="photo-card">
                                <div class="photo-title">{{ $attachment->attachment_type->value === 'RECEIVER_PHOTO' ? 'Foto Penerima' : 'Foto Surat Jalan' }}</div>
                                @if ($uri = $photoDataUri($attachment->r2_key, $attachment->mime_type))
                                    <img src="{{ $uri }}" alt="">
                                @else
                                    <p class="muted">{{ $attachment->original_filename ?? $attachment->r2_key }}</p>
                                @endif
                            </div>
                        </td>
                    @endforeach
                    @for ($column = $attachmentRow->count(); $column < 3; $column++)
                        <td></td>
                    @endfor
                </tr>
            </table>
        @empty
            <p class="muted">Tidak ada foto round.</p>
        @endforelse

        <h3 class="section-title">Foto Item</h3>
        @php($itemPhotoNumber = 1)
        @foreach ($round->items as $item)
            @foreach ($item->photos->chunk(3) as $photoRow)
                <table class="photo-table">
                    <tr>
                        @foreach ($photoRow as $photo)
                            <td>
                                <div class="photo-card">
                                    <div class="photo-title">{{ $itemPhotoNumber++ }}</div>
                                    @if ($uri = $photoDataUri($photo->r2_key, $photo->mime_type))
                                        <img src="{{ $uri }}" alt="">
                                    @else
                                        <p class="muted">{{ $photo->original_filename ?? $photo->r2_key }}</p>
                                    @endif
                                </div>
                            </td>
                        @endforeach
                        @for ($column = $photoRow->count(); $column < 3; $column++)
                            <td></td>
                        @endfor
                    </tr>
                </table>
            @endforeach
        @endforeach
    @endforeach
</body>
</html>
