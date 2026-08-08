# Trigger Inventory

Every MySQL/MariaDB trigger the schema installs, where it's defined, and its
verification status. Source: `database/migrations/2025_01_02_000001_create_laundry_triggers.php`,
`database/migrations/2026_08_05_132208_extend_payment_cap_guard_for_subscription_cycles.php`,
and `database/migrations/2026_08_07_150000_create_damage_status_history_table.php`.

**29 triggers total**: 16 audit triggers (2 per audited table × 8 tables) + 13
named business-rule/workflow triggers. All triggers execute with the
*definer's* privileges, not the connecting user's — this is what lets the
audit triggers write into `activity_log` even though `laundry_app` itself has
no direct write access needed beyond what its table grants allow.

Last verification pass: 2026-08-07, against a live MariaDB 10.4.32 instance,
using throwaway fixtures created and torn down in the same session (not
reasoned about from code alone).

## Audit triggers (16)

One `AFTER INSERT` + one `AFTER UPDATE` trigger per table below, each writing
a row into `activity_log` with a table-specific JSON snapshot and
`causer_id = @current_user_id` (set once per request by
`SetCurrentUserForAudit` middleware).

| Table | Insert trigger | Update trigger | Verified this pass |
|---|---|---|---|
| customers | `trg_customers_audit_insert` | `trg_customers_audit_update` | ✅ fired (insert) |
| orders | `trg_orders_audit_insert` | `trg_orders_audit_update` | ✅ fired (insert, via fixture order creation) |
| subscriptions | `trg_subscriptions_audit_insert` | `trg_subscriptions_audit_update` | Not re-fired this pass — same generated template as the two rows above |
| payments | `trg_payments_audit_insert` | `trg_payments_audit_update` | Not re-fired this pass — same generated template |
| expenses | `trg_expenses_audit_insert` | `trg_expenses_audit_update` | Not re-fired this pass — same generated template |
| settings | `trg_settings_audit_insert` | `trg_settings_audit_update` | Not re-fired this pass — same generated template |
| damage_records | `trg_damage_records_audit_insert` | `trg_damage_records_audit_update` | Not re-fired this pass — same generated template |
| damage_resolutions | `trg_damage_resolutions_audit_insert` | `trg_damage_resolutions_audit_update` | Not re-fired this pass — same generated template |

Plus one standalone: **`trg_model_has_roles_audit_insert`** (Spatie's
polymorphic pivot has no surrogate `id`, so it can't go through the generic
per-table loop above) — ✅ confirmed firing live: `AdminUserSeeder`'s
`syncRoles(['Admin'])` call produced a real `activity_log` row referencing
the role grant.

All 16 per-table triggers are generated from one PHP loop
(`createAuditTriggers()`) with an identical body shape, parameterized only by
table name and JSON snapshot expression — verifying the pattern holds on 2 of
8 tables (customers, and the model_has_roles special case) is a reasonable
confidence level for the rest, but is not the same as having independently
fired all 16. Recommend re-running this same fixture-based check across the
remaining 6 tables before a compliance sign-off, not just before a casual
verification pass.

## Business-rule / workflow triggers (13)

