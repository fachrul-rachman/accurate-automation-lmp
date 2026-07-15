# Technical Design

## Stack

- Laravel 13.
- React.
- Relational database.
- Cloudflare R2 for files.
- Accurate API read-only.
- PDF generation handled by Laravel.
- Progressive Web App support.

## Architecture

Use a modular monolith.

Recommended backend modules:

- Auth and user management.
- Location and HoD mapping.
- Accurate integration.
- Goods receipt inspection.
- Receiving round workflow.
- File storage.
- PDF generation.
- Audit logging.
- Admin monitoring.

Keep business logic in services, not inside controllers.

## PWA Technical Requirements

The React frontend must be implemented as a PWA.

Required:

- Web app manifest.
- App name and short name.
- PWA icons in required sizes.
- Theme color and background color.
- Standalone display mode.
- Service worker registration.
- Frontend asset caching.
- Offline fallback page or state.
- Mobile-friendly responsive layout.

Caching rules:

- Cache static frontend assets where safe.
- Do not aggressively cache PB search/import API responses.
- Do not cache signature actions.
- Do not cache photo upload actions.
- Do not cache PDF generation actions.
- Do not let users create or submit receiving rounds while offline in MVP.

Offline MVP behavior:

- If offline, show a clear message that PB search, photo upload, signature, and PDF actions require internet.
- Do not implement background sync for inspection data in MVP.
- Do not queue photo uploads in MVP.

## Database Design

Codex may adjust column types to match Laravel conventions, but must preserve the business model and relationships below.

### `users`

Use Laravel default user fields plus:

- `role`: enum/string.
- `location_id`: nullable foreign key to `locations`.
- `is_active`: boolean.

Roles:

- `WAREHOUSE`
- `HOD`
- `HEAD_WAREHOUSE`
- `ADMIN`

If multi-role support is needed, use a role mapping table. For MVP, one role per user is acceptable unless existing auth requires otherwise.

### `locations`

Fields:

- `id`
- `name`
- `code`: nullable.
- `is_active`
- timestamps.

Purpose:

- Represents business locations.
- Warehouse and HoD users belong to locations.

### `hod_location_mappings`

Fields:

- `id`
- `location_id`
- `hod_user_id`
- `is_active`
- timestamps.

Rules:

- Warehouse can only select HoD mapped to the relevant location.
- A location may have one or more HoD users.

### `goods_receipt_inspections`

Represents one PB inspection record.

Fields:

- `id`
- `location_id`: nullable FK.
- `created_by`: FK users.
- `accurate_receive_item_id`: nullable.
- `pb_number`: string, unique.
- `po_number`: nullable string.
- `supplier_name`: nullable string.
- `pb_date`: nullable date.
- `accurate_raw_json`: json.
- `pb_status`: enum/string.
- `pdf_status`: enum/string.
- timestamps.

Statuses:

- `OPEN_PARTIAL`
- `COMPLETE`
- `PDF_GENERATED`

PDF statuses:

- `NOT_READY`
- `GENERATED`

Rules:

- `pb_number` must be unique.
- If a PB already exists, do not create a duplicate inspection.
- Store the full Accurate detail response in `accurate_raw_json`.

### `goods_receipt_rounds`

Represents one receiving/checking session for a PB.

Fields:

- `id`
- `inspection_id`: FK.
- `round_number`: integer.
- `created_by`: FK users.
- `selected_hod_id`: FK users.
- `round_status`: enum/string.
- `completion_result`: enum/string nullable.
- `specification_matches_po`: boolean.
- `notes`: nullable text.
- `submitted_at`: nullable datetime.
- `hod_signed_by`: nullable FK users.
- `hod_signed_at`: nullable datetime.
- `head_warehouse_signed_by`: nullable FK users.
- `head_warehouse_signed_at`: nullable datetime.
- timestamps.

Round statuses:

- `DRAFT`
- `WAITING_HOD_SIGNATURE`
- `WAITING_HEAD_WAREHOUSE_SIGNATURE`
- `SIGNED`

Completion results:

- `PARTIAL`
- `COMPLETE`

Rules:

- Round number starts at 1 per inspection.
- Only one non-signed round may exist per inspection.
- After submit, round data is locked.

