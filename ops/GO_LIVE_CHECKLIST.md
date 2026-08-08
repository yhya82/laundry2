# Go-Live Checklist — Phase 14

What's actually done vs. what still needs a real human before this is
launched. Split deliberately into two sections: the first is verifiable by
automated tooling and has been; the second genuinely cannot be — no amount
of additional engineering substitutes for a real person using the app.

## Done — automated verification

- [x] **End-to-end test pass, all six §2 workflows** — `tests/Feature/WorkflowsTest.php`,
      5 tests / 52 assertions, all passing against a real MySQL-compatible
      database (not sqlite, not mocked):
  - Walk-in order: Terminal → package selection → payment → order created →
    advanced through every processing stage one at a time → completed →
    receipt generated.
  - Subscription: customer → subscription → cycle auto-created → collection
    auto-scheduled → collected via Terminal subscription mode → cycle
    correctly exhausted.
  - Processing pipeline: covered within the walk-in test — every stage
    transition auto-logged to `order_status_history` with zero app-side
    logging code (the trigger does it).
  - Cancellation: cancel with reason → terminal state confirmed (a second
    cancel attempt and an advance attempt on a cancelled order are both
    rejected, no state change).
  - Payment & refund: partial payment → balance tracked correctly →
    overpayment rejected server-side (trigger cap guard surfaced as a
    friendly error, not a raw DB exception) → full payment → partial refund
    → payment status correctly flips to `partially_refunded`.
  - Damage management: report → review chain (`pending_review` → `approved`,
    confirmed `pending_review` can skip straight past `under_investigation`
    per §2.4) → a direct attempt to set `status=resolved` is rejected →
    resolution via the real endpoint correctly opens that door → store
    credit resolution correctly credits the customer's ledger.
- [x] **Security review of auth, RBAC boundaries, and DB grants** — the
  three-part audit run earlier this project (authorization, injection/XSS,
  auth/config) had every finding fixed and re-verified: order-assignment
  scoping now covers write endpoints (`advance`, `cancel`, `payments.record`)
  not just reads; the Terminal's cart price/quantity are re-derived
  server-side at submit time, closing the price-tampering path; the seeded
  admin account refuses a guessable default password outside local/testing;
  granting Admin-equivalent permissions now requires already being an Admin.
  Re-confirmed just now, live: `laundry_app` (the account the app actually
  runs as, not `root`) still cannot `DELETE` from `activity_log` or run any
  DDL; the app still functions normally under that restricted credential.
- [x] Full automated suite: **30/30 passing** (25 pre-existing + 5 new
  workflow tests), `composer audit` clean.

## Not done — needs a real person, not more engineering

- [ ] **UAT with actual Admin and Laundry-role staff, on real devices at
  each breakpoint.** Nothing here substitutes for a real staff member
  actually using the app. Suggested script, mirroring the acceptance
  criterion below:
  1. Have a Laundry-role staff member (not a developer, not someone who's
     seen the app before) complete one real walk-in order on their own
     phone, unaided — this is the literal acceptance bar for this phase.
  2. Have an Admin-role user (ideally whoever will actually run this
     business day-to-day) walk through: creating a subscription, resolving
     a damage report, checking the dashboard, and the Settings screens —
     specifically the ones this session touched (Backup tab's new alert
     email field, Order assignment toggle).
  3. Test on whatever real devices staff will actually use — a phone
     screen surfaces responsive-layout problems a desktop browser won't.
  4. Capture friction points as they happen, not just hard failures — a
     workflow that technically works but confuses a first-time user is
     still a launch risk.
- [ ] **Staged rollout plan with an explicit rollback path.** Needs a real
  target environment to stage against, which doesn't exist yet in this
  session. At minimum before go-live: confirm what "rollback" concretely
  means for this app (redeploy previous git ref + `php artisan migrate:rollback`
  for the last batch, assuming migrations stay backward-compatible one step
  back — verify that assumption holds for whatever the last pre-launch
  migration turns out to be, rather than taking it on faith).
- [ ] **Go-live checklist sign-off from whoever owns the business
  decision.** Not something that can be marked done from inside a coding
  session — this document is the input to that conversation, not a
  substitute for it.
- [ ] **DR drill at production data volume.** The drill logged in
  `ops/DISASTER_RECOVERY_RUNBOOK.md` proved the backup/restore *mechanism*
  works (real mysqldump, real gzip, real mysql restore, 4.58 seconds
  end-to-end) but against a near-empty test database. Re-run it against a
  realistic data volume before treating the 4-hour RTO as validated at
  scale.
- [ ] **The RPO gap.** Also flagged in the DR runbook: daily backups give
  up to ~24h of exposure, not the confirmed 15-minute target. This needs a
  business decision (accept the gap, or invest in binlog-based point-in-time
  recovery) before launch, not a unilateral engineering call.
- [ ] **A real S3-compatible bucket and a real alert-capable mail
  transport.** Both are fully wired in code (any S3-compatible provider via
  `AWS_*` env vars; email alerts via the existing mail config) but pointed
  at nothing real in this environment — same situation as this project's
  Twilio integration. Needs real credentials before backups actually leave
  the server or alerts actually reach an inbox.

## Acceptance criterion (from the plan)

> A Laundry-role staff member has completed one real walk-in order start to
> finish on a phone, unaided.

This is a statement about a specific event that needs to actually happen,
witnessed, not a state that can be inferred from passing tests. Nothing in
this checklist marks it done, because nothing here is that event.
