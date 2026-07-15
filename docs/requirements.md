# Requirements

## Product Goal

Build a web application for warehouse goods receipt inspection.

Warehouse users search a PB number from Accurate, inspect received goods, upload required photos, submit the receiving round to HoD, then Head Warehouse signs it. When all required goods are complete, the system generates a final PDF.

## Users and Roles

### Warehouse

Responsible for checking incoming goods.

Can:

- Search/import PB from Accurate.
- Create a goods receipt inspection.
- Create a receiving round.
- Edit a draft round.
- Fill item checking data.
- Upload required photos.
- Submit round to HoD.
- Create the next round if PB is still partial and previous round is fully signed.
- Download PDF if available.

Cannot:

- Edit after submit to HoD.
- Sign as HoD or Head Warehouse unless separately assigned.
- Generate PDF manually.

### HoD

Acts as the requester/receiver side.

Can:

- View rounds assigned to them.
- Click signature as "Diterima Peminta".

Cannot:

- Edit warehouse item data.
- Upload or delete photos.
- Reject.
- Generate PDF manually.

### Head Warehouse

Acts as knowing/acknowledging party.

Can:

- View rounds after HoD signature.
- Click signature as "Diketahui".

Cannot:

- Edit warehouse item data.
- Upload or delete photos.
- Reject.
- Generate PDF manually.

### Admin

Responsible for configuration and monitoring.

Can:

- Manage users.
- Manage locations.
- Manage HoD mapping per location.
- Monitor all PB.
- View PB details read-only.
- View all rounds.
- View audit logs.
- View/download PDF.

Cannot:

- Correct inspection business data in MVP.
- Sign automatically as a workflow role unless the user also has that role.

## Business Concepts

### PB

PB is the Accurate Receive Item / Penerimaan Barang document.

Rules:

- One PB represents one receiving document from Accurate.
- One PB can have multiple receiving rounds in this application.
- One PB can only have one active round at a time.
- PO number should be stored if available from Accurate, but it is not a blocker for MVP until the business decides otherwise.

### Receiving Round

A receiving round represents one goods arrival/checking session for the PB.

Rules:

- Round 1 is created when the PB is first inspected.
- Round 2 and later are created only when the PB is still partial.
- A new round can only be created after the previous round is signed by Head Warehouse.
- A round is locked after warehouse submits it to HoD.

## Status Rules

### PB Status

Use these statuses:

- `OPEN_PARTIAL`
- `COMPLETE`
- `PDF_GENERATED`

Meaning:

- `OPEN_PARTIAL`: PB is not complete yet, and can receive another round if previous round is fully signed.
- `COMPLETE`: PB has fulfilled all required good quantity and no outstanding damage remains.
- `PDF_GENERATED`: Final PDF exists, and PB is locked.

### Round Status

Use these statuses:

- `DRAFT`
- `WAITING_HOD_SIGNATURE`
- `WAITING_HEAD_WAREHOUSE_SIGNATURE`
- `SIGNED`

Transitions:

- `DRAFT` to `WAITING_HOD_SIGNATURE` when Warehouse submits.
- `WAITING_HOD_SIGNATURE` to `WAITING_HEAD_WAREHOUSE_SIGNATURE` when HoD signs.
- `WAITING_HEAD_WAREHOUSE_SIGNATURE` to `SIGNED` when Head Warehouse signs.

No reject status in MVP.

## Partial and Complete Rules

### Partial

PB is partial if any of these is true:

- Item has not arrived.
- Quantity is less than the PB/PO quantity.
- Any received item is damaged.

### Complete

PB is complete if all of these are true:

- Accumulated good quantity from all signed rounds fulfills the required quantity for every item.
- There is no outstanding damaged quantity.
- The final round has been signed by Head Warehouse.

### Over Received Goods

If goods arrive more than the quantity shown from Accurate:

- The extra quantity is not counted.
- The inspection follows the quantity from Accurate.
- Quantity can still be marked as suitable if the required quantity is fulfilled.
- No special over-received status is needed in MVP.

## Warehouse Form Requirements

### Per Item Fields Shown to Warehouse

Show these fields per item:

- Auto increment number.
- Item code.
- Item name.
- Quantity.
- Unit.
- PO number if available.
- Quality checklist.
- Quantity checklist.
- Required item photo upload.

### Quality Checklist

Options:

- `GOOD`
- `DAMAGED`

Display labels:

- Baik.
- Rusak.

Rules:

