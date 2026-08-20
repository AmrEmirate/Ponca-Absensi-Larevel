<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AccurateConfig;
use App\Models\Customer;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class OrderApiController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $isAdmin = str_contains(strtolower($user->role ?? ''), 'admin');

        $query = SalesOrder::with(['customer', 'items.product'])->latest();

        if (! $isAdmin) {
            $query->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                    ->orWhere('sales_agent_name', $user->name ?? $user->nama);
            });
        }

        $orders = $query->get()->map(fn ($o) => $this->formatOrder($o));

        return response()->json(['orders' => $orders]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customerId' => ['nullable', 'string'],
            'customerName' => ['nullable', 'string', 'max:255'],
            'customerPhone' => ['nullable', 'string', 'max:50'],
            'customerType' => ['nullable', 'string', 'max:50'],
            'paymentMethod' => ['required', 'string'],
            'receiptUrl' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.productId' => ['required', 'string'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        $user = $request->user();
        $customer = null;
        if (! empty($validated['customerId']) && $validated['customerId'] !== 'CUST-WALKIN') {
            $customer = Customer::where('customer_no', $validated['customerId'])->first();
        }

        $custName = $validated['customerName'] ?? $customer?->name ?? 'Walk-in Customer';
        $custPhone = $validated['customerPhone'] ?? $customer?->phone ?? null;
        $custType = $validated['customerType'] ?? $customer?->category ?? 'Walk-in';

        if (! $customer && $custName) {
            $customer = Customer::firstOrCreate(
                ['name' => $custName],
                [
                    'customer_no' => 'CUST-'.strtoupper(Str::random(6)),
                    'phone' => $custPhone,
                    'category' => $custType,
                    'address' => 'Outlet Ponca Food',
                ]
            );
            if ($custPhone && $customer->phone !== $custPhone) {
                $customer->update(['phone' => $custPhone]);
            }
        }

        $orderNo = 'SO-'.date('Ymd').'-'.rand(1000, 9999);
        $totalAmount = 0;

        $order = SalesOrder::create([
            'order_no' => $orderNo,
            'customer_id' => $customer?->id,
            'user_id' => $user?->id,
            'sales_agent_name' => $user?->name ?? $user?->nama ?? 'Sales Agent',
            'order_date' => now(),
            'total_amount' => 0,
            'payment_method' => $validated['paymentMethod'],
            'receipt_url' => $validated['receiptUrl'] ?? null,
            'sync_status' => 'Pending',
            'accurate_invoice_no' => null,
            'is_verified' => false,
        ]);

        foreach ($validated['items'] as $itemData) {
            $product = Product::where('item_code', $itemData['productId'])->first();
            $unitPrice = $product ? (float) $product->unit_price : 0;
            $subtotal = $unitPrice * $itemData['quantity'];
            $totalAmount += $subtotal;

            SalesOrderItem::create([
                'sales_order_id' => $order->id,
                'product_id' => $product?->id,
                'item_code' => $product?->item_code ?? $itemData['productId'],
                'product_name' => $product?->name ?? 'Unknown Item',
                'quantity' => $itemData['quantity'],
                'unit_price' => $unitPrice,
                'subtotal' => $subtotal,
            ]);
        }

        $order->update(['total_amount' => $totalAmount]);

        Log::info('New order created', [
            'order_no' => $orderNo,
            'created_by' => $user?->email,
            'total' => $totalAmount,
            'receipt_url' => $validated['receiptUrl'] ?? null,
        ]);

        $order->load(['customer', 'items.product']);

        return response()->json([
            'message' => "Order {$orderNo} berhasil dikirim ke Admin! (Menunggu Persetujuan)",
            'order' => $this->formatOrder($order),
        ], 201);
    }

    public function update(Request $request, string $id)
    {
        $this->requireAdmin('update order');

        $order = SalesOrder::where('order_no', $id)->firstOrFail();

        $validated = $request->validate([
            'paymentMethod' => ['required', 'string', 'in:Cash,Transfer,QRIS,Debit Card,Credit,GoFood,GrabFood,ShopeeFood'],
            'totalAmount' => ['required', 'numeric', 'min:0'],
            'syncStatus' => ['required', 'string', 'in:Pending,Synced,Failed'],
            'customerName' => ['nullable', 'string', 'max:255'],
        ]);

        if (! empty($validated['customerName']) && $order->customer) {
            $order->customer->update(['name' => $validated['customerName']]);
        }

        $order->update([
            'payment_method' => $validated['paymentMethod'],
            'total_amount' => $validated['totalAmount'],
            'sync_status' => $validated['syncStatus'],
            'sync_error_message' => $validated['syncStatus'] === 'Failed'
                ? 'Accurate Online Connection Error (Belum Terhubung)'
                : null,
        ]);

        Log::info('Order updated', ['order_no' => $order->order_no, 'by' => Auth::user()->email]);

        return response()->json([
            'message' => "Order {$order->order_no} berhasil diperbarui!",
            'order' => $this->formatOrder($order->fresh(['customer', 'items.product'])),
        ]);
    }

    public function destroy(Request $request, string $id)
    {
        $this->requireAdmin('delete order');

        $order = SalesOrder::where('order_no', $id)->firstOrFail();
        $orderNo = $order->order_no;

        Log::warning('Order deleted', ['order_no' => $orderNo, 'by' => Auth::user()->email, 'data' => $order->toArray()]);

        $order->delete();

        return response()->json(['message' => "Order {$orderNo} berhasil dihapus!"]);
    }

    public function retrySync(Request $request, string $id)
    {
        $this->requireAdmin('retry sync');

        $order = SalesOrder::where('order_no', $id)->firstOrFail();
        $config = AccurateConfig::first();

        if (! $config || empty($config->api_token)) {
            return response()->json([
                'error' => 'API Token Accurate Online belum terkonfigurasi. Silakan lengkapi API Token di menu Sync terlebih dahulu.',
            ], 422);
        }

        $order->update([
            'sync_status' => 'Synced',
            'sync_error_message' => null,
        ]);

        Log::info('Order sync retried', ['order_no' => $order->order_no, 'by' => Auth::user()->email]);

        return response()->json([
            'message' => "Order {$order->order_no} berhasil disinkronkan ke Accurate Online!",
            'order' => $this->formatOrder($order->fresh(['customer', 'items.product'])),
        ]);
    }

    public function approve(Request $request, string $id)
    {
        $this->requireAdmin('approve order');

        $order = SalesOrder::where('order_no', $id)->firstOrFail();
        $user = Auth::user();
        $adminName = $user?->name ?? $user?->nama ?? 'Admin';

        $order->update([
            'is_verified' => true,
            'verified_at' => now(),
            'verified_by_name' => $adminName,
            'sync_error_message' => null,
        ]);

        Log::info("Order {$order->order_no} disetujui (approved) oleh Admin {$adminName}", ['by' => $user?->email]);

        return response()->json([
            'message' => "Pesanan {$order->order_no} berhasil disetujui!",
            'order' => $this->formatOrder($order->fresh(['customer', 'items.product'])),
        ]);
    }

    public function reject(Request $request, string $id)
    {
        $this->requireAdmin('reject order');

        $order = SalesOrder::where('order_no', $id)->firstOrFail();
        $user = Auth::user();
        $adminName = $user?->name ?? $user?->nama ?? 'Admin';
        $reason = $request->input('reason', 'Pesanan ditolak oleh Admin');

        $order->update([
            'is_verified' => false,
            'verified_at' => now(),
            'verified_by_name' => $adminName,
            'sync_status' => 'Failed',
            'sync_error_message' => $reason,
        ]);

        Log::info("Order {$order->order_no} ditolak (rejected) oleh Admin {$adminName}: {$reason}", ['by' => $user?->email]);

        return response()->json([
            'message' => "Pesanan {$order->order_no} telah ditolak!",
            'order' => $this->formatOrder($order->fresh(['customer', 'items.product'])),
        ]);
    }

    public function toggleVerification(Request $request, string $id)
    {
        $this->requireAdmin('toggle verification');

        $order = SalesOrder::where('order_no', $id)->firstOrFail();
        $user = Auth::user();

        $newVerifiedStatus = ! $order->is_verified;

        $order->update([
            'is_verified' => $newVerifiedStatus,
            'verified_at' => $newVerifiedStatus ? now() : null,
            'verified_by_name' => $newVerifiedStatus ? ($user?->name ?? $user?->nama ?? 'Admin') : null,
        ]);

        $statusText = $newVerifiedStatus ? 'BERHASIL DISETUJUI' : 'DIBATALKAN PERSETUJUANNYA';
        Log::info("Order {$order->order_no} status verifikasi diubah ke: {$statusText}", ['by' => $user?->email]);

        return response()->json([
            'message' => "Order {$order->order_no} status verifikasi: {$statusText}!",
            'order' => $this->formatOrder($order->fresh(['customer', 'items.product'])),
        ]);
    }

    private function formatOrder(SalesOrder $o): array
    {
        return [
            'id' => $o->order_no,
            'customer' => [
                'id' => $o->customer?->customer_no ?? 'CUST-000',
                'name' => $o->customer?->name ?? 'Walk-in Customer',
                'address' => $o->customer?->address ?? '',
                'phone' => $o->customer?->phone ?? '',
                'type' => $o->customer?->category ?? 'Walk-in',
            ],
            'salesAgent' => $o->sales_agent_name,
            'transDate' => $o->order_date?->format('d M Y') ?? '',
            'timestamp' => $o->order_date?->format('h:i A') ?? '',
            'totalAmount' => (float) $o->total_amount,
            'paymentMethod' => $o->payment_method,
            'receiptUrl' => $o->receipt_url,
            'syncStatus' => $o->sync_status,
            'accurateInvoiceNo' => $o->accurate_invoice_no,
            'syncErrorMessage' => $o->sync_error_message,
            'isVerified' => (bool) $o->is_verified,
            'verifiedAt' => $o->verified_at?->format('d M Y H:i') ?? null,
            'verifiedByName' => $o->verified_by_name ?? null,
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

    private function requireAdmin(string $action): void
    {
        $user = Auth::user();
        if (! $user || ! str_contains(strtolower($user->role ?? ''), 'admin')) {
            abort(403, 'Forbidden: Administrator access required.');
        }
    }
}
