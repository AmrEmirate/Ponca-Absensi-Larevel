<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ProfileController extends Controller
{
    public function updateAvatar(Request $request)
    {
        $validated = $request->validate([
            'avatar_url' => ['required', 'url', 'max:1024'],
        ]);

        $user = Auth::user();

        $url = $validated['avatar_url'];
        if (! str_contains($url, 'cloudinary.com')) {
            return response()->json([
                'error' => 'Hanya URL Cloudinary yang diizinkan.',
            ], 422);
        }

        $user->update(['avatar' => $url, 'foto_profil' => $url]);

        Log::info('User updated own avatar via Cloudinary', [
            'user_id' => $user->id,
            'email' => $user->email,
            'avatar_url' => $url,
            'ip' => $request->ip(),
        ]);

        return response()->json([
            'message' => 'Foto profil berhasil diperbarui!',
            'avatar_url' => $url,
        ]);
    }
}
