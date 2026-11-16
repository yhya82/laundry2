<?php

namespace App\Support;

use App\Models\ActivityLog;
use App\Models\LoginEvent;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Turns raw audit rows into what the Audit Log shows: a readable sentence,
 * module, action, icon/colour, a link, and a before → after table.
 *
 * Two sources share one shape:
 *  - activity_log (data changes, written by DB triggers). New rows hold
 *    {"old": {...}, "new": {...}}; rows from before the 2026-11-16 trigger
 *    update hold a flat "after only" object -- both are read here.
 *  - login_events (security: sign-ins, failed logins, lockouts, admin
 *    password changes and sign-outs), shown to Admins only.
 */
class AuditPresenter
{
    /** Module label => [subject types, icon key, show-route name or null]. */
    public const MODULES = [
        'Orders' => [['App\Models\Order'], 'order', 'orders.show'],
        'Customers' => [['App\Models\Customer'], 'customer', 'customers.show'],
        'Subscriptions' => [['App\Models\Subscription', 'App\Models\SubscriptionCycle', 'App\Models\Collection'], 'subscription', 'subscriptions.show'],
        'Payments' => [['App\Models\Payment', 'App\Models\Refund'], 'payment', null],
        'Expenses' => [['App\Models\Expense'], 'expense', null],
        'Damage' => [['App\Models\DamageRecord', 'App\Models\DamageResolution'], 'damage', 'damage.show'],
        'Catalog' => [['App\Models\LaundryPackage', 'App\Models\SubscriptionPackage', 'App\Models\ClothingItem', 'App\Models\ClothesCategory', 'App\Models\WashingMachine'], 'catalog', null],
        'Users & roles' => [['App\Models\User', 'Spatie\Permission\Models\Role'], 'user', 'users.show'],
        'Settings' => [['App\Models\Setting'], 'setting', null],
    ];

    /** Action filter value => label (data actions first, then security). */
    public const ACTIONS = [
        'created' => 'Created',
        'updated' => 'Updated',
        'deleted' => 'Deleted',
        'access' => 'Roles & permissions',
        'signed_in' => 'Signed in',
        'signed_out' => 'Signed out',
        'failed' => 'Failed login',
        'lockout' => 'Account locked',
        'admin' => 'Admin security actions',
    ];

    public const SECURITY_ACTIONS = ['signed_in', 'signed_out', 'failed', 'lockout', 'admin'];

    protected const FIELD_LABELS = [
        'full_name' => 'Name', 'total_amount' => 'Total', 'is_active' => 'Active', 'must_change_password' => 'Temporary password',
        'password_changed' => 'Password', 'deleted_at' => 'Deleted', 'customer_type' => 'Customer type', 'store_credit_balance' => 'Store credit',
        'washing_machine_id' => 'Washing machine', 'assigned_to' => 'Assigned to', 'collected_by_type' => 'Collected by',
        'subscription_package_id' => 'Package', 'subscription_cycle_id' => 'Cycle', 'combined_into_collection_id' => 'Combined into',
        'monthly_price_snapshot' => 'Cycle price', 'max_clothes_snapshot' => 'Clothes cap', 'cycle_overage_charge' => 'Overage charge',
        'clothes_category_id' => 'Category', 'expense_category_id' => 'Category', 'base_price' => 'Price', 'monthly_price' => 'Price',
        'order_id' => 'Order', 'payment_id' => 'Payment', 'subscription_id' => 'Subscription', 'key' => 'Setting', 'value' => 'Value',
    ];

    protected const MONEY_FIELDS = ['amount', 'total_amount', 'discount', 'extra_charge', 'cycle_overage_charge', 'credit_applied',
        'store_credit_balance', 'monthly_price_snapshot', 'base_price', 'monthly_price'];

    protected const FLAG_FIELDS = ['is_active', 'must_change_password', 'clothes_allowed'];

