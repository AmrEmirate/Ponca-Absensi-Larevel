<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FacePlusPlusService
{
    protected string $apiKey;
    protected string $apiSecret;
    protected string $apiUrl;

    public function __construct()
    {
        $this->apiKey = config('services.facepp.api_key', '');
        $this->apiSecret = config('services.facepp.api_secret', '');
        $this->apiUrl = config('services.facepp.api_url', 'https://api-us.faceplusplus.com');

        if (empty($this->apiKey) || empty($this->apiSecret)) {
            throw new \Exception('API Key atau Secret Face++ belum dikonfigurasi pada server (FACEPP_API_KEY / FACEPP_API_SECRET).');
        }
    }

    /**
     * Detect face in a base64 image using Face++ Detect API.
     * Returns true if at least one face is detected.
     */
    public function detectFace(string $base64Image): bool
    {
        // Strip data URI prefix if present
        $cleanBase64 = $this->stripDataUri($base64Image);

        try {
            $response = Http::timeout(30)
                ->asMultipart()
                ->post("{$this->apiUrl}/facepp/v3/detect", [
                    ['name' => 'api_key', 'contents' => $this->apiKey],
                    ['name' => 'api_secret', 'contents' => $this->apiSecret],
                    ['name' => 'image_base64', 'contents' => $cleanBase64],
                ]);

            if ($response->successful()) {
                $data = $response->json();
                return isset($data['faces']) && count($data['faces']) > 0;
            }

            Log::error('Face++ Detect API error: ' . $response->body());
            return false;
        } catch (\Exception $e) {
            Log::error('Face++ Detect API exception: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Compare two faces using Face++ Compare API.
     * 
     * @param string $referenceImageUrl URL of the reference face image (from Cloudinary)
     * @param string $liveImageBase64 Base64 of the live captured image
     * @return array ['isMatch' => bool, 'confidence' => float]
     */
    public function compareFaces(string $referenceImageUrl, string $liveImageBase64): array
    {
        $cleanBase64 = $this->stripDataUri($liveImageBase64);
        $threshold = 72; // Confidence threshold (0-100 scale)

        try {
            $response = Http::timeout(30)
                ->asMultipart()
                ->post("{$this->apiUrl}/facepp/v3/compare", [
                    ['name' => 'api_key', 'contents' => $this->apiKey],
                    ['name' => 'api_secret', 'contents' => $this->apiSecret],
                    ['name' => 'image_url1', 'contents' => $referenceImageUrl],
                    ['name' => 'image_base64_2', 'contents' => $cleanBase64],
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $confidence = $data['confidence'] ?? 0;

                return [
                    'isMatch' => $confidence >= $threshold,
                    'confidence' => $confidence,
                ];
            }

            // Handle specific Face++ error codes
            $errorData = $response->json();
            $errorMsg = $errorData['error_message'] ?? 'Unknown error';

            if (str_contains($errorMsg, 'NO_FACE_FOUND')) {
                throw new \Exception('Wajah tidak terdeteksi pada foto.');
            }

            Log::error('Face++ Compare API error: ' . $response->body());
            throw new \Exception('Gagal membandingkan wajah: ' . $errorMsg);
        } catch (\Exception $e) {
            Log::error('Face++ Compare API exception: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Strip data URI prefix from base64 string
     */
    private function stripDataUri(string $base64): string
    {
        if (str_starts_with($base64, 'data:image')) {
            return explode(',', $base64, 2)[1] ?? $base64;
        }
        return $base64;
    }
}