### `goods_receipt_round_items`

Represents item checking data in a round.

Fields:

- `id`
- `round_id`: FK.
- `line_number`: integer.
- `accurate_detail_item_id`: nullable.
- `accurate_item_id`: nullable.
- `item_code`: nullable string.
- `item_name`: string.
- `qty`: decimal.
- `unit`: nullable string.
- `po_number`: nullable string.
- `quality_status`: enum/string.
- `quantity_status`: enum/string.
- `received_qty`: decimal nullable.
- `damaged_qty`: decimal default 0.
- timestamps.

Quality statuses:

- `GOOD`
- `DAMAGED`

Quantity statuses:

- `MATCH`
- `NOT_MATCH`
- `PARTIAL`

Validation rules:

- If quantity status is `MATCH`, set `received_qty = qty`.
- If quantity status is `NOT_MATCH` or `PARTIAL`, `received_qty` is required.
- `received_qty <= qty`.
- `received_qty >= 0`.
- If quality is `DAMAGED`, `damaged_qty` is required.
- `damaged_qty <= received_qty`.

Good quantity formula:

`good_qty = received_qty - damaged_qty`

### `goods_receipt_round_item_photos`

Stores photos linked to round items.

Fields:

- `id`
- `round_item_id`: FK.
- `photo_type`: enum/string.
- `r2_key`: string.
- `original_filename`: nullable string.
- `mime_type`: nullable string.
- `file_size`: nullable integer.
- timestamps.

Photo types:

- `ITEM`
- `DAMAGE`

Rules:

- At least one `ITEM` photo is required for every arrived item.
- `DAMAGE` photo is required if item quality is `DAMAGED`.

### `round_attachments`

Stores photos linked to a round, not a specific item.

Fields:

- `id`
- `round_id`: FK.
- `attachment_type`: enum/string.
- `r2_key`: string.
- `original_filename`: nullable string.
- `mime_type`: nullable string.
- `file_size`: nullable integer.
- timestamps.

Attachment types:

- `DELIVERY_NOTE`
- `RECEIVER_PHOTO`

Rules:

- One delivery note photo is required per round.
- One receiver photo is required per round.

### `pdf_exports`

Stores final PDF metadata.

Fields:

- `id`
- `inspection_id`: FK.
- `generated_by`: nullable FK users.
- `r2_key`: string.
- `file_name`: string.
- `file_size`: nullable integer.
- `generated_at`: datetime.
- timestamps.

Rules:

- One final PDF per PB for MVP.
- PDF is generated automatically after Head Warehouse signs a complete round.

### `audit_logs`

Stores important activity logs.

Fields:

- `id`
- `user_id`: nullable FK users.
- `event`: string.
- `auditable_type`: nullable string.
- `auditable_id`: nullable integer/string.
- `description`: text.
- `old_values`: nullable json.
- `new_values`: nullable json.
- `metadata`: nullable json.
- `ip_address`: nullable string.
- `user_agent`: nullable string.
- `created_at`.

Events:

- `PB_IMPORTED`
- `ROUND_CREATED`
- `ROUND_UPDATED`
- `ROUND_SUBMITTED_TO_HOD`
- `HOD_SIGNED`
- `HEAD_WAREHOUSE_SIGNED`
- `PB_MARKED_PARTIAL`
- `PB_MARKED_COMPLETE`
- `PDF_GENERATED`
- `PDF_DOWNLOADED`
- `USER_CREATED`
- `USER_UPDATED`
- `LOCATION_CREATED`
- `LOCATION_UPDATED`
- `HOD_MAPPING_UPDATED`

## Workflow Rules Per Action

### Import PB From Accurate

Allowed by:

- Warehouse.
- Admin may view/import only if needed for monitoring, but MVP should prioritize Warehouse import.

Rules:

- Search PB by number.
- Fetch detail.
- Store raw JSON snapshot.
- Map important fields.
- If PB already exists, open existing inspection.
- Do not create duplicate inspection for the same PB number.

### Create Round

Allowed by:

- Warehouse.

Rules:

- Allowed if PB is new.
- Allowed if PB is `OPEN_PARTIAL` and all previous rounds are `SIGNED`.
- Not allowed if there is an active non-signed round.
- Not allowed if PB is `PDF_GENERATED`.

