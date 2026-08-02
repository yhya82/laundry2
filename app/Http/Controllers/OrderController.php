<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $orders = Order::with('customer')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->get('status')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->get('q').'%';
                $q->where('order_number', 'like', $term);
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('orders.index', compact('orders'));
    }

    public function create(): View
    {
        return view('orders.create');
    }

    public function show(Order $order): View
    {
        $order->load(['customer', 'packageLines.clothesLines', 'payments', 'statusHistory.order', 'damageRecords', 'receipt', 'creator']);

        return view('orders.show', compact('order'));
    }
}
