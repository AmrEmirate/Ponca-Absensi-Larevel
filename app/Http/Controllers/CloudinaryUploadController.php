<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CloudinaryUploadController extends Controller
{
    public function upload(Request $request)
    {
        $request->validate([
            'file' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'min:5', 'max:5120'],
            'folder' => ['nullable', 'string', 'max:100'],
        ]);

        $cloudName = env('CLOUDINARY_CLOUD_NAME', 'pafffh2m');
        $apiKey = env('CLOUDINARY_API_KEY', '859181128686625');
        $apiSecret = env('CLOUDINARY_API_SECRET', 'BjPcv_3aeB33BK3nj5oF8ybouMw');

        $timestamp = time();
        $folder = $request->input('folder', 'ponca_uploads');

        $paramsToSign = [
            'folder' => $folder,
            'timestamp' => $timestamp,
        ];
        ksort($paramsToSign);

        $signatureParts = [];
        foreach ($paramsToSign as $key => $val) {
            $signatureParts[] = "{$key}={$val}";
        }
        $stringToSign = implode('&', $signatureParts) . $apiSecret;
        $signature = sha1($stringToSign);

        $response = Http::attach(
            'file',
            file_get_contents($request->file('file')->getRealPath()),
            $request->file('file')->getClientOriginalName()
        )->post("https://api.cloudinary.com/v1_1/{$cloudName}/image/upload", [
            'api_key' => $apiKey,
            'timestamp' => $timestamp,
            'signature' => $signature,
            'folder' => $folder,
        ]);

        if ($response->successful()) {
            $data = $response->json();
            Log::info('Cloudinary auto-signed upload successful', [
                'public_id' => $data['public_id'] ?? null,
                'url' => $data['secure_url'] ?? null,
            ]);

            return response()->json([
                'success' => true,
                'secure_url' => $data['secure_url'],
                'public_id' => $data['public_id'] ?? '',
            ]);
        }

        Log::error('Cloudinary signed upload failed', [
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        return response()->json([
            'error' => 'Upload ke Cloudinary gagal.',
            'details' => $response->json()['error']['message'] ?? $response->body(),
        ], 500);
    }
}
