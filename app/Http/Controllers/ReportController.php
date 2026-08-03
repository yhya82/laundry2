<?php

namespace App\Http\Controllers;

use App\Models\DamageRecord;
use App\Models\Expense;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        [$from, $to] = $this->range($request);

        $revenue = Payment::where('status', '!=', 'refunded')
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('DATE(created_at) as day, SUM(amount) as total, COUNT(*) as count')
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        $damageByStatus = DamageRecord::whereBetween('created_at', [$from, $to])
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $expensesByCategory = Expense::with('category')
            ->whereBetween('expense_date', [$from, $to])
            ->get()
            ->groupBy('category.name')
            ->map(fn ($group) => $group->sum('amount'));

        return view('reports.index', [
            'from' => $from->format('Y-m-d'),
            'to' => $to->format('Y-m-d'),
            'revenue' => $revenue,
            'revenueTotal' => $revenue->sum('total'),
            'damageByStatus' => $damageByStatus,
            'expensesByCategory' => $expensesByCategory,
            'expensesTotal' => $expensesByCategory->sum(),
        ]);
    }

    public function exportRevenue(Request $request): StreamedResponse
    {
        [$from, $to] = $this->range($request);

        $payments = Payment::with('order.customer', 'subscription.customer')
            ->where('status', '!=', 'refunded')
            ->whereBetween('created_at', [$from, $to])
            ->orderBy('created_at')
            ->get();

        return $this->csv('revenue', ['Date', 'Customer', 'Order', 'Method', 'Amount', 'Credit Applied', 'Status'], $payments->map(fn ($p) => [
            $p->created_at->format('Y-m-d H:i'),
            $p->order?->customer?->full_name ?? $p->subscription?->customer?->full_name ?? '—',
            $p->order?->order_number ?? '—',
            $p->method,
            number_format($p->amount, 2),
            number_format($p->credit_applied, 2),
            $p->status,
        ]));
    }

    public function exportDamage(Request $request): StreamedResponse
    {
        [$from, $to] = $this->range($request);

        $records = DamageRecord::with('order.customer', 'damageType', 'resolution')
            ->whereBetween('created_at', [$from, $to])
            ->orderBy('created_at')
            ->get();

        return $this->csv('damage', ['Date', 'Order', 'Customer', 'Type', 'Item', 'Status', 'Resolution', 'Amount'], $records->map(fn ($d) => [
            $d->created_at->format('Y-m-d H:i'),
            $d->order->order_number,
            $d->order->customer->full_name,
            $d->damageType->name,
            $d->item_description,
            $d->status,
            $d->resolution?->resolution_type ?? '—',
            $d->resolution?->amount ? number_format($d->resolution->amount, 2) : '—',
        ]));
    }

    public function exportExpenses(Request $request): StreamedResponse
    {
        [$from, $to] = $this->range($request);

        $expenses = Expense::with('category', 'recorder')
            ->whereBetween('expense_date', [$from, $to])
            ->orderBy('expense_date')
            ->get();

        return $this->csv('expenses', ['Date', 'Category', 'Description', 'Amount', 'Recorded By'], $expenses->map(fn ($e) => [
            $e->expense_date->format('Y-m-d'),
            $e->category->name,
            $e->description,
            number_format($e->amount, 2),
            $e->recorder->name ?? '—',
        ]));
    }

    /**
     * @return array{0: \Carbon\Carbon, 1: \Carbon\Carbon}
     */
    private function range(Request $request): array
    {
        $from = $request->filled('from') ? \Carbon\Carbon::parse($request->get('from'))->startOfDay() : now()->subDays(30)->startOfDay();
        $to = $request->filled('to') ? \Carbon\Carbon::parse($request->get('to'))->endOfDay() : now()->endOfDay();

        return [$from, $to];
    }

    private function csv(string $name, array $header, $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($header, $rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $header);
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, $name.'-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }
}
