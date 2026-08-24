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

        $salesAgents = User::whereIn('role', ['ADMIN', 'SALLER', 'Sales', 'Admin', 'sales', 'admin', 'OWNER'])->get();
        $leaderboard = $salesAgents->map(function ($agent) {
            $agentName = $agent->name ?? $agent->nama;
            $agentOrders = SalesOrder::where(function ($q) use ($agent, $agentName) {
                $q->where('user_id', $agent->id)
                    ->orWhere('sales_agent_name', $agentName);
            });
            // Hanya menghitung transaksi yang SUDAH diverifikasi/disetujui Admin
            $verifiedQuery = (clone $agentOrders)->where('is_verified', true);
            $totalRevenue = $verifiedQuery->sum('total_amount');
            $totalOrders = $verifiedQuery->count();

            return [
                'id' => (string) $agent->id,
                'name' => $agentName,
                'role' => $agent->role,
                'outlet' => $agent->location ?? $agent->lokasi?->nama_lokasi ?? 'Jakarta Selatan',
                'totalRevenue' => (float) $totalRevenue,
                'totalOrders' => (int) $totalOrders,
                'verifiedOrders' => (int) $totalOrders,
                'avatar' => $agent->foto_profil ?? $agent->avatar ?? strtoupper(substr($agentName, 0, 2)),
            ];
        })
            ->sortByDesc('totalRevenue')
            ->values()
            ->all();

        // Statistik Dashboard Hari Ini (HANYA transaksi yang SUDAH diverifikasi Admin)
        $todayVerifiedOrders = SalesOrder::whereDate('order_date', today())->where('is_verified', true)->get();
        $todayRevenue = $todayVerifiedOrders->sum('total_amount');
        $pendingCount = SalesOrder::where('is_verified', false)->count();
        $failedCount = SalesOrder::where('sync_status', 'Failed')->count();

        return response()->json([
            'orders' => $orders,
            'leaderboard' => $leaderboard,
            'stats' => [
                'todayRevenue' => (float) $todayRevenue,
                'todayOrders' => $todayVerifiedOrders->count(),
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
            'isVerified' => (bool) $o->is_verified,
            'verifiedAt' => $o->verified_at ? $o->verified_at->format('d M Y H:i') : null,
            'verifiedByName' => $o->verified_by_name,
            'accurateInvoiceNo' => $o->accurate_invoice_no,
            'syncErrorMessage' => $o->sync_error_message,
            'items' => $o->items->map(fn ($item) => [
                'product' => [
                    'id' => $item->item_code,
                    'itemNo' => $item->item_code,
                    'name' => $item->product_name,
                    'unitPrice' => (float) $item->unit_price,
                    'category' => $item->product?->category ?? 'Umum',
                    'stock' => $item->product?->stock ?? 0,
                    'weight' => $item->product?->weight ? (float) $item->product->weight : null,
                    'unit' => $item->product?->unit ?? 'gr',
                    'image' => $item->product?->image_url ?? '',
                ],
                'quantity' => $item->quantity,
                'subtotal' => (float) $item->subtotal,
            ])->values()->all(),
        ];
    }
}
