<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SalesOrder;
use App\Models\User;
use Illuminate\Http\Request;

class DashboardApiController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $isAdmin = str_contains(strtolower($user->role ?? ''), 'admin');

        $ordersQuery = SalesOrder::with(['customer', 'items'])->latest();
        if (! $isAdmin) {
            $ordersQuery->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                    ->orWhere('sales_agent_name', $user->name ?? $user->nama);
            });
        }

        $orders = $ordersQuery->get()->map(fn ($o) => $this->formatOrder($o));

        $salesAgents = User::where('role', 'like', '%Sales%')->orWhere('role', 'like', '%SALLER%')->get();
        $leaderboard = $salesAgents->map(function ($agent) {
            $agentName = $agent->name ?? $agent->nama;
            $totalRevenue = SalesOrder::where(function ($q) use ($agent, $agentName) {
                $q->where('user_id', $agent->id)
                    ->orWhere('sales_agent_name', $agentName);
            })->sum('total_amount');

            return [
                'name' => $agentName,
                'outlet' => $agent->location ?? 'Jakarta Selatan',
                'totalRevenue' => (float) $totalRevenue,
                'avatar' => $agent->avatar ?? $agent->foto_profil ?? strtoupper(substr($agentName, 0, 2)),
            ];
        })
            ->sortByDesc('totalRevenue')
            ->values()
            ->all();

        $todayOrders = SalesOrder::whereDate('order_date', today())->get();
        $todayRevenue = $todayOrders->sum('total_amount');
        $pendingCount = SalesOrder::where('sync_status', 'Pending')->count();
        $failedCount = SalesOrder::where('sync_status', 'Failed')->count();

        return response()->json([
            'orders' => $orders,
            'leaderboard' => $leaderboard,
            'stats' => [
                'todayRevenue' => (float) $todayRevenue,
                'todayOrders' => $todayOrders->count(),
                'pendingSync' => $pendingCount,
                'failedSync' => $failedCount,
            ],
        ]);
    }

    private function formatOrder(SalesOrder $o): array
    {
        return [
            'id' => $o->order_no,
            'customer' => [
                'id' => $o->customer ? $o->customer->customer_no : 'CUST-000',
                'name' => $o->customer ? $o->customer->name : 'Walk-in Customer',
                'address' => $o->customer?->address ?? '',
                'phone' => $o->customer?->phone ?? '',
                'type' => $o->customer?->category ?? 'Walk-in',
            ],
            'salesAgent' => $o->sales_agent_name,
            'transDate' => $o->order_date ? $o->order_date->format('d M Y') : '',
            'timestamp' => $o->order_date ? $o->order_date->format('h:i A') : '',
            'totalAmount' => (float) $o->total_amount,
            'paymentMethod' => $o->payment_method,
            'receiptUrl' => $o->receipt_url,
            'syncStatus' => $o->sync_status,
            'accurateInvoiceNo' => $o->accurate_invoice_no,
            'syncErrorMessage' => $o->sync_error_message,
            'items' => $o->items->map(fn ($item) => [
                'product' => [
                    'id' => $item->item_code,
                    'itemNo' => $item->item_code,
                    'name' => $item->product_name,
                    'unitPrice' => (float) $item->unit_price,
                    'category' => $item->product?->category ?? 'Food',
                    'stock' => $item->product?->stock ?? 0,
                    'image' => $item->product?->image_url ?? '',
                    'unit' => 'Portion',
                ],
                'quantity' => $item->quantity,
                'subtotal' => (float) $item->subtotal,
            ])->values()->all(),
        ];
    }
}
