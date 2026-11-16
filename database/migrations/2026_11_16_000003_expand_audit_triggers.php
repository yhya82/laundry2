<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Audit log, take two.
 *
 *  - Updates now record BEFORE and AFTER: properties = {"old": {...}, "new": {...}}
 *    for the tracked columns, and an update that touches none of them (e.g.
 *    a remember-token refresh) writes nothing. Inserts record {"new": {...}},
 *    deletes {"old": {...}}. Rows written before this keep their old flat
 *    "after only" shape -- AuditPresenter reads both.
 *  - Many more tables are audited: users, collections, subscription cycles,
 *    refunds, the catalog, roles -- plus role REMOVAL and role-permission
 *    changes, not just role grants.
 *  - Passwords are never copied into the log: a user update records only
 *    "password_changed" (yes/no).
 *
 * Still trigger-only (the app never writes activity_log, and its DB user
 * can't change or delete rows), still causer = @current_user_id. Note that
 * MySQL does not fire triggers for rows removed by an ON DELETE CASCADE.
 */
return new class extends Migration
{
    /**
     * table => [model class, tracked columns]. The first tracked column is
     * also what makes the entry readable (a name, an order number, ...).
     */
    private array $tables = [
        'customers' => ['App\\Models\\Customer', ['full_name', 'phone', 'email', 'customer_type', 'store_credit_balance', 'deleted_at']],
        'orders' => ['App\\Models\\Order', ['order_number', 'status', 'total_amount', 'discount', 'extra_charge', 'cycle_overage_charge', 'washing_machine_id', 'assigned_to', 'collected_by_type', 'cancellation_reason']],
        'subscriptions' => ['App\\Models\\Subscription', ['status', 'subscription_package_id', 'collection_type', 'collections_per_month', 'max_clothes_per_cycle', 'end_date']],
        'payments' => ['App\\Models\\Payment', ['amount', 'credit_applied', 'method', 'status', 'order_id', 'subscription_cycle_id']],
        'expenses' => ['App\\Models\\Expense', ['description', 'amount', 'expense_category_id', 'expense_date', 'deleted_at']],
        'settings' => ['App\\Models\\Setting', ['`key`', 'value']],
        'damage_records' => ['App\\Models\\DamageRecord', ['item_description', 'status']],
        'damage_resolutions' => ['App\\Models\\DamageResolution', ['resolution_type', 'amount']],
        'users' => ['App\\Models\\User', ['name', 'email', 'phone', 'is_active', 'must_change_password']],
        'collections' => ['App\\Models\\Collection', ['status', 'scheduled_date', 'subscription_cycle_id', 'cancellation_reason', 'combined_into_collection_id']],
        'subscription_cycles' => ['App\\Models\\SubscriptionCycle', ['subscription_id', 'starts_on', 'ends_on', 'monthly_price_snapshot', 'max_clothes_snapshot']],
        'refunds' => ['App\\Models\\Refund', ['payment_id', 'amount', 'reason']],
        'laundry_packages' => ['App\\Models\\LaundryPackage', ['name', 'base_price', 'priority', 'clothes_allowed', 'is_active']],
        'subscription_packages' => ['App\\Models\\SubscriptionPackage', ['name', 'monthly_price', 'clothes_allowance', 'collections_per_month', 'max_clothes_per_cycle', 'is_active']],
        'clothing_items' => ['App\\Models\\ClothingItem', ['name', 'clothes_category_id']],
        'clothes_categories' => ['App\\Models\\ClothesCategory', ['name', 'description']],
        'washing_machines' => ['App\\Models\\WashingMachine', ['name', 'is_active']],
        'roles' => ['Spatie\\Permission\\Models\\Role', ['name', 'description']],
    ];

    /** The original (2025_01_02) snapshots, restored by down(). */
    private array $legacy = [
        'customers' => ['App\\Models\\Customer', "JSON_OBJECT('customer_type', NEW.customer_type, 'store_credit_balance', NEW.store_credit_balance)"],
        'orders' => ['App\\Models\\Order', "JSON_OBJECT('status', NEW.status, 'total_amount', NEW.total_amount)"],
        'subscriptions' => ['App\\Models\\Subscription', "JSON_OBJECT('status', NEW.status)"],
        'payments' => ['App\\Models\\Payment', "JSON_OBJECT('amount', NEW.amount, 'status', NEW.status)"],
        'expenses' => ['App\\Models\\Expense', "JSON_OBJECT('amount', NEW.amount)"],
        'settings' => ['App\\Models\\Setting', "JSON_OBJECT('key', NEW.`key`, 'value', NEW.value)"],
        'damage_records' => ['App\\Models\\DamageRecord', "JSON_OBJECT('status', NEW.status)"],
        'damage_resolutions' => ['App\\Models\\DamageResolution', "JSON_OBJECT('resolution_type', NEW.resolution_type, 'amount', NEW.amount)"],
    ];

    public function up(): void
    {
        $this->dropAll();

        foreach ($this->tables as $table => [$model, $columns]) {
            $this->createTableTriggers($table, $model, $columns);
        }

        $this->createPivotTriggers();
    }

    public function down(): void
    {
        $this->dropAll();

        foreach ($this->legacy as $table => [$model, $snapshot]) {
            foreach (['insert' => 'created', 'update' => 'updated'] as $event => $verb) {
                DB::unprepared("
                    CREATE TRIGGER trg_{$table}_audit_{$event}
                    AFTER ".strtoupper($event)." ON {$table}
                    FOR EACH ROW
                    BEGIN
                        INSERT INTO activity_log (log_name, description, subject_type, subject_id, causer_type, causer_id, properties, created_at)
                        VALUES ('default', '{$verb} {$table}', ".$this->str($model).", NEW.id, 'App\\\\Models\\\\User', @current_user_id, {$snapshot}, NOW());
                    END
                ");
            }
        }

        DB::unprepared("
            CREATE TRIGGER trg_model_has_roles_audit_insert
            AFTER INSERT ON model_has_roles
            FOR EACH ROW
            BEGIN
                INSERT INTO activity_log (log_name, description, subject_type, subject_id, causer_type, causer_id, properties, created_at)
                VALUES ('default', 'role granted', NEW.model_type, NEW.model_id, 'App\\\\Models\\\\User', @current_user_id, JSON_OBJECT('role_id', NEW.role_id), NOW());
            END
        ");
    }

    private function createTableTriggers(string $table, string $model, array $columns): void
    {
        $subject = $this->str($model);
        $isUsers = $table === 'users';

        $json = function (string $row) use ($columns, $isUsers, $table) {
            $pairs = collect($columns)->map(fn ($c) => "'".trim($c, '`')."', {$row}.{$c}");

            // Never the hash -- only whether it changed (update only).
            if ($isUsers && $row !== 'NEW_INSERT') {
                $pairs->push("'password_changed', ".($row === 'NEW' ? 'NOT (NEW.password <=> OLD.password)' : 'FALSE'));
            }

            return 'JSON_OBJECT('.$pairs->implode(', ').')';
        };

        $insertJson = 'JSON_OBJECT('.collect($columns)->map(fn ($c) => "'".trim($c, '`')."', NEW.{$c}")->implode(', ').')';
        $deleteJson = 'JSON_OBJECT('.collect($columns)->map(fn ($c) => "'".trim($c, '`')."', OLD.{$c}")->implode(', ').')';

        // Only log an update when a tracked column (or the password) changed.
        $changed = collect($columns)->map(fn ($c) => "NOT (NEW.{$c} <=> OLD.{$c})");
        if ($isUsers) {
            $changed->push('NOT (NEW.password <=> OLD.password)');
        }
        $changedCondition = $changed->implode(' OR ');

        DB::unprepared("
            CREATE TRIGGER trg_{$table}_audit_insert
            AFTER INSERT ON {$table}
            FOR EACH ROW
            BEGIN
                INSERT INTO activity_log (log_name, description, subject_type, subject_id, causer_type, causer_id, properties, created_at)
                VALUES ('default', 'created {$table}', {$subject}, NEW.id, 'App\\\\Models\\\\User', @current_user_id, JSON_OBJECT('new', {$insertJson}), NOW());
            END
        ");

        DB::unprepared("
            CREATE TRIGGER trg_{$table}_audit_update
            AFTER UPDATE ON {$table}
            FOR EACH ROW
            BEGIN
                IF {$changedCondition} THEN
                    INSERT INTO activity_log (log_name, description, subject_type, subject_id, causer_type, causer_id, properties, created_at)
                    VALUES ('default', 'updated {$table}', {$subject}, NEW.id, 'App\\\\Models\\\\User', @current_user_id, JSON_OBJECT('old', {$json('OLD')}, 'new', {$json('NEW')}), NOW());
                END IF;
            END
        ");

        DB::unprepared("
            CREATE TRIGGER trg_{$table}_audit_delete
            AFTER DELETE ON {$table}
            FOR EACH ROW
            BEGIN
                INSERT INTO activity_log (log_name, description, subject_type, subject_id, causer_type, causer_id, properties, created_at)
                VALUES ('default', 'deleted {$table}', {$subject}, OLD.id, 'App\\\\Models\\\\User', @current_user_id, JSON_OBJECT('old', {$deleteJson}), NOW());
            END
        ");
    }

    /**
     * Spatie's pivots have no id column, so they get their own triggers:
     * a role granted/removed to a user, a permission granted/removed on a role.
     */
    private function createPivotTriggers(): void
    {
        foreach (['insert' => ['NEW', 'role granted'], 'delete' => ['OLD', 'role removed']] as $event => [$row, $description]) {
            DB::unprepared("
                CREATE TRIGGER trg_model_has_roles_audit_{$event}
                AFTER ".strtoupper($event)." ON model_has_roles
                FOR EACH ROW
                BEGIN
                    INSERT INTO activity_log (log_name, description, subject_type, subject_id, causer_type, causer_id, properties, created_at)
                    VALUES ('default', '{$description}', {$row}.model_type, {$row}.model_id, 'App\\\\Models\\\\User', @current_user_id,
                        JSON_OBJECT('role', (SELECT name FROM roles WHERE id = {$row}.role_id), 'role_id', {$row}.role_id), NOW());
                END
            ");
        }

        foreach (['insert' => ['NEW', 'permission granted'], 'delete' => ['OLD', 'permission removed']] as $event => [$row, $description]) {
            DB::unprepared("
                CREATE TRIGGER trg_role_has_permissions_audit_{$event}
                AFTER ".strtoupper($event)." ON role_has_permissions
                FOR EACH ROW
                BEGIN
                    INSERT INTO activity_log (log_name, description, subject_type, subject_id, causer_type, causer_id, properties, created_at)
                    VALUES ('default', '{$description}', 'Spatie\\\\Permission\\\\Models\\\\Role', {$row}.role_id, 'App\\\\Models\\\\User', @current_user_id,
                        JSON_OBJECT('permission', (SELECT name FROM permissions WHERE id = {$row}.permission_id), 'role', (SELECT name FROM roles WHERE id = {$row}.role_id)), NOW());
                END
            ");
        }
    }

    private function dropAll(): void
    {
        foreach (array_keys($this->tables) as $table) {
            foreach (['insert', 'update', 'delete'] as $event) {
                DB::unprepared("DROP TRIGGER IF EXISTS trg_{$table}_audit_{$event}");
            }
        }

        foreach (['model_has_roles', 'role_has_permissions'] as $pivot) {
            foreach (['insert', 'delete'] as $event) {
                DB::unprepared("DROP TRIGGER IF EXISTS trg_{$pivot}_audit_{$event}");
            }
        }
    }

    /** A PHP string as a MySQL string literal (backslashes doubled). */
    private function str(string $value): string
    {
        return "'".str_replace(['\\', "'"], ['\\\\', "\\'"], $value)."'";
    }
};
