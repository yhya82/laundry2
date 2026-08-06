<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\DamageRecord;
use App\Models\Expense;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = auth()->user();

        $queueCounts = $user->can('orders.view')
            ? Order::query()
                ->whereIn('status', array_keys(Order::STAGE_SEQUENCE))
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status')
            : collect();

        $todayRevenue = $user->can('payments.view')
            ? Payment::where('status', '!=', 'refunded')->whereDate('created_at', today())->sum('amount')
            : null;

        $monthRevenue = $user->can('payments.view')
            ? Payment::where('status', '!=', 'refunded')->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])->sum('amount')
            : null;

        $yearRevenue = $user->can('payments.view')
            ? Payment::where('status', '!=', 'refunded')->whereBetween('created_at', [now()->startOfYear(), now()->endOfYear()])->sum('amount')
            : null;

        $activeSubs = $user->can('subscriptions.view')
            ? Subscription::where('status', 'active')->count()
            : null;

        $pendingOrders = $user->can('orders.view')
            ? Order::whereIn('status', array_keys(Order::STAGE_SEQUENCE))->count()
            : null;

        $monthExpenses = $user->can('expenses.view')
            ? Expense::whereBetween('expense_date', [now()->startOfMonth(), now()->endOfMonth()])->sum('amount')
            : null;

        $damageSnapshot = $user->can('damage.view') ? [
            'pending' => DamageRecord::where('status', 'pending_review')->count(),
            'resolved30d' => DamageRecord::where('status', 'resolved')->where('updated_at', '>=', now()->subDays(30))->count(),
        ] : null;

        $totalCustomers = $user->can('customers.view') ? Customer::count() : null;

        $walkInCustomers = $user->can('customers.view') ? Customer::where('customer_type', 'walk_in')->count() : null;

        $subscriptionCustomers = $user->can('customers.view') ? Customer::where('customer_type', 'subscription')->count() : null;

        $revenueTrendPeriod = in_array($request->get('period'), ['all', 'day', 'month', 'year'], true)
            ? $request->get('period')
            : 'month';

        $revenueTrendSeries = $user->can('payments.view')
            ? $this->revenueTrendSeries($revenueTrendPeriod)
            : null;

        return view('dashboard', compact(
            'queueCounts',
            'todayRevenue',
            'monthRevenue',
            'yearRevenue',
            'activeSubs',
            'pendingOrders',
            'monthExpenses',
            'damageSnapshot',
            'totalCustomers',
            'walkInCustomers',
            'subscriptionCustomers',
            'revenueTrendSeries',
            'revenueTrendPeriod',
        ));
    }

    /**
     * Bucketed to match how much detail is actually useful at each zoom
     * level: a single day's worth of hours, a month's worth of days, or a
     * year (or all of history) worth of months. "All" doesn't pad empty
     * months since its span is open-ended -- it only plots months that
     * actually have revenue.
     */
    private function revenueTrendSeries(string $period): Collection
    {
        $paid = Payment::where('status', '!=', 'refunded');

        return match ($period) {
            'day' => (function () use ($paid) {
                $hourly = (clone $paid)->whereDate('created_at', now()->toDateString())
                    ->selectRaw('HOUR(created_at) as bucket, SUM(amount) as total')
                    ->groupBy('bucket')
                    ->pluck('total', 'bucket');

                return collect(range(0, 23))->map(fn ($hour) => [
                    'label' => sprintf('%02d:00', $hour),
                    'total' => (float) ($hourly[$hour] ?? 0),
                ]);
            })(),

            'year' => (function () use ($paid) {
                $monthly = (clone $paid)->whereBetween('created_at', [now()->startOfYear(), now()->endOfYear()])
                    ->selectRaw('MONTH(created_at) as bucket, SUM(amount) as total')
                    ->groupBy('bucket')
                    ->pluck('total', 'bucket');

                return collect(range(1, 12))->map(fn ($month) => [
                    'label' => \Carbon\Carbon::create()->month($month)->format('M'),
                    'total' => (float) ($monthly[$month] ?? 0),
                ]);
            })(),

            'all' => (clone $paid)
                ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as bucket, SUM(amount) as total")
                ->groupBy('bucket')
                ->orderBy('bucket')
                ->get()
                ->map(fn ($row) => [
                    'label' => \Carbon\Carbon::createFromFormat('Y-m', $row->bucket)->format('M Y'),
                    'total' => (float) $row->total,
                ]),

            default => (function () use ($paid) {
                $daily = (clone $paid)->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
                    ->selectRaw('DAY(created_at) as bucket, SUM(amount) as total')
                    ->groupBy('bucket')
                    ->pluck('total', 'bucket');

                return collect(range(1, now()->daysInMonth))->map(fn ($day) => [
                    'label' => (string) $day,
                    'total' => (float) ($daily[$day] ?? 0),
                ]);
            })(),
        };
    }
}