### Save Draft

Allowed by:

- Warehouse who created the round, or Warehouse user with access to the location depending on implementation.

Rules:

- Only allowed when round status is `DRAFT`.
- Validate item quantities and required draft data based on save mode.

### Submit to HoD

Allowed by:

- Warehouse.

Rules:

- Round must be `DRAFT`.
- Required item data must be complete.
- Required photos must exist.
- HoD must be selected.
- Selected HoD must be mapped to the location.
- Set round status to `WAITING_HOD_SIGNATURE`.
- Set `submitted_at`.
- Lock round data.

### HoD Sign

Allowed by:

- Selected HoD only.

Rules:

- Round must be `WAITING_HOD_SIGNATURE`.
- Set `hod_signed_by`.
- Set `hod_signed_at`.
- Set status to `WAITING_HEAD_WAREHOUSE_SIGNATURE`.

### Head Warehouse Sign

Allowed by:

- Head Warehouse.

Rules:

- Round must be `WAITING_HEAD_WAREHOUSE_SIGNATURE`.
- Set `head_warehouse_signed_by`.
- Set `head_warehouse_signed_at`.
- Set round status to `SIGNED`.
- Calculate PB completion using all signed rounds.
- If partial, keep PB as `OPEN_PARTIAL`.
- If complete, set PB to `COMPLETE`, generate PDF, then set PB to `PDF_GENERATED` and PDF status to `GENERATED` after success.

## Completion Calculation

For each item from the imported Accurate PB snapshot:

1. Sum good quantity from all signed rounds.
2. Good quantity per round item is `received_qty - damaged_qty`.
3. Compare accumulated good quantity against required quantity.

PB is complete only if:

- Every item has accumulated good quantity greater than or equal to required quantity.
- There is no outstanding damage.

PB is partial if:

- Any required item good quantity is still below required quantity.
- Any damage exists without later good quantity replacing it.

## Permission Matrix

### Warehouse

Can:

- Access own/location PB workflow.
- Import PB.
- Create round.
- Edit draft round.
- Submit to HoD.
- Download final PDF.

Cannot:

- Edit after submit.
- Sign HoD or Head Warehouse actions.
- Access admin pages.

### HoD

Can:

- View rounds assigned to them.
- Sign as HoD.

Cannot:

- Edit inspection data.
- View unrelated HoD tasks.
- Sign Head Warehouse action.

### Head Warehouse

Can:

- View rounds waiting for Head Warehouse signature.
- Sign as Head Warehouse.

Cannot:

- Edit inspection data.

### Admin

Can:

- Manage users.
- Manage locations.
- Manage HoD mapping.
- Monitor PB.
- View logs.
- View/download PDF.

Cannot:

- Edit inspection business data in MVP.

## R2 Storage Convention

Do not store binaries in database.

Store only:

- R2 key.
- original filename.
- mime type.
- file size.
- file type.

Suggested keys:

```text
goods-receipts/{pb_number}/round-{round_number}/delivery-note/{uuid}.{ext}
goods-receipts/{pb_number}/round-{round_number}/receiver/{uuid}.{ext}
goods-receipts/{pb_number}/round-{round_number}/items/{item_code}/{uuid}.{ext}
goods-receipts/{pb_number}/round-{round_number}/items/{item_code}/damage/{uuid}.{ext}
goods-receipts/{pb_number}/pdf/final.pdf
```

Sanitize PB number and item code before using them in paths.

## PDF Data Requirements

PDF should receive structured data from the application, not directly query random pieces everywhere.

Required data object:

- Inspection header.
- Accurate snapshot fields.
- Rounds ordered by round number.
- Item summary with accumulated good/damaged/received quantity.
- Round item details.
- Round attachments.
- Item photos.
- Damage photos.
- Signature summary per round.
- Final signature block from final round.

## Error Handling Rules

- Accurate API failure should show a clear error and write an audit or sync log if implemented.
- R2 upload failure should block submit if the file is required.
- PDF generation failure should not silently mark PDF as generated.
- Invalid status transitions must return validation or authorization errors.
- Missing required photos must block submit to HoD.