- If quality is `DAMAGED`, damaged quantity must be filled.
- Damaged quantity cannot be greater than received quantity.
- Damage photo is required when quality is `DAMAGED`.

### Quantity Checklist

Options:

- `MATCH`
- `NOT_MATCH`
- `PARTIAL`

Display labels:

- Sesuai.
- Tidak sesuai.
- Partial.

Rules:

- If quantity is `MATCH`, received quantity equals the item quantity from Accurate.
- If quantity is `NOT_MATCH` or `PARTIAL`, show `received_qty` field.
- `received_qty` is required for `NOT_MATCH` and `PARTIAL`.
- `received_qty` cannot be greater than the item quantity from Accurate.
- `received_qty` cannot be negative.

### Global Round Fields

Per round, warehouse fills:

- Specification matches PO: yes/no.
- Notes.
- Delivery note photo.
- Receiver photo.

Rules:

- Delivery note photo is required per round.
- Receiver photo is required per round.
- Notes are optional, but recommended when specification does not match PO.

## Photo Rules

### Per Round

Required:

- Delivery note photo.
- Receiver photo.

### Per Item

Required:

- At least one item photo for every item that arrived.

Conditional:

- Damage photo is required if item quality is `DAMAGED`.

Rules:

- Photos can be changed while round status is `DRAFT`.
- Photos are locked after submit to HoD.
- Photos from all rounds must be included in the final PDF.

## Signature Rules

HoD and Head Warehouse only sign by clicking a button.

No image signature, no drawing canvas, no uploaded signature file.

PDF displays:

- Name.
- Role/title.
- Timestamp.

Signature meaning:

- Warehouse: diterima pertama oleh.
- HoD: diterima peminta oleh.
- Head Warehouse: diketahui oleh.

## PDF Rules

Final PDF is generated automatically when Head Warehouse signs the round that makes the PB complete.

Rules:

- PDF is per PB.
- PDF is not generated for partial PB.
- PDF includes all rounds.
- PDF includes all photos from all rounds.
- Main signature block uses only the final round signatures.
- Audit signature summary is shown per round.
- PDF is stored in Cloudflare R2.
- After PDF is generated, PB is locked.

Required PDF data:

- PB number.
- PO number if available.
- PB date if available.
- Location.
- Warehouse.
- Item summary.
- Round history.
- All round photos.
- All item photos.
- Damage photos if any.
- HoD and Head Warehouse signature timestamps.
- Final signature block from final round.

## Accurate Rules

- Accurate integration is read-only in MVP.
- The application must not update Accurate.
- Search PB by PB number.
- Fetch PB detail.
- Store full raw JSON snapshot.
- Map important fields into local columns for filtering, display, and PDF.
- Do not infer missing fields manually.

## PWA Requirement

The application must be a Progressive Web App.

MVP PWA rules:

- The application must be installable on supported mobile and desktop browsers.
- The application must be responsive and usable on warehouse mobile devices.
- Provide a web app manifest with app name, short name, icons, theme color, and display mode.
- Provide a service worker for frontend asset caching and offline fallback.
- Offline mode is limited to a clear fallback/error state.
- Do not support full offline goods inspection in MVP.
- PB search/import, photo upload, signatures, PDF generation, and PDF download require internet connection.
- Do not aggressively cache PB, inspection, photo, signature, or PDF API responses.

## MVP Scope

Included:

1. Login/auth.
2. Roles: Warehouse, HoD, Head Warehouse, Admin.
3. User management.
4. Location management.
5. HoD mapping per location.
6. Accurate PB search/import.
7. Accurate raw JSON snapshot.
8. Goods receipt inspection.
9. Receiving round.
10. Warehouse checking form.
11. R2 photo upload.
12. HoD click signature.
13. Head Warehouse click signature.
14. Partial/complete calculation.
15. Auto PDF generation when complete.
16. R2 PDF storage.
17. PDF download.
18. Admin monitoring.
19. Audit log viewer.
20. PWA installability and responsive mobile usage.

## Non-Scope MVP

Not included:

1. Update back to Accurate.
2. Reject or request revision.
3. Digital signature image/canvas.
4. QR validation PDF.
5. WhatsApp/email notification.
6. Native mobile app.
7. Vendor performance analytics.
8. Bulk import/export.
9. Admin correction of business data.
10. Manual PB input without Accurate.
11. Extra approval layers.
12. OCR delivery note.
13. Serial number tracking.
14. Inventory adjustment.
