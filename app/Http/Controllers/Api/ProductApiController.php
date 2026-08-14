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
        $products = Product::orderBy('created_at', 'desc')->get()->map(fn ($p) => $this->formatProduct($p));

        return response()->json(['products' => $products]);
    }

    public function store(Request $request)
    {
        $this->requireStaff('create product');

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
            'message' => "Produk \"{$product->name}\" berhasil ditambahkan!",
            'product' => $this->formatProduct($product),
        ], 201);
    }

    public function update(Request $request, string $itemCode)
    {
        $this->requireStaff('update product');

        $product = Product::where('item_code', $itemCode)->first();
        if (! $product) {
            return response()->json(['error' => 'Produk tidak ditemukan.'], 404);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:100'],
            'unitPrice' => ['required', 'numeric', 'min:0'],
            'stock' => ['required', 'integer', 'min:0'],
            'imageUrl' => ['nullable', 'string', 'max:1024'],
        ]);

        $product->update([
            'name' => $validated['name'],
            'category' => $validated['category'],
            'unit_price' => $validated['unitPrice'],
            'stock' => $validated['stock'],
            'image_url' => $validated['imageUrl'] ?? $product->image_url,
        ]);

        return response()->json([
            'message' => "Produk \"{$product->name}\" berhasil diperbarui!",
            'product' => $this->formatProduct($product->fresh()),
        ]);
    }

    public function destroy(string $itemCode)
    {
        $this->requireStaff('delete product');

        $product = Product::where('item_code', $itemCode)->first();
        if (! $product) {
            return response()->json(['error' => 'Produk tidak ditemukan.'], 404);
        }

        $name = $product->name;
        $product->delete();

        return response()->json([
            'message' => "Produk \"{$name}\" berhasil dihapus!",
        ]);
    }

    public function updateImage(Request $request, string $itemCode)
    {
        $this->requireStaff('update product image');

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

    private function formatProduct(Product $p): array
    {
        return [
            'id' => $p->item_code,
            'itemNo' => $p->item_code,
            'name' => $p->name,
            'category' => $p->category,
            'unitPrice' => (float) $p->unit_price,
            'stock' => (int) $p->stock,
            'image' => $p->image_url ?? '',
            'unit' => $p->unit ?? 'Porsi',
        ];
    }

    private function requireStaff(string $action): void
    {
        $user = Auth::user();
        if (! $user) {
            abort(401, 'Silakan login terlebih dahulu.');
        }
        $role = strtolower($user->role ?? '');
        $jabatan = strtolower($user->jabatan ?? '');
        if (! str_contains($role, 'admin') && ! str_contains($role, 'saller') && ! str_contains($jabatan, 'admin') && ! str_contains($jabatan, 'sales')) {
            abort(403, 'Akses ditolak: Hanya Admin / Saller yang dapat mengelola produk.');
        }
    }
}