    /**
     * @param  Collection<int, string>  $userNames  id => name, for subjects that are users
     */
    public static function activity(ActivityLog $log, Collection $userNames): array
    {
        $type = (string) $log->subject_type;
        [$module, $moduleIcon, $route] = self::moduleFor($type);
        $props = (array) ($log->properties ?? []);
        $legacy = ! array_key_exists('old', $props) && ! array_key_exists('new', $props);
        $old = $legacy ? null : (array) ($props['old'] ?? []);
        $new = $legacy ? $props : (array) ($props['new'] ?? []);

        $description = (string) $log->description;
        $verb = Str::before($description, ' ');
        $isAccess = Str::contains($description, ['role granted', 'role removed', 'permission granted', 'permission removed']);
        $action = $isAccess ? 'access' : (in_array($verb, ['created', 'updated', 'deleted'], true) ? $verb : 'updated');

        $subject = self::subjectName($type, $log->subject_id, $new ?: (array) $old, $userNames);

        $title = match (true) {
            $description === 'role granted' => 'Gave '.$subject.' the '.($props['role'] ?? 'role #'.($props['role_id'] ?? '?')).' role',
            $description === 'role removed' => 'Removed the '.($props['role'] ?? 'role #'.($props['role_id'] ?? '?')).' role from '.$subject,
            $description === 'permission granted' => 'Gave role '.($props['role'] ?? '#'.$log->subject_id).' the '.($props['permission'] ?? '?').' permission',
            $description === 'permission removed' => 'Removed the '.($props['permission'] ?? '?').' permission from role '.($props['role'] ?? '#'.$log->subject_id),
            default => ucfirst($action).' '.$subject,
        };

        $changes = $isAccess ? [] : self::changes($action, $old, $new, $legacy);

        // Status changes say so in the sentence itself.
        $status = collect($changes)->firstWhere('field', 'Status');
        if ($action === 'updated' && $status && $status['before'] !== null) {
            $title .= ': '.$status['before'].' → '.$status['after'];
        }

        $tone = ['created' => 'emerald', 'updated' => 'sky', 'deleted' => 'red', 'access' => 'violet'][$action];

        return [
            'key' => 'data-'.$log->id,
            'source' => 'data',
            'module' => $module,
            'module_icon' => $moduleIcon,
            'action' => ['created' => 'Created', 'updated' => 'Updated', 'deleted' => 'Deleted', 'access' => 'Access'][$action],
            'tone' => $tone,
            'icon' => $isAccess ? 'role' : $action,
            'title' => $title,
            'who' => $log->causer?->name ?? 'System',
            'when' => $log->created_at?->format('Y-m-d H:i:s'),
            'ago' => $log->created_at?->diffForHumans(),
            'link' => $route && $action !== 'deleted' && $log->subject_id && self::linkable($type, $route)
                ? route($route, $log->subject_id)
                : null,
            'link_label' => $route ? 'Open '.Str::singular(strtolower($module === 'Users & roles' ? 'user' : $module)) : null,
            'changes' => $changes,
            'legacy' => $legacy && ! $isAccess,
            'meta' => array_filter(['Record' => self::shortType($type).($log->subject_id ? ' #'.$log->subject_id : '')]),
        ];
    }

    public static function security(LoginEvent $event): array
    {
        $agent = UserAgent::describe($event->user_agent);
        $person = $event->user?->name ?? $event->email ?? 'Someone';
        $reason = LoginEvent::REASON_LABELS[$event->reason] ?? null;

        [$title, $tone, $icon] = match ($event->event) {
            'login' => [$person.' signed in', 'emerald', 'login'],
            'logout' => [$person.' signed out', 'slate', 'logout'],
            'failed' => ['Failed login for '.($event->email ?? 'unknown email').($reason ? ' ('.strtolower($reason).')' : ''), 'red', 'failed'],
            'lockout' => ['Account locked: '.($event->email ?? 'unknown email'), 'red', 'lock'],
            'password_reset_by_admin' => ['Changed the password of '.$person, 'violet', 'key'],
            'sessions_revoked' => [$event->user ? 'Signed '.$person.' out'.($reason ? ' ('.strtolower($reason).')' : '') : 'Signed out every other device', 'violet', 'logout'],
            default => [Str::headline($event->event), 'slate', 'shield'],
        };

        return [
            'key' => 'security-'.$event->id,
            'source' => 'security',
            'module' => 'Security',
            'module_icon' => 'shield',
            'action' => LoginEvent::EVENT_LABELS[$event->event] ?? Str::headline($event->event),
            'tone' => $tone,
            'icon' => $icon,
            'title' => $title,
            'who' => $event->actor?->name ?? $event->user?->name ?? ($event->email ? $event->email : 'Unknown'),
            'when' => $event->created_at?->format('Y-m-d H:i:s'),
            'ago' => $event->created_at?->diffForHumans(),
            'link' => route('settings.index', array_filter(['tab' => 'security', 'sec_tab' => 'activity', 'ev_event' => 'all', 'ev_ip' => $event->ip_address])),
            'link_label' => 'Open in Security Center',
            'changes' => [],
            'legacy' => false,
            'meta' => array_filter([
                'Email typed' => $event->email,
                'Account' => $event->user ? $event->user->name.' ('.$event->user->email.')' : null,
                'Reason' => $reason,
                'IP address' => $event->ip_address,
                'Device' => $agent['browser'].' on '.$agent['os'],
                'Done by' => $event->actor?->name,
            ]),
        ];
    }

