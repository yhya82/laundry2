<?php

namespace App\Http\Controllers;

use App\Models\DamageRecord;
use App\Models\Expense;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Subscription;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
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

        $revenueTrend = $user->can('payments.view')
            ? Payment::where('status', '!=', 'refunded')
                ->where('created_at', '>=', now()->subDays(6)->startOfDay())
                ->selectRaw('DATE(created_at) as day, SUM(amount) as total')
                ->groupBy('day')
                ->orderBy('day')
                ->get()
                ->keyBy('day')
            : null;

        $revenueTrendSeries = null;

        if ($revenueTrend !== null) {
            $revenueTrendSeries = collect(range(6, 0))->map(function ($daysAgo) use ($revenueTrend) {
                $day = now()->subDays($daysAgo)->format('Y-m-d');

                return ['label' => now()->subDays($daysAgo)->format('D'), 'total' => (float) ($revenueTrend[$day]->total ?? 0)];
            });
        }

        return view('dashboard', compact(
            'queueCounts',
            'todayRevenue',
            'activeSubs',
            'pendingOrders',
            'monthExpenses',
            'damageSnapshot',
            'revenueTrendSeries',
        ));
    }
}
