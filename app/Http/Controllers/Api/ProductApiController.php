<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class ProductApiController extends Controller
{
    public function index()
    {
        $products = Product::all()->map(fn ($p) => [
            'id' => $p->item_code,
            'itemNo' => $p->item_code,
            'name' => $p->name,
            'category' => $p->category,
            'unitPrice' => (float) $p->unit_price,
            'stock' => $p->stock,
            'image' => $p->image_url ?? '',
            'unit' => $p->unit ?? 'Portion',
        ]);

        return response()->json(['products' => $products]);
    }

    public function updateImage(Request $request, string $itemCode)
    {
        $this->requireAdmin('update product image');

        $validated = $request->validate([
            'imageUrl' => ['required', 'string', 'url', 'max:1024'],
        ]);

        if (! str_contains($validated['imageUrl'], 'cloudinary.com')) {
            return response()->json(['error' => 'Hanya URL Cloudinary yang diizinkan.'], 422);
        }

        $product = Product::where('item_code', $itemCode)->first();

        if (! $product) {
            return response()->json(['error' => 'Produk tidak ditemukan.'], 404);
        }

        $product->update(['image_url' => $validated['imageUrl']]);

        return response()->json([
            'message' => 'Gambar produk berhasil diperbarui!',
            'imageUrl' => $validated['imageUrl'],
        ]);
    }

    public function store(Request $request)
    {
        $this->requireAdmin('create product');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:100'],
            'unitPrice' => ['required', 'numeric', 'min:0'],
            'stock' => ['required', 'integer', 'min:0'],
            'itemCode' => ['nullable', 'string', 'max:50', 'unique:products,item_code'],
            'imageUrl' => ['nullable', 'string', 'max:1024'],
        ]);

        $itemCode = ! empty($validated['itemCode'])
            ? $validated['itemCode']
            : ('PRD-'.strtoupper(Str::random(6)));

        $product = Product::create([
            'item_code' => $itemCode,
            'name' => $validated['name'],
            'category' => $validated['category'],
            'unit_price' => $validated['unitPrice'],
            'stock' => $validated['stock'],
            'image_url' => $validated['imageUrl'] ?? null,
            'accurate_item_id' => null,
        ]);

        return response()->json([
            'message' => 'Produk baru berhasil ditambahkan!',
            'product' => [
                'id' => $product->item_code,
                'itemNo' => $product->item_code,
                'name' => $product->name,
                'category' => $product->category,
                'unitPrice' => (float) $product->unit_price,
                'stock' => $product->stock,
                'image' => $product->image_url ?? '',
                'unit' => 'Portion',
            ],
        ], 201);
    }

    private function requireAdmin(string $action): void
    {
        $user = Auth::user();
        if (! $user || ! str_contains(strtolower($user->role ?? ''), 'admin')) {
            abort(403, 'Akses ditolak: Hanya Admin yang dapat melakukan aksi ini.');
        }
    }
}