    /** @return array{0: string, 1: string, 2: ?string} module, icon, route */
    public static function moduleFor(string $type): array
    {
        foreach (self::MODULES as $label => [$types, $icon, $route]) {
            if (in_array($type, $types, true)) {
                // Only the module's main type has a page to link to.
                return [$label, $icon, $type === $types[0] ? $route : null];
            }
        }

        return ['Other', 'other', null];
    }

    protected static function linkable(string $type, string $route): bool
    {
        return \Illuminate\Support\Facades\Route::has($route);
    }

    protected static function shortType(string $type): string
    {
        return Str::headline(class_basename($type));
    }

    protected static function subjectName(string $type, ?int $id, array $values, Collection $userNames): string
    {
        $ref = $id ? '#'.$id : '';

        return match (class_basename($type)) {
            'Order' => 'order '.($values['order_number'] ?? $ref),
            'Customer' => 'customer '.($values['full_name'] ?? $ref),
            'User' => 'user '.($values['name'] ?? $userNames->get($id) ?? $ref),
            'Setting' => 'setting '.($values['key'] ?? $ref),
            'Payment' => 'payment '.(isset($values['amount']) ? 'of '.self::money($values['amount']) : $ref),
            'Refund' => 'refund '.(isset($values['amount']) ? 'of '.self::money($values['amount']) : $ref),
            'Expense' => 'expense '.($values['description'] ?? $ref),
            'Subscription' => 'subscription '.$ref,
            'SubscriptionCycle' => 'subscription cycle '.$ref,
            'Collection' => 'collection '.$ref,
            'LaundryPackage' => 'laundry package '.($values['name'] ?? $ref),
            'SubscriptionPackage' => 'subscription package '.($values['name'] ?? $ref),
            'ClothingItem' => 'clothing item '.($values['name'] ?? $ref),
            'ClothesCategory' => 'category '.($values['name'] ?? $ref),
            'WashingMachine' => 'washing machine '.($values['name'] ?? $ref),
            'Role' => 'role '.($values['name'] ?? $ref),
            'DamageRecord' => 'damage report '.$ref,
            'DamageResolution' => 'damage resolution '.$ref,
            default => strtolower(self::shortType($type)).' '.$ref,
        };
    }

    /**
     * @return list<array{field: string, before: ?string, after: ?string}>
     */
    protected static function changes(string $action, ?array $old, array $new, bool $legacy): array
    {
        $fields = array_unique(array_merge(array_keys((array) $old), array_keys($new)));
        $rows = [];

        foreach ($fields as $field) {
            $before = $old[$field] ?? null;
            $after = $new[$field] ?? null;

            if ($field === 'password_changed') {
                if ($after) {
                    $rows[] = ['field' => 'Password', 'before' => null, 'after' => 'Changed'];
                }

                continue;
            }

            if ($action === 'updated' && ! $legacy && (string) $before === (string) $after) {
                continue;
            }

            if ($action === 'created' && $after === null) {
                continue;
            }

            $rows[] = [
                'field' => self::FIELD_LABELS[$field] ?? Str::headline($field),
                'before' => $legacy || $action === 'created' ? null : self::format($field, $before),
                'after' => $action === 'deleted' ? null : self::format($field, $after),
            ];
        }

        return $rows;
    }

    protected static function format(string $field, mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (in_array($field, self::FLAG_FIELDS, true)) {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'Yes' : 'No';
        }

        if (in_array($field, self::MONEY_FIELDS, true) && is_numeric($value)) {
            return self::money($value);
        }

        if (in_array($field, ['status', 'customer_type', 'collection_type', 'method', 'resolution_type', 'collected_by_type', 'priority'], true)) {
            return Str::headline((string) $value);
        }

        if (str_ends_with($field, '_id') && is_numeric($value)) {
            return '#'.$value;
        }

        return Str::limit((string) $value, 80);
    }

    protected static function money(mixed $value): string
    {
        return 'GMD '.number_format((float) $value, 2);
    }
}
