<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\CourierAssignment;
use App\Models\CourierLocation;
use App\Models\RouteDeviation;
use App\Models\StoreVisit;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CourierApiController extends Controller
{
    /**
     * GET /api/courier/assignment/today
     * Mendapatkan penugasan rute kurir aktif untuk hari ini.
     */
    public function getTodayAssignment(Request $request)
    {
        $user = $request->attributes->get('user') ?? $request->user();
        if (!$user) {
            return response()->json(['error' => 'Pengguna tidak terautentikasi'], 401);
        }

        $today = Carbon::today()->toDateString();

        // Cari assignment aktif (in_progress) atau yang ditugaskan hari ini
        $assignment = CourierAssignment::with([
            'route' => function ($q) {
                $q->with(['stops' => function ($sq) {
                    $sq->orderBy('sequence_order', 'asc');
                }]);
            },
            'visits',
        ])
        ->where('user_id', $user->id)
        ->where(function ($q) use ($today) {
            $q->where('assignment_date', $today)
              ->orWhere('status', 'in_progress');
        })
        ->orderByDesc('id')
        ->first();

        if (!$assignment) {
            return response()->json([
                'success' => true,
                'data' => null,
                'message' => 'Tidak ada rute yang ditugaskan hari ini',
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $assignment,
        ]);
    }

    /**
     * POST /api/courier/assignment/{id}/start
     * Kurir memulai perjalanan rute.
     */
    public function startAssignment(Request $request, $id)
    {
        $user = $request->attributes->get('user') ?? $request->user();
        $assignment = CourierAssignment::where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (!$assignment) {
            return response()->json(['error' => 'Penugasan tidak ditemukan'], 404);
        }

        $assignment->update([
            'status' => 'in_progress',
            'started_at' => $assignment->started_at ?? now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Rute berhasil dimulai',
            'data' => $assignment->fresh(['route.stops', 'visits']),
        ]);
    }

    /**
     * POST /api/courier/assignment/{id}/complete
     * Kurir menyelesaikan seluruh rute harian.
     */
    public function completeAssignment(Request $request, $id)
    {
        $user = $request->attributes->get('user') ?? $request->user();
        $assignment = CourierAssignment::where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (!$assignment) {
            return response()->json(['error' => 'Penugasan tidak ditemukan'], 404);
        }

        $assignment->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Rute berhasil diselesaikan',
            'data' => $assignment->fresh(['route.stops', 'visits']),
        ]);
    }

    /**
     * POST /api/courier/locations/batch
     * Sinkronisasi batch koordinat GPS dari mobile (Store-and-Forward Buffer).
     */
    public function batchLocations(Request $request)
    {
        $user = $request->attributes->get('user') ?? $request->user();
        $validated = $request->validate([
            'assignment_id' => 'nullable|integer',
            'locations' => 'required|array',
            'locations.*.latitude' => 'required|numeric',
            'locations.*.longitude' => 'required|numeric',
            'locations.*.speed' => 'nullable|numeric',
            'locations.*.recorded_at' => 'required|string',
        ]);

        $assignmentId = $validated['assignment_id'] ?? null;
        $now = now();
        $insertData = [];

        foreach ($validated['locations'] as $loc) {
            $recordedAt = null;
            try {
                $recordedAt = Carbon::parse($loc['recorded_at'])->toDateTimeString();
            } catch (\Exception $e) {
                $recordedAt = $now->toDateTimeString();
            }

            $insertData[] = [
                'assignment_id' => $assignmentId,
                'user_id' => $user->id,
                'latitude' => $loc['latitude'],
                'longitude' => $loc['longitude'],
                'speed' => $loc['speed'] ?? null,
                'recorded_at' => $recordedAt,
                'synced_at' => $now,
                'created_at' => $now,
            ];
        }

        if (!empty($insertData)) {
            // Bulk insert per 100 rows untuk efisiensi
            foreach (array_chunk($insertData, 100) as $chunk) {
                CourierLocation::insert($chunk);
            }
        }

        return response()->json([
            'success' => true,
            'message' => count($insertData) . ' titik lokasi berhasil disinkronisasi',
            'count' => count($insertData),
        ]);
    }

    /**
     * POST /api/courier/deviations
     * Mencatat deviasi / keluar rute (bisa single atau batch).
     */
    public function logDeviations(Request $request)
    {
        $user = $request->attributes->get('user') ?? $request->user();
        $validated = $request->validate([
            'assignment_id' => 'nullable|integer',
            'deviations' => 'required|array',
            'deviations.*.latitude' => 'required|numeric',
            'deviations.*.longitude' => 'required|numeric',
            'deviations.*.distance_deviation_meters' => 'required|numeric',
            'deviations.*.recorded_at' => 'required|string',
        ]);

        $assignmentId = $validated['assignment_id'] ?? null;
        $now = now();
        $insertData = [];

        foreach ($validated['deviations'] as $dev) {
            $recordedAt = null;
            try {
                $recordedAt = Carbon::parse($dev['recorded_at'])->toDateTimeString();
            } catch (\Exception $e) {
                $recordedAt = $now->toDateTimeString();
            }

            $insertData[] = [
                'assignment_id' => $assignmentId,
                'user_id' => $user->id,
                'latitude' => $dev['latitude'],
                'longitude' => $dev['longitude'],
                'distance_deviation_meters' => $dev['distance_deviation_meters'],
                'recorded_at' => $recordedAt,
                'is_resolved' => false,
                'created_at' => $now,
            ];
        }

        if (!empty($insertData)) {
            RouteDeviation::insert($insertData);
        }

        return response()->json([
            'success' => true,
            'message' => count($insertData) . ' log deviasi berhasil dicatat',
        ]);
    }

    /**
     * POST /api/courier/stops/{stopId}/checkin
     * Kurir melakukan check-in di toko.
     */
    public function checkInStop(Request $request, $stopId)
    {
        $user = $request->attributes->get('user') ?? $request->user();
        $validated = $request->validate([
            'assignment_id' => 'required|integer',
            'notes' => 'nullable|string',
            'proof_image_url' => 'nullable|string',
            'checkin_time' => 'nullable|string',
        ]);

        $stop = RouteStop::findOrFail($stopId);

        $checkinTime = $validated['checkin_time'] ?? null;
        try {
            $checkinTime = $checkinTime ? Carbon::parse($checkinTime) : now();
        } catch (\Exception $e) {
            $checkinTime = now();
        }

        $visit = StoreVisit::updateOrCreate(
            [
                'assignment_id' => $validated['assignment_id'],
                'route_stop_id' => $stopId,
            ],
            [
                'checkin_time' => $checkinTime,
                'notes' => $validated['notes'] ?? null,
                'proof_image_url' => $validated['proof_image_url'] ?? null,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Check-in di ' . $stop->store_name . ' berhasil dicatat',
            'data' => $visit->fresh('stop'),
        ]);
    }

    /**
     * POST /api/courier/stops/{stopId}/checkout
     * Kurir menyelesaikan kunjungan toko.
     */
    public function checkOutStop(Request $request, $stopId)
    {
        $validated = $request->validate([
            'assignment_id' => 'required|integer',
            'notes' => 'nullable|string',
        ]);

        $visit = StoreVisit::where('assignment_id', $validated['assignment_id'])
            ->where('route_stop_id', $stopId)
            ->first();

        if (!$visit) {
            return response()->json(['error' => 'Data kunjungan belum dimulai (check-in belum dilakukan)'], 404);
        }

        $visit->update([
            'checkout_time' => now(),
            'notes' => $validated['notes'] ?? $visit->notes,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Check-out berhasil dicatat',
            'data' => $visit->fresh('stop'),
        ]);
    }

    // ==========================================
    // ADMIN ENDPOINTS FOR ROUTES & ASSIGNMENT
    // ==========================================

    /**
     * GET /api/admin/routes
     */
    public function adminGetRoutes()
    {
        $routes = Route::with(['stops' => function ($q) {
            $q->orderBy('sequence_order', 'asc');
        }])->get();

        return response()->json([
            'success' => true,
            'data' => $routes,
        ]);
    }

    /**
     * POST /api/admin/routes
     */
    public function adminCreateRoute(Request $request)
    {
        $validated = $request->validate([
            'route_code' => 'required|string|max:50|unique:routes,route_code',
            'route_name' => 'required|string|max:100',
            'area_name' => 'nullable|string|max:100',
            'path_polyline' => 'nullable|string',
            'is_active' => 'nullable|boolean',
            'stops' => 'nullable|array',
            'stops.*.store_name' => 'required|string|max:150',
            'stops.*.address' => 'nullable|string',
            'stops.*.latitude' => 'required|numeric',
            'stops.*.longitude' => 'required|numeric',
            'stops.*.sequence_order' => 'required|integer',
            'stops.*.radius_tolerance_meters' => 'nullable|integer',
        ]);

        DB::beginTransaction();
        try {
            $route = Route::create([
                'route_code' => $validated['route_code'],
                'route_name' => $validated['route_name'],
                'area_name' => $validated['area_name'] ?? null,
                'path_polyline' => $validated['path_polyline'] ?? null,
                'is_active' => $validated['is_active'] ?? true,
            ]);

            if (!empty($validated['stops'])) {
                foreach ($validated['stops'] as $stopData) {
                    $route->stops()->create([
                        'store_name' => $stopData['store_name'],
                        'address' => $stopData['address'] ?? null,
                        'latitude' => $stopData['latitude'],
                        'longitude' => $stopData['longitude'],
                        'sequence_order' => $stopData['sequence_order'],
                        'radius_tolerance_meters' => $stopData['radius_tolerance_meters'] ?? 50,
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Master rute berhasil dibuat',
                'data' => $route->load('stops'),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Gagal membuat rute: ' . $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/admin/courier/assign
     */
    public function adminAssignCourier(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'route_id' => 'required|exists:routes,id',
            'assignment_date' => 'required|date',
        ]);

        $assignment = CourierAssignment::updateOrCreate(
            [
                'user_id' => $validated['user_id'],
                'assignment_date' => $validated['assignment_date'],
            ],
            [
                'route_id' => $validated['route_id'],
                'status' => 'assigned',
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Penugasan rute kurir berhasil disimpan',
            'data' => $assignment->load(['route.stops', 'user']),
        ]);
    }

    /**
     * GET /api/admin/courier/tracking/realtime
     * Mengambil lokasi real-time kurir yang sedang aktif.
     */
    public function adminRealtimeTracking()
    {
        $today = Carbon::today()->toDateString();
        $assignments = CourierAssignment::with(['user', 'route.stops', 'visits'])
            ->where(function ($q) use ($today) {
                $q->where('assignment_date', $today)
                  ->orWhere('status', 'in_progress');
            })
            ->get();

        $result = [];
        foreach ($assignments as $a) {
            $lastLocation = CourierLocation::where('assignment_id', $a->id)
                ->orderByDesc('id')
                ->first();

            $lastDeviation = RouteDeviation::where('assignment_id', $a->id)
                ->orderByDesc('id')
                ->first();

            $result[] = [
                'assignment' => $a,
                'last_location' => $lastLocation,
                'last_deviation' => $lastDeviation,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }
}
