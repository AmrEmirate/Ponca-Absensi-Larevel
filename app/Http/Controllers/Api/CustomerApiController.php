<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class CustomerApiController extends Controller
{
    public function index()
    {
        $customers = Customer::orderBy('created_at', 'desc')->get()->map(fn ($c) => $this->formatCustomer($c));

        return response()->json(['customers' => $customers]);
    }

    public function store(Request $request)
    {
        $this->requireStaff('create customer');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'type' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:500'],
        ]);

        $customerNo = 'CUST-' . strtoupper(Str::random(6));

        $customer = Customer::create([
            'customer_no' => $customerNo,
            'name' => $validated['name'],
            'phone' => $validated['phone'] ?? null,
            'category' => $validated['type'] ?? 'Regular',
            'address' => $validated['address'] ?? 'Outlet Ponca Food',
            'accurate_customer_id' => null,
        ]);

        return response()->json([
            'message' => "Pelanggan \"{$customer->name}\" berhasil disimpan!",
            'customer' => $this->formatCustomer($customer),
        ], 201);
    }

    public function update(Request $request, string $customerNo)
    {
        $this->requireStaff('update customer');

        $customer = Customer::where('customer_no', $customerNo)->first();
        if (! $customer) {
            return response()->json(['error' => 'Data pelanggan tidak ditemukan.'], 404);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'type' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:500'],
        ]);

        $customer->update([
            'name' => $validated['name'],
            'phone' => $validated['phone'] ?? $customer->phone,
            'category' => $validated['type'] ?? $customer->category,
            'address' => array_key_exists('address', $validated) ? $validated['address'] : $customer->address,
        ]);

        return response()->json([
            'message' => "Data pelanggan \"{$customer->name}\" berhasil diperbarui!",
            'customer' => $this->formatCustomer($customer->fresh()),
        ]);
    }

    public function destroy(string $customerNo)
    {
        $this->requireStaff('delete customer');

        $customer = Customer::where('customer_no', $customerNo)->first();
        if (! $customer) {
            return response()->json(['error' => 'Data pelanggan tidak ditemukan.'], 404);
        }

        $name = $customer->name;
        $customer->delete();

        return response()->json([
            'message' => "Pelanggan \"{$name}\" berhasil dihapus!",
        ]);
    }

    private function formatCustomer(Customer $c): array
    {
        return [
            'id' => $c->customer_no,
            'customerNo' => $c->customer_no,
            'name' => $c->name,
            'phone' => $c->phone ?? '',
            'email' => $c->email ?? '',
            'type' => $c->category ?? 'Regular',
            'address' => $c->address ?? '',
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
            abort(403, 'Akses ditolak: Hanya Admin / Sales / Karyawan POS yang dapat mengelola pelanggan.');
        }
    }
}
