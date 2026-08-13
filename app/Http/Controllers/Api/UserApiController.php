<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class UserApiController extends Controller
{
    const DEFAULT_PASSWORD_SALES = 'PoncaSaller';
    const DEFAULT_PASSWORD_ADMIN = 'PoncaAdmin';

    public function index()
    {
        $this->requireAdmin('view users');

        $users = User::all()->map(fn ($u) => $this->formatUser($u));

        return response()->json(['users' => $users]);
    }

    public function store(Request $request)
    {
        $this->requireAdmin('create user');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'role' => ['required', 'string', 'in:Admin,Sales'],
            'location' => ['nullable', 'string', 'max:255'],
        ]);

        $defaultPassword = $validated['role'] === 'Admin'
            ? self::DEFAULT_PASSWORD_ADMIN
            : self::DEFAULT_PASSWORD_SALES;

        $user = User::create([
            'name' => $validated['name'],
            'nama' => $validated['name'],
            'nik' => 'SALES-' . time() . '-' . rand(100, 999),
            'email' => $validated['email'],
            'password' => Hash::make($defaultPassword),
            'role' => $validated['role'],
            'location' => $validated['location'] ?? 'Jakarta Selatan',
            'status' => 'Active',
            'is_active' => true,
            'avatar' => strtoupper(substr($validated['name'], 0, 2)),
            'must_change_password' => true,
        ]);

        Log::info('New user created by admin', [
            'new_user' => $user->email,
            'created_by' => Auth::user()->email,
            'ip' => $request->ip(),
        ]);

        return response()->json([
            'message' => "Akun {$user->name} berhasil dibuat! Sandi default: {$defaultPassword}",
            'user' => $this->formatUser($user),
            'defaultPassword' => $defaultPassword,
        ], 201);
    }

    public function update(Request $request, string $id)
    {
        $this->requireAdmin('update user');

        $user = User::findOrFail($id);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email,'.$user->id],
            'role' => ['required', 'string', 'in:Admin,Sales'],
            'location' => ['nullable', 'string', 'max:255'],
        ]);

        $user->update([
            'name' => $validated['name'],
            'nama' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
            'location' => $validated['location'] ?? $user->location,
        ]);

        Log::info('User updated by admin', ['target' => $user->email, 'by' => Auth::user()->email]);

        return response()->json([
            'message' => "Data {$user->name} berhasil diperbarui!",
            'user' => $this->formatUser($user->fresh()),
        ]);
    }

    public function toggleStatus(Request $request, string $id)
    {
        $this->requireAdmin('toggle user status');

        $user = User::findOrFail($id);

        if ($user->id === Auth::id()) {
            return response()->json(['error' => 'Anda tidak bisa menonaktifkan akun sendiri.'], 422);
        }

        $newStatus = $user->status === 'Active' ? 'Inactive' : 'Active';
        $user->update([
            'status' => $newStatus,
            'is_active' => ($newStatus === 'Active'),
        ]);

        Log::info('User status toggled', ['target' => $user->email, 'status' => $newStatus, 'by' => Auth::user()->email]);

        return response()->json([
            'message' => "Status {$user->name} diubah menjadi {$newStatus}!",
            'user' => $this->formatUser($user->fresh()),
        ]);
    }

    public function resetPassword(Request $request, string $id)
    {
        $this->requireAdmin('reset password');

        $user = User::findOrFail($id);
        $defaultPassword = str_contains(strtolower($user->role), 'admin')
            ? self::DEFAULT_PASSWORD_ADMIN
            : self::DEFAULT_PASSWORD_SALES;

        $user->update([
            'password' => Hash::make($defaultPassword),
            'must_change_password' => true,
        ]);

        Log::warning('User password reset by admin', ['target' => $user->email, 'by' => Auth::user()->email]);

        return response()->json([
            'message' => "Password {$user->name} direset ke default ({$defaultPassword}).",
            'defaultPassword' => $defaultPassword,
        ]);
    }

    private function formatUser(User $u): array
    {
        return [
            'id' => (string) $u->id,
            'name' => $u->name ?? $u->nama,
            'email' => $u->email,
            'role' => $u->role,
            'location' => $u->location ?? 'Jakarta Selatan',
            'avatar' => $u->avatar ?? $u->foto_profil ?? strtoupper(substr($u->name ?? $u->nama, 0, 2)),
            'status' => $u->status ?? ($u->is_active ? 'Active' : 'Inactive'),
            'lastLogin' => $u->last_login_at ? $u->last_login_at->diffForHumans() : 'Never',
            'mustChangePassword' => (bool) $u->must_change_password,
        ];
    }

    private function requireAdmin(string $action): void
    {
        $user = Auth::user();
        if (! $user || ! str_contains(strtolower($user->role ?? ''), 'admin')) {
            Log::critical('SECURITY: Non-admin tried restricted action', ['action' => $action, 'user_id' => $user?->id]);
            abort(403, 'Forbidden: Administrator access required.');
        }
    }
}
