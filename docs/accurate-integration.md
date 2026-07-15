# Accurate Integration

## Purpose

This file describes the MVP Accurate API integration for retrieving PB / Receive Item data.

The application uses Accurate as read-only source data. It must not update Accurate in MVP.

## Reference

Use the existing Accurate reference document as the detailed endpoint reference:

`Accurate md.txt`

Do not copy the whole reference into implementation comments. Use this file as the implementation guide and the reference file for endpoint details.

## Environment Variables

Required:

```env
ACCOUNT_URL=https://account.accurate.id
API_TOKEN=your_api_token
SIGNATURE_SECRET=your_signature_secret
```

Runtime-derived:

```env
ACCURATE_HOST=from api-token.do response
ACCURATE_URL={ACCURATE_HOST}/accurate
```

Do not hardcode Accurate database host.

## Required Headers

Every Accurate request requires:

```http
Authorization: Bearer {API_TOKEN}
X-Api-Timestamp: {timestamp}
X-Api-Signature: {signature}
```

Signature:

```text
HMAC-SHA256(timestamp, SIGNATURE_SECRET)
```

Output format: hex.

## Endpoint Flow

### 1. Validate API Token and Discover Host

Endpoint:

```http
GET https://account.accurate.id/api/api-token.do
```

Use response field:

```text
d.database.host
```

Then build:

```text
ACCURATE_URL = d.database.host + "/accurate"
```

### 2. Search Receive Item by PB Number

Endpoint:

```http
GET {ACCURATE_URL}/api/receive-item/list.do?filter.number={PB_NUMBER}
```

Expected success response:

```json
{
  "s": true,
  "d": [
    { "id": 16201 }
  ]
}
```

Rules:

- If `d` is empty, PB does not exist.
- If more than one result appears, use exact number matching or show an error. Do not guess.
- Use the returned ID to fetch detail.

### 3. Fetch Receive Item Detail

Endpoint:

```http
GET {ACCURATE_URL}/api/receive-item/detail.do?id={RECEIVE_ITEM_ID}
```

Rules:

- Store the full response data as raw JSON snapshot.
- Map important fields into local columns.
- Do not infer missing fields manually.

## Important Field Mapping

### Header

Map when available:

```text
accurate_receive_item_id = d.id
pb_number = d.number
pb_date = d.transDateView or d.shipDate
branch_id = d.branchId
location_text = d.charField1 / d.charField2 / d.charField3 if useful
accurate_raw_json = full d object
```

PO number:

```text
po_number = from Accurate field if available in valid PB detail response
```

MVP rule:

- Store PO number if available.
- Do not block PB processing if PO number is missing, unless the business later changes this rule.
- Do not manually infer PO number from unrelated text.

### Detail Item

Map per item when available:

```text
line_number = detailItem[].seq
accurate_detail_item_id = detailItem[].id
accurate_item_id = detailItem[].itemId
item_code = detailItem[].item.no
item_name = detailItem[].detailName or detailItem[].item.name
qty = detailItem[].quantity
unit = detailItem[].itemUnit.name or detailItem[].availableItemUnitName
warehouse_name = detailItem[].warehouse.name
po_number = item-level PO reference if available
```

## Snapshot Rule

Always store full Accurate detail response as JSON.

Reason:

- Accurate response can contain custom fields.
- Some fields may be needed later.
- PDF and audit need a stable reference, even if Accurate data changes later.

## Local Data Rule

The local application should not mirror all Accurate fields into columns.

Use local columns only for:

- filtering.
- display.
- workflow.
- PDF generation.
- audit readability.

Keep the raw JSON for everything else.

## Error Handling

### Token or Signature Error

Show clear error:

```text
Accurate authentication failed. Please check API token and signature configuration.
```

### PB Not Found

Show clear error:

```text
Nomor PB tidak ditemukan di Accurate.
```

### Detail Fetch Failed

Show clear error:

```text
Data detail PB gagal diambil dari Accurate.
```

### Missing Item Data

If detail item list is empty:

```text
PB ditemukan tetapi tidak memiliki item. PB tidak dapat diproses.
```

## Implementation Notes

Recommended Laravel service:

`AccurateReceiveItemService`

Methods:

- `buildHeaders()`
- `getDatabaseHost()`
- `searchReceiveItemByNumber(string $pbNumber)`
- `getReceiveItemDetail(int|string $id)`
- `importReceiveItem(string $pbNumber)`

Use timeout handling. Do not let Accurate requests hang indefinitely.

## Non-Scope

Do not implement:

- Accurate update.
- Accurate delete.
- Accurate inventory adjustment.
- Accurate PO mutation.
- Manual PB creation without Accurate.