| Trigger | Table / event | Rule enforced | Verified this pass |
|---|---|---|---|
| `trg_orders_status_transition_guard` | `orders`, `BEFORE UPDATE` | Only the immediate next stage (or `cancelled` from any non-terminal stage) is a legal status change; `completed`/`cancelled` are terminal | ✅ blocked a raw-SQL stage-skip attempt (`received` → `washing`) |
| `trg_orders_status_history_log` | `orders`, `AFTER UPDATE` | Every status change auto-writes an `order_status_history` row — the app never logs this itself | ✅ a valid single-step advance produced a history row with zero app-side logging code |
| `trg_subscriptions_customer_type_guard` | `subscriptions`, `BEFORE INSERT` | A subscription can only be created for a customer already typed `subscription` | ✅ blocked a raw-SQL insert against a `walk_in` customer |
| `trg_damage_records_block_cancelled_order` | `damage_records`, `BEFORE INSERT` | Damage cannot be reported against an already-cancelled order | ✅ blocked once tested against a genuinely `cancelled` order (first attempt was a false negative caused by `Order::$fillable` correctly rejecting a mass-assigned `status` in the test fixture, not a trigger bug — see note below) |
| `trg_damage_records_resolve_guard` | `damage_records`, `BEFORE UPDATE` | `status` can only reach `resolved` via a `damage_resolutions` row, never a direct `UPDATE` | ✅ blocked a raw-SQL `UPDATE damage_records SET status='resolved'` |
| `trg_damage_resolutions_apply_status` | `damage_resolutions`, `AFTER INSERT` | Inserting a resolution automatically flips the parent `damage_records.status` to `resolved` — the only door into that value | ✅ confirmed the flip happened from a real resolution insert |
| `trg_damage_records_status_history_log` | `damage_records`, `AFTER UPDATE` | Every status change (review-chain move or the resolve-triggered flip above) auto-writes a `damage_status_history` row, same pattern as `trg_orders_status_history_log` | ✅ a `transition()` call produced a history row with zero app-side logging code |
| `trg_payments_cap_guard` (base) / extended version | `payments`, `BEFORE INSERT` | Sum of completed payments against an order (or, post-2026-08-05, a subscription cycle) can never exceed its total | ✅ blocked a payment of 150 against an order with total 100 |
| `trg_refunds_cap_guard` | `refunds`, `BEFORE INSERT` | A refund can never exceed what remains unrefunded on its parent payment | ✅ blocked a refund of 150 against a payment of 100 |
| `trg_refunds_apply_payment_status` | `refunds`, `AFTER INSERT` | Payment status auto-flips to `partially_refunded` / `refunded` based on cumulative refunds | ✅ a 40-of-100 refund correctly flipped the payment to `partially_refunded` |
| `trg_credit_transactions_overdraft_guard` | `credit_transactions`, `BEFORE INSERT` | Store credit can never go negative; also computes `balance_after` | ✅ blocked a 500 debit against a genuine 100 balance; correctly computed `balance_after` for both a credit and a debit |
| `trg_credit_transactions_apply_balance` | `credit_transactions`, `AFTER INSERT` | Applies the computed `balance_after` back onto `customers.store_credit_balance` | ✅ balance correctly moved 0 → 100 (credit) → 70 (debit) |
| `trg_model_has_roles_audit_insert` | see Audit triggers above | | ✅ (listed once, not twice) |

**Note on the false negative during this pass:** the first attempt at
`trg_damage_records_block_cancelled_order` appeared not to fire. Investigation
showed the test fixture's `Order::create(['status' => 'cancelled', ...])` had
silently dropped `status`, because `status` is deliberately excluded from
`Order::$fillable` — the same guard the security review confirmed prevents a
client from mass-assigning an order's status directly. The order was actually
sitting at the default `received`, so the trigger correctly allowed the
insert. Re-tested against a genuinely `cancelled` order (set via raw SQL,
bypassing the app's guard on purpose to isolate the DB-level rule) and the
trigger fired as expected. Documented here because it's a good illustration
of why DB-level and app-level guards are usually tested together — an
app-level guard doing its job can look, from the DB side alone, like a
trigger failing.

## DB-privilege boundary (Phase 12's other verification task)

Tested directly against `laundry_app` (the account `.env` now actually uses,
not `root`):

- `UPDATE`/`INSERT` on ordinary tables (e.g. `customers`): **allowed**.
- `UPDATE`/`DELETE` on `activity_log`: **denied** — `1142 UPDATE/DELETE command denied to user 'laundry_app'`.
- `UPDATE`/`DELETE` on `order_status_history`: **denied**, same error class.
- `CREATE TABLE` (or any DDL): **denied** — `laundry_app` has no schema-modification privilege at all, only the DML grants `ops_grants.sql` lists per table.

`ops_grants.sql` itself needed two corrections before this held true against
the *current* schema: it predated `subscription_cycles` and
`washing_machines` (both would have silently fallen through to
SELECT+INSERT-only, breaking real functionality — cycle-closing and
washing-machine retirement), and the `laundry_app` account already existed
from an earlier point in this project's history with two stale grants
(`user_roles`, `role_permissions`) left over from before the Spatie RBAC
migration replaced those table names. Both are fixed now — see
`ops_grants.sql`'s current state and git history for the exact diff.
