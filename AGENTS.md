# AGENTS.md

## Purpose

This project is a Laravel 13 + React web application for warehouse goods receipt inspection.

The system lets warehouse users inspect goods received from Accurate Receive Item / PB data, route the receiving round for HoD and Head Warehouse signatures, and generate a final PDF when the PB is complete.

## Core Working Rules for Codex

Follow these rules strictly.

1. Use KISS: simple, effective, production-ready.
2. Do not add features outside the MVP scope.
3. Do not change business rules unless explicitly instructed.
4. Follow `plan.md` as the implementation order.
5. Use `requirements.md` as the source of truth for business rules.
6. Use `technical-design.md` as the source of truth for database, workflow, storage, permission, and audit design.
7. Use `accurate-integration.md` as the source of truth for Accurate API integration.
8. Use `testing-checklist.md` before declaring a phase complete.
9. Do not update data back to Accurate in MVP.
10. Store the full Accurate response snapshot when importing PB data.
11. Store files in Cloudflare R2. Do not store file binaries in the database.
12. HoD and Head Warehouse do not approve or reject. They only sign by clicking a button.
13. Do not implement reject, request revision, digital signature canvas, QR validation, OCR, WhatsApp notification, or inventory adjustment in MVP.

## Development Style

Use a modular monolith structure. Keep domain logic readable and isolated.

Prefer explicit services over scattered controller logic.

Suggested service responsibilities:

- `AccurateReceiveItemService`: Accurate token validation, host discovery, PB search, PB detail fetch.
- `GoodsReceiptInspectionService`: inspection creation, PB uniqueness, round creation, status rules.
- `GoodsReceiptRoundService`: draft save, submit to HoD, HoD sign, Head Warehouse sign.
- `CompletionCalculationService`: accumulated good quantity and partial/complete calculation.
- `PdfGenerationService`: final PDF data assembly and PDF generation.
- `R2StorageService`: file uploads, file metadata, R2 key conventions.
- `AuditLogService`: write audit events.

## Code Quality Rules

- Keep controllers thin.
- Validate every write request with Form Request classes.
- Use policies or gates for role and data access.
- Use enums or constants for statuses. Do not use random strings scattered across the codebase.
- Use database transactions for workflow transitions.
- Write audit logs for important workflow events.
- Keep React components modular and page-oriented.
- Do not build a fancy UI before the workflow is correct.
- Do not infer missing Accurate fields manually.

## File Reading Order

Read files in this order:

1. `requirements.md`
2. `technical-design.md`
3. `accurate-integration.md`
4. `plan.md`
5. `testing-checklist.md`

Then implement phases from `plan.md` sequentially.


## PWA Rule

The application must be implemented as a PWA. Keep it installable and mobile-friendly, but do not implement full offline inspection in MVP. Offline support is limited to frontend asset cache and a safe fallback state.
