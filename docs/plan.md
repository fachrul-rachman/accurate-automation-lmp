# Implementation Plan

## Goal

Build the MVP in phases. Do not skip phases. Later modules depend on earlier modules.

## Phase 1: Project Foundation

Build the application foundation.

Scope:

- Laravel 13 backend.
- React frontend.
- Authentication.
- Base layout.
- Role model foundation.
- Basic dashboard routing by role.
- PWA setup.
- Web app manifest.
- Service worker with asset cache and offline fallback.
- Responsive mobile-first layout baseline for warehouse usage.

Done when:

- Users can log in.
- Users are redirected to the correct dashboard based on role.
- Route protection exists for authenticated pages.
- The app is installable as a PWA on supported browsers.
- Offline access shows a safe fallback state, not stale inspection data.

## Phase 2: Admin Foundation

Build the admin foundation before business modules.

Scope:

- User management.
- Location management.
- HoD mapping per location.
- Role assignment.
- Admin monitoring page skeleton.

Done when:

- Admin can create and edit users.
- Admin can create and edit locations.
- Admin can map HoD users to locations.
- Head Warehouse can be configured as a user role.

## Phase 3: Accurate Integration

Build Accurate read-only integration.

Scope:

- API token validation.
- Accurate host discovery.
- Search PB by number.
- Fetch Receive Item detail by ID.
- Store raw Accurate JSON snapshot.
- Map important fields to local columns.

Reference: `accurate-integration.md`.

Done when:

- Warehouse can search a PB number.
- The system can fetch and store PB detail.
- If PB already exists, the system opens the existing inspection instead of creating a duplicate.

## Phase 4: Warehouse Module

Build the main receiving/checking flow.

Scope:

- Warehouse dashboard.
- Import PB from Accurate.
- Create goods receipt inspection.
- Create receiving round.
- Edit draft round.
- Item checking form.
- Per item photo upload.
- Per round delivery note photo upload.
- Per round receiver photo upload.
- Submit round to HoD.

Rules:

- Warehouse can edit only when round status is `DRAFT`.
- After submit to HoD, item data and photos are locked.
- Only one active round is allowed per PB.

Done when:

- Warehouse can complete a draft round and submit it to the selected HoD.
- The round moves to `WAITING_HOD_SIGNATURE`.

## Phase 5: HoD Signature Module

Build the HoD signature flow after Warehouse module exists.

Scope:

- HoD dashboard for pending signatures.
- Round detail page.
- Click signature action as "Diterima Peminta".

Rules:

- HoD can only see rounds assigned to them.
- HoD cannot edit warehouse data.
- HoD cannot reject.

Done when:

- HoD can sign a waiting round.
- The round moves to `WAITING_HEAD_WAREHOUSE_SIGNATURE`.

## Phase 6: Head Warehouse Signature Module

Build the final signature flow after HoD signature exists.

Scope:

- Head Warehouse dashboard for pending signatures.
- Round detail page.
- Click signature action as "Diketahui".
- Completion calculation after signing.

Rules:

- Head Warehouse cannot edit warehouse data.
- Head Warehouse cannot reject.
- After Head Warehouse signs, the round becomes `SIGNED`.
- If PB is partial, PB remains open for the next round.
- If PB is complete, PDF generation is triggered.

Done when:

- Partial round is signed and locked.
- Complete round is signed, PB is marked complete, and PDF generation is triggered.

## Phase 7: PDF Generation

Build PDF only after Warehouse, HoD, and Head Warehouse flows work.

Scope:

- Generate final PDF per PB.
- Include all rounds.
- Include all photos from all rounds.
- Include accumulated item summary.
- Include audit signature summary per round.
- Include main signature block from the final round only.
- Store PDF in R2.
- Allow download.

Rules:

- PDF is generated automatically after Head Warehouse signs the round that makes the PB complete.
- PDF is not generated for partial PB.
- After PDF is generated, PB is locked.

Done when:

- A complete PB produces a downloadable PDF.
- Partial PB does not produce final PDF.

## Phase 8: Admin Monitoring and Audit Log

Build full admin monitoring after the workflow exists.

Scope:

- Admin PB monitoring dashboard.
- PB status filters.
- Read-only PB detail.
- Round history.
- Audit log viewer.
- PDF view/download if available.

Rules:

- Admin cannot correct business data in MVP.
- Admin does not sign unless the user also has a workflow role.

Done when:

- Admin can monitor any PB status.
- Admin can view logs and PDF.

## Phase 9: PWA and Frontend Hardening

Harden the PWA and mobile frontend behavior after the main workflow exists.

Scope:

- Verify installability.
- Verify manifest icons and display mode.
- Verify service worker registration.
- Verify offline fallback behavior.
- Verify responsive warehouse form usability on mobile.
- Ensure API data for PB, inspection, photos, signatures, and PDF is not aggressively cached.

Done when:

- The app passes basic PWA installability checks.
- Offline mode does not allow unsafe business actions.
- Warehouse form is usable on mobile screen sizes.

## Phase 10: Permission Hardening

Harden access after all core pages exist.

Scope:

- Role-based access.
- Location-based access for Warehouse and HoD.
- Status transition protection.
- Button visibility by role and status.
- Server-side authorization for every action.

Done when:

- Invalid role actions are blocked server-side.
- Invalid status transitions are blocked server-side.

## Phase 11: Testing and Cleanup

Scope:

- Feature tests for main workflows.
- Permission tests.
- Status transition tests.
- R2 upload tests or storage fakes.
- PDF generation tests.
- Audit log tests.
- UI cleanup.

Reference: `testing-checklist.md`.

Done when:

- The test checklist passes.
- No MVP non-scope features are implemented accidentally.
