<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Audit tables get an AFTER INSERT + AFTER UPDATE trigger writing a minimal
     * snapshot into activity_log. causer_id is read from the @current_user_id
     * session variable — the app is expected to set it once per request
     * (SET @current_user_id = ?) once authentication middleware exists (Phase 02).
     * Until then it will simply record NULL.
     *
     * @var array<string, array{model:string, snapshot:string}>
     */
    private array $auditedTables = [
        'customers' => [
            'model' => 'App\\\\Models\\\\Customer',
            'snapshot' => "JSON_OBJECT('customer_type', NEW.customer_type, 'store_credit_balance', NEW.store_credit_balance)",
        ],
        'orders' => [
            'model' => 'App\\\\Models\\\\Order',
            'snapshot' => "JSON_OBJECT('status', NEW.status, 'total_amount', NEW.total_amount)",
        ],
        'subscriptions' => [
            'model' => 'App\\\\Models\\\\Subscription',
            'snapshot' => "JSON_OBJECT('status', NEW.status)",
        ],
        'payments' => [
            'model' => 'App\\\\Models\\\\Payment',
            'snapshot' => "JSON_OBJECT('amount', NEW.amount, 'status', NEW.status)",
        ],
        'expenses' => [
            'model' => 'App\\\\Models\\\\Expense',
            'snapshot' => "JSON_OBJECT('amount', NEW.amount)",
        ],
        'settings' => [
            'model' => 'App\\\\Models\\\\Setting',
            'snapshot' => "JSON_OBJECT('key', NEW.`key`, 'value', NEW.value)",
        ],
        'damage_records' => [
            'model' => 'App\\\\Models\\\\DamageRecord',
            'snapshot' => "JSON_OBJECT('status', NEW.status)",
        ],
        'damage_resolutions' => [
            'model' => 'App\\\\Models\\\\DamageResolution',
            'snapshot' => "JSON_OBJECT('resolution_type', NEW.resolution_type, 'amount', NEW.amount)",
        ],
    ];

    public function up(): void
    {
        $this->createBusinessRuleTriggers();
        $this->createAuditTriggers();
        $this->createRoleAssignmentAuditTrigger();
    }

    public function down(): void
    {
        foreach (array_keys($this->auditedTables) as $table) {
            DB::unprepared("DROP TRIGGER IF EXISTS trg_{$table}_audit_insert");
            DB::unprepared("DROP TRIGGER IF EXISTS trg_{$table}_audit_update");
        }

        $businessTriggers = [
            'trg_orders_status_transition_guard',
            'trg_orders_status_history_log',
            'trg_subscriptions_customer_type_guard',
            'trg_damage_records_block_cancelled_order',
            'trg_damage_records_resolve_guard',
            'trg_damage_resolutions_apply_status',
            'trg_payments_cap_guard',
            'trg_refunds_cap_guard',
            'trg_refunds_apply_payment_status',
            'trg_credit_transactions_overdraft_guard',
            'trg_credit_transactions_apply_balance',
        ];

        foreach ($businessTriggers as $trigger) {
            DB::unprepared("DROP TRIGGER IF EXISTS {$trigger}");
        }

        DB::unprepared('DROP TRIGGER IF EXISTS trg_model_has_roles_audit_insert');
    }

    private function createAuditTriggers(): void
    {
        foreach ($this->auditedTables as $table => $config) {
            $model = $config['model'];
            $snapshot = $config['snapshot'];

            DB::unprepared("
                CREATE TRIGGER trg_{$table}_audit_insert
                AFTER INSERT ON {$table}
                FOR EACH ROW
                BEGIN
                    INSERT INTO activity_log (log_name, description, subject_type, subject_id, causer_type, causer_id, properties, created_at)
                    VALUES ('default', 'created {$table}', '{$model}', NEW.id, 'App\\\\Models\\\\User', @current_user_id, {$snapshot}, NOW());
                END
            ");

            DB::unprepared("
                CREATE TRIGGER trg_{$table}_audit_update
                AFTER UPDATE ON {$table}
                FOR EACH ROW
                BEGIN
                    INSERT INTO activity_log (log_name, description, subject_type, subject_id, causer_type, causer_id, properties, created_at)
                    VALUES ('default', 'updated {$table}', '{$model}', NEW.id, 'App\\\\Models\\\\User', @current_user_id, {$snapshot}, NOW());
                END
            ");
        }
    }

    private function createBusinessRuleTriggers(): void
    {
        // Orders can only advance to the immediate next processing stage, or to
        // 'cancelled' from any non-terminal stage. completed/cancelled are terminal.
        DB::unprepared("
            CREATE TRIGGER trg_orders_status_transition_guard
            BEFORE UPDATE ON orders
            FOR EACH ROW
            BEGIN
                IF NEW.status <> OLD.status THEN
                    IF OLD.status IN ('completed', 'cancelled') THEN
                        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Order status is terminal and cannot change';
                    ELSEIF NEW.status = 'cancelled' THEN
                        -- allowed from any non-terminal stage
                        SET @noop = 1;
                    ELSEIF NOT (
                        (OLD.status = 'received' AND NEW.status = 'sorting') OR
                        (OLD.status = 'sorting' AND NEW.status = 'washing') OR
                        (OLD.status = 'washing' AND NEW.status = 'drying') OR
                        (OLD.status = 'drying' AND NEW.status = 'ironing') OR
                        (OLD.status = 'ironing' AND NEW.status = 'packaging') OR
                        (OLD.status = 'packaging' AND NEW.status = 'completed')
                    ) THEN
                        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid order status transition';
                    END IF;
                END IF;
            END
        ");

        DB::unprepared("
            CREATE TRIGGER trg_orders_status_history_log
            AFTER UPDATE ON orders
            FOR EACH ROW
            BEGIN
                IF NEW.status <> OLD.status THEN
                    INSERT INTO order_status_history (order_id, from_status, to_status, changed_by, created_at)
                    VALUES (NEW.id, OLD.status, NEW.status, @current_user_id, NOW());
                END IF;
            END
        ");

        // A subscription can only be created for a customer already typed 'subscription'.
        DB::unprepared("
            CREATE TRIGGER trg_subscriptions_customer_type_guard
            BEFORE INSERT ON subscriptions
            FOR EACH ROW
            BEGIN
                DECLARE cust_type VARCHAR(20);
                SELECT customer_type INTO cust_type FROM customers WHERE id = NEW.customer_id;
                IF cust_type IS NULL OR cust_type <> 'subscription' THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Customer must be of type subscription';
                END IF;
            END
        ");

        // Damage cannot be reported against an already-cancelled order.
        DB::unprepared("
            CREATE TRIGGER trg_damage_records_block_cancelled_order
            BEFORE INSERT ON damage_records
            FOR EACH ROW
            BEGIN
                DECLARE ord_status VARCHAR(20);
                SELECT status INTO ord_status FROM orders WHERE id = NEW.order_id;
                IF ord_status = 'cancelled' THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cannot report damage against a cancelled order';
                END IF;
            END
        ");

        // 'resolved' can only be reached via trg_damage_resolutions_apply_status below,
        // never set directly by the application.
        DB::unprepared("
            CREATE TRIGGER trg_damage_records_resolve_guard
            BEFORE UPDATE ON damage_records
            FOR EACH ROW
            BEGIN
                IF NEW.status = 'resolved' AND OLD.status <> 'resolved' AND (@allow_damage_resolve IS NULL OR @allow_damage_resolve <> 1) THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'damage_records.status can only reach resolved via a damage_resolutions row';
                END IF;
            END
        ");

        DB::unprepared("
            CREATE TRIGGER trg_damage_resolutions_apply_status
            AFTER INSERT ON damage_resolutions
            FOR EACH ROW
            BEGIN
                SET @allow_damage_resolve = 1;
                UPDATE damage_records SET status = 'resolved' WHERE id = NEW.damage_record_id;
                SET @allow_damage_resolve = NULL;
            END
        ");

        // Sum of completed payments against an order can never exceed its total_amount.
        DB::unprepared("
            CREATE TRIGGER trg_payments_cap_guard
            BEFORE INSERT ON payments
            FOR EACH ROW
            BEGIN
                DECLARE existing_total DECIMAL(10,2);
                DECLARE order_total DECIMAL(10,2);
                IF NEW.order_id IS NOT NULL THEN
                    SELECT COALESCE(SUM(amount), 0) INTO existing_total FROM payments WHERE order_id = NEW.order_id AND status = 'completed';
                    SELECT total_amount INTO order_total FROM orders WHERE id = NEW.order_id;
                    IF (existing_total + NEW.amount) > order_total THEN
                        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Payment would exceed order total';
                    END IF;
                END IF;
            END
        ");

        // A refund can never exceed what remains unrefunded on its parent payment.
        DB::unprepared("
            CREATE TRIGGER trg_refunds_cap_guard
            BEFORE INSERT ON refunds
            FOR EACH ROW
            BEGIN
                DECLARE payment_amount DECIMAL(10,2);
                DECLARE already_refunded DECIMAL(10,2);
                SELECT amount INTO payment_amount FROM payments WHERE id = NEW.payment_id;
                SELECT COALESCE(SUM(amount), 0) INTO already_refunded FROM refunds WHERE payment_id = NEW.payment_id;
                IF NEW.amount > (payment_amount - already_refunded) THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Refund exceeds remaining refundable amount';
                END IF;
            END
        ");

        DB::unprepared("
            CREATE TRIGGER trg_refunds_apply_payment_status
            AFTER INSERT ON refunds
            FOR EACH ROW
            BEGIN
                DECLARE payment_amount DECIMAL(10,2);
                DECLARE total_refunded DECIMAL(10,2);
                SELECT amount INTO payment_amount FROM payments WHERE id = NEW.payment_id;
                SELECT COALESCE(SUM(amount), 0) INTO total_refunded FROM refunds WHERE payment_id = NEW.payment_id;
                IF total_refunded >= payment_amount THEN
                    UPDATE payments SET status = 'refunded' WHERE id = NEW.payment_id;
                ELSE
                    UPDATE payments SET status = 'partially_refunded' WHERE id = NEW.payment_id;
                END IF;
            END
        ");

        // Store credit can never go negative. balance_after is computed here, not by the app.
        DB::unprepared("
            CREATE TRIGGER trg_credit_transactions_overdraft_guard
            BEFORE INSERT ON credit_transactions
            FOR EACH ROW
            BEGIN
                DECLARE current_balance DECIMAL(10,2);
                SELECT store_credit_balance INTO current_balance FROM customers WHERE id = NEW.customer_id FOR UPDATE;
                IF NEW.type = 'debit' AND NEW.amount > current_balance THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Insufficient store credit balance';
                END IF;
                IF NEW.type = 'credit' THEN
                    SET NEW.balance_after = current_balance + NEW.amount;
                ELSE
                    SET NEW.balance_after = current_balance - NEW.amount;
                END IF;
            END
        ");

        DB::unprepared("
            CREATE TRIGGER trg_credit_transactions_apply_balance
            AFTER INSERT ON credit_transactions
            FOR EACH ROW
            BEGIN
                UPDATE customers SET store_credit_balance = NEW.balance_after WHERE id = NEW.customer_id;
            END
        ");
    }

    /**
     * spatie/laravel-permission's model_has_roles is a pivot with a composite
     * primary key (role_id, model_id, model_type) -- no surrogate id -- so it
     * can't go through the generic loop above (which assumes NEW.id). Role
     * removal goes through DELETE, which the app credential is allowed to do
     * per ops_grants.sql, so only the grant side is audited here.
     */
    private function createRoleAssignmentAuditTrigger(): void
    {
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
};
