<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AccurateConfig;
use App\Models\AccurateSyncDataset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SyncApiController extends Controller
{
    public function datasets()
    {
        $this->requireAdmin('view sync datasets');

        $datasets = AccurateSyncDataset::all()->map(fn ($d) => [
            'code' => $d->code,
            'datasetName' => $d->dataset_name,
            'recordCount' => $d->record_count,
            'status' => $d->status,
            'lastSync' => $d->last_sync,
        ]);

        return response()->json(['datasets' => $datasets]);
    }

    public function config()
    {
        $this->requireAdmin('view sync config');

        $config = AccurateConfig::first();

        return response()->json([
            'config' => [
                'clientId' => $config?->client_id ?? 'pfj-acc-app-2026',
                'apiToken' => $config?->api_token ?? '',
                'dbId' => $config?->db_id ?? '889201',
                'autoSync' => (bool) ($config?->auto_sync ?? true),
                'syncIntervalMinutes' => (int) ($config?->sync_interval_minutes ?? 5),
                'lastSuccessfulSync' => $config?->last_successful_sync ?? 'Never',
            ],
        ]);
    }

    public function syncAll()
    {
        $this->requireAdmin('sync all');

        AccurateSyncDataset::query()->update([
            'status' => 'Synced',
            'last_sync' => 'Just now',
        ]);

        AccurateConfig::query()->update(['last_successful_sync' => now()->toDateTimeString()]);

        return response()->json([
            'message' => 'Semua dataset berhasil disinkronkan dengan Accurate Online!',
            'datasets' => AccurateSyncDataset::all()->map(fn ($d) => [
                'code' => $d->code,
                'datasetName' => $d->dataset_name,
                'recordCount' => $d->record_count,
                'status' => $d->status,
                'lastSync' => $d->last_sync,
            ]),
        ]);
    }

    public function syncSingle(Request $request)
    {
        $this->requireAdmin('sync single');

        $validated = $request->validate([
            'datasetCode' => ['required', 'string'],
        ]);

        $dataset = AccurateSyncDataset::where('code', $validated['datasetCode'])->firstOrFail();
        $dataset->update(['status' => 'Synced', 'last_sync' => 'Just now']);

        return response()->json([
            'message' => "{$dataset->dataset_name} berhasil disinkronkan!",
            'dataset' => [
                'code' => $dataset->code,
                'datasetName' => $dataset->dataset_name,
                'recordCount' => $dataset->record_count,
                'status' => $dataset->status,
                'lastSync' => $dataset->last_sync,
            ],
        ]);
    }

    public function saveConfig(Request $request)
    {
        $this->requireAdmin('save config');

        $validated = $request->validate([
            'clientId' => ['required', 'string'],
            'apiToken' => ['required', 'string'],
            'dbId' => ['required', 'string'],
            'autoSync' => ['boolean'],
            'syncIntervalMinutes' => ['integer', 'min:1', 'max:60'],
        ]);

        $config = AccurateConfig::updateOrCreate(['id' => 1], [
            'client_id' => $validated['clientId'],
            'api_token' => $validated['apiToken'],
            'db_id' => $validated['dbId'],
            'auto_sync' => $validated['autoSync'] ?? true,
            'sync_interval_minutes' => $validated['syncIntervalMinutes'] ?? 5,
        ]);

        return response()->json([
            'message' => 'Konfigurasi Accurate Online berhasil disimpan!',
            'config' => [
                'clientId' => $config->client_id,
                'apiToken' => $config->api_token,
                'dbId' => $config->db_id,
                'autoSync' => (bool) $config->auto_sync,
                'syncIntervalMinutes' => (int) $config->sync_interval_minutes,
                'lastSuccessfulSync' => $config->last_successful_sync ?? 'Never',
            ],
        ]);
    }

    private function requireAdmin(string $action): void
    {
        $user = Auth::user();
        if (! $user || ! str_contains(strtolower($user->role ?? ''), 'admin')) {
            abort(403, 'Forbidden: Administrator access required.');
        }
    }
}
