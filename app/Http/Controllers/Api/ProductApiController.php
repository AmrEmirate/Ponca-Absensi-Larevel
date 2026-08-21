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
            'stock' => ['nullable', 'integer', 'min:0'],
            'weight' => ['nullable', 'numeric', 'min:0'],
            'unit' => ['nullable', 'string', 'max:50'],
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
            'stock' => $validated['stock'] ?? 999999,
            'weight' => $validated['weight'] ?? null,
            'unit' => $validated['unit'] ?? 'gr',
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
            'stock' => ['nullable', 'integer', 'min:0'],
            'weight' => ['nullable', 'numeric', 'min:0'],
            'unit' => ['nullable', 'string', 'max:50'],
            'imageUrl' => ['nullable', 'string', 'max:1024'],
        ]);

        $product->update([
            'name' => $validated['name'],
            'category' => $validated['category'],
            'unit_price' => $validated['unitPrice'],
            'stock' => array_key_exists('stock', $validated) ? ($validated['stock'] ?? 999999) : ($product->stock ?? 999999),
            'weight' => array_key_exists('weight', $validated) ? $validated['weight'] : $product->weight,
            'unit' => $validated['unit'] ?? $product->unit ?? 'gr',
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
            'weight' => $p->weight ? (float) $p->weight : null,
            'unit' => $p->unit ?? 'gr',
            'image' => $p->image_url ?? '',
        ];
    }

    private function requireStaff(string $action): void
    {
        $user = Auth::user();
        if (! $user) {
            abort(401, 'Silakan login terlebih dahulu.');
        }
        $role = strtolower(trim((string) ($user->role ?? '')));
        $jabatan = strtolower(trim((string) ($user->jabatan ?? '')));

        $allowed = ['admin', 'owner', 'saller', 'seller', 'sales', 'kasir', 'staff', 'karyawan'];
        $hasPermission = false;
        foreach ($allowed as $r) {
            if (str_contains($role, $r) || str_contains($jabatan, $r)) {
                $hasPermission = true;
                break;
            }
        }

        if (! $hasPermission) {
            abort(403, 'Akses ditolak: Hanya Admin / Saller / Karyawan POS yang dapat mengelola produk.');
        }
    }
}
