<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Izin;
use App\Services\CloudinaryService;
use App\Http\Requests\CreateIzinRequest;
use Illuminate\Http\Request;
use Carbon\Carbon;

class IzinController extends Controller
{
    /**
     * POST /api/izin
     */
    public function createIzin(CreateIzinRequest $request)
    {
        $jwtUser = $request->attributes->get('user');
        $userId = $jwtUser->id;
        $targetUserId = $request->input('targetUserId');

        if ($targetUserId && ($jwtUser->role === 'ADMIN' || $jwtUser->role === 'SCANNER')) {
            $parsedTarget = (int) $targetUserId;
            if ($parsedTarget <= 0) {
                return response()->json(['error' => 'Format ID Pengguna target tidak valid.'], 400);
            }
            $userId = $parsedTarget;
        }

        $user = \App\Models\User::find($userId);
        if (!$user) {
            return response()->json(['error' => 'Pengguna target tidak ditemukan.'], 404);
        }

        $jenisIzin = $request->input('jenisIzin');
        $deskripsi = $request->input('deskripsi');
        $fotoBase64 = $request->input('fotoBase64');
        $tanggal = $request->input('tanggal');

        if (!$jenisIzin || !$deskripsi) {
            return response()->json(['error' => 'Jenis Izin dan Deskripsi wajib diisi.'], 400);
        }

        $fotoUrl = null;
        if ($fotoBase64) {
            try {
                $cloudinary = new CloudinaryService();
                $fotoUrl = $cloudinary->uploadBase64($fotoBase64, 'izin');
            } catch (\Exception $e) {
                return response()->json(['error' => 'Gagal menyimpan foto izin ke cloud'], 500);
            }
        }

        // Determine tanggal
        if ($tanggal) {
            try {
                $dateObj = Carbon::parse($tanggal);
                $tanggalValue = $dateObj->toDateString();
            } catch (\Exception $e) {
                return response()->json(['error' => 'Format tanggal tidak valid.'], 400);
            }
        } else {
            $wibTime = Carbon::now('Asia/Jakarta');
            $tanggalValue = $wibTime->toDateString();
        }

        $newIzin = Izin::create([
            'user_id' => $userId,
            'jenis_izin' => $jenisIzin,
            'deskripsi' => $deskripsi,
            'foto_url' => $fotoUrl,
            'status' => 'PENDING',
            'tanggal' => $tanggalValue,
        ]);

        return response()->json(['message' => 'Pengajuan izin berhasil dibuat.', 'data' => $newIzin]);
    }

    /**
     * GET /api/izin/me
     */
    public function getMyIzin(Request $request)
    {
        $jwtUser = $request->attributes->get('user');
        $userId = $jwtUser->id;
        $targetUserId = $request->query('userId');

        if ($targetUserId && $jwtUser->role === 'ADMIN') {
            $parsedTarget = (int) $targetUserId;
            if ($parsedTarget <= 0) {
                return response()->json(['error' => 'Format ID Pengguna target tidak valid.'], 400);
            }
            $userId = $parsedTarget;
        }

        $data = Izin::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($izin) {
                return [
                    'id' => $izin->id,
                    'userId' => $izin->user_id,
                    'jenisIzin' => $izin->jenis_izin,
                    'deskripsi' => $izin->deskripsi,
                    'fotoUrl' => $izin->foto_url,
                    'status' => $izin->status,
                    'tanggal' => $izin->tanggal->toISOString(),
                    'createdAt' => $izin->created_at->toISOString(),
                ];
            });

        return response()->json(['data' => $data]);
    }

    /**
     * GET /api/izin/all (Admin only)
     */
    public function getAllIzin(Request $request)
    {
        $locationId = $request->query('locationId') ?? $request->query('master_lokasi_id');

        $query = Izin::with(['user:id,nama,nik,master_lokasi_id']);

        if ($locationId) {
            $locId = (int) $locationId;
            $query->whereHas('user', function ($uQ) use ($locId) {
                $uQ->where('master_lokasi_id', $locId);
            });
        }

        $data = $query->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($izin) {
                return [
                    'id' => $izin->id,
                    'userId' => $izin->user_id,
                    'user' => $izin->user ? [
                        'nama' => $izin->user->nama,
                        'nik' => $izin->user->nik,
                    ] : null,
                    'jenisIzin' => $izin->jenis_izin,
                    'deskripsi' => $izin->deskripsi,
                    'fotoUrl' => $izin->foto_url,
                    'status' => $izin->status,
                    'tanggal' => $izin->tanggal->toISOString(),
                    'createdAt' => $izin->created_at->toISOString(),
                ];
            });

        return response()->json(['data' => $data]);
    }

    /**
     * PUT /api/izin/{id}/status
     */
    public function updateIzinStatus(Request $request, int $id)
    {
        $status = $request->input('status');

        if (!in_array($status, ['APPROVED', 'REJECTED'])) {
            return response()->json(['error' => 'Status tidak valid.'], 400);
        }

        $izin = Izin::findOrFail($id);
        $izin->update(['status' => $status]);

        return response()->json([
            'message' => 'Izin berhasil di-' . strtolower($status),
            'data' => $izin->fresh(),
        ]);
    }
}
