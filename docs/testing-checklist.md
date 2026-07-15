# Testing Checklist

## Purpose

Use this checklist before declaring implementation complete.

Tests should cover workflow, permissions, statuses, file handling, Accurate integration, PDF generation, and audit logs.

## Accurate Integration Tests

- Search PB by valid number returns data.
- Search PB by invalid number shows not found.
- Detail fetch stores raw JSON snapshot.
- Imported PB maps header fields.
- Imported PB maps item fields.
- Same PB cannot create duplicate active inspection.
- If PB already exists, system opens existing inspection.
- Accurate API failure shows clear error.

## Warehouse Tests

- Warehouse can open dashboard.
- Warehouse can search/import PB.
- Warehouse can create round 1.
- Warehouse can edit round while status is `DRAFT`.
- Warehouse can fill item quality as good.
- Warehouse can fill item quality as damaged.
- Damaged item requires damaged quantity.
- Damaged item requires damage photo.
- Quantity `MATCH` sets received quantity equal to item quantity.
- Quantity `NOT_MATCH` requires received quantity.
- Quantity `PARTIAL` requires received quantity.
- Received quantity cannot exceed item quantity.
- Required item photo blocks submit if missing.
- Required delivery note photo blocks submit if missing.
- Required receiver photo blocks submit if missing.
- Submit to HoD changes round status to `WAITING_HOD_SIGNATURE`.
- After submit, Warehouse cannot edit item data.
- After submit, Warehouse cannot delete or replace photos.

## HoD Tests

- HoD can see only rounds assigned to them.
- HoD cannot see unrelated rounds.
- HoD can view round detail.
- HoD cannot edit item data.
- HoD cannot upload/delete photos.
- HoD can click signature when status is `WAITING_HOD_SIGNATURE`.
- HoD signature sets `hod_signed_by` and `hod_signed_at`.
- HoD signature changes status to `WAITING_HEAD_WAREHOUSE_SIGNATURE`.
- HoD cannot sign twice.
- HoD cannot sign a round not assigned to them.

## Head Warehouse Tests

- Head Warehouse can see rounds waiting for Head Warehouse signature.
- Head Warehouse can view round detail.
- Head Warehouse cannot edit item data.
- Head Warehouse can click signature when status is `WAITING_HEAD_WAREHOUSE_SIGNATURE`.
- Head Warehouse signature sets `head_warehouse_signed_by` and `head_warehouse_signed_at`.
- Head Warehouse signature changes round status to `SIGNED`.
- Head Warehouse cannot sign twice.

## Partial Multi-Round Tests

- Round 1 with quantity short results in PB `OPEN_PARTIAL`.
- Round 1 with damaged item results in PB `OPEN_PARTIAL`.
- Partial PB does not generate PDF.
- Warehouse can create round 2 only after round 1 is signed by Head Warehouse.
- Warehouse cannot create round 2 while round 1 is still active.
- Accumulated good quantity is calculated across signed rounds.
- Round 2 can complete remaining quantity.
- Complete round triggers PDF generation after Head Warehouse signs.

## Complete PB Tests

- PB becomes `COMPLETE` when accumulated good quantity fulfills all item quantity and no outstanding damage remains.
- PB becomes `PDF_GENERATED` after PDF generation succeeds.
- PB is locked after PDF is generated.
- Warehouse cannot create new round after PDF is generated.

## PDF Tests

- PDF is generated automatically only after Head Warehouse signs complete round.
- PDF is not generated for partial PB.
- PDF includes PB data.
- PDF includes PO number if available.
- PDF includes item summary.
- PDF includes all rounds.
- PDF includes all photos from all rounds.
- PDF includes delivery note photos from all rounds.
- PDF includes receiver photos from all rounds.
- PDF includes item photos from all rounds.
- PDF includes damage photos if any.
- PDF main signature block uses only final round signatures.
- PDF audit signature summary shows every round.
- PDF file is stored in R2.
- PDF can be downloaded by allowed roles.

## Admin Tests

- Admin can manage users.
- Admin can manage locations.
- Admin can manage HoD mapping.
- Admin can monitor all PB.
- Admin can filter PB by status.
- Admin can view PB detail read-only.
- Admin can view round history.
- Admin can view audit logs.
- Admin can download PDF if available.
- Admin cannot edit inspection business data.
- Admin does not get workflow signature access unless assigned a workflow role.

## Permission Tests

- Guest cannot access protected pages.
- Warehouse cannot access HoD-only signature action.
- Warehouse cannot access Head Warehouse signature action.
- HoD cannot access Warehouse draft edit action.
- HoD cannot sign unrelated round.
- Head Warehouse cannot edit item data.
- Admin cannot edit inspection data.
- Invalid status transition is blocked server-side.
- Hidden buttons are also protected server-side.

## R2 Storage Tests

- Item photo upload stores file in R2 or fake storage in tests.
- Damage photo upload stores file metadata.
- Delivery note photo upload stores file metadata.
- Receiver photo upload stores file metadata.
- Database stores R2 key, not binary file.
- Missing required upload blocks submit.
- Generated PDF stores R2 key.

## Audit Log Tests

Verify audit log is written for:

- PB import.
- Round creation.
- Draft update.
- Submit to HoD.
- HoD signature.
- Head Warehouse signature.
- PB marked partial.
- PB marked complete.
- PDF generated.
- PDF downloaded.
- User created/updated.
- Location created/updated.
- HoD mapping updated.

## Non-Scope Guard Tests

Confirm MVP does not include:

- Accurate update.
- Reject/request revision.
- Digital signature canvas.
- QR validation.
- WhatsApp/email notification.
- OCR.
- Inventory adjustment.
- Manual PB input without Accurate.
