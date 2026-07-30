<?php

namespace App\Services;

use Cloudinary\Cloudinary;
use Cloudinary\Api\Upload\UploadApi;
use Illuminate\Support\Facades\Log;

class CloudinaryService
{
    protected Cloudinary $cloudinary;

    public function __construct()
    {
        $cloudinaryUrl = config('services.cloudinary.url');
        if (empty($cloudinaryUrl)) {
            throw new \Exception('URL Cloudinary belum dikonfigurasi pada environment server (CLOUDINARY_URL).');
        }
        $this->cloudinary = new Cloudinary($cloudinaryUrl);
    }

    /**
     * Upload a base64 image to Cloudinary.
     *
     * @param string $base64String The base64 string (can include data URI prefix)
     * @param string $folder The folder in Cloudinary (e.g. 'faces', 'attendance', 'izin')
     * @return string The secure URL of the uploaded image
     */
    public function uploadBase64(string $base64String, string $folder): string
    {
        try {
            $dataUri = $base64String;
            if (!str_starts_with($base64String, 'data:image')) {
                $dataUri = "data:image/jpeg;base64,{$base64String}";
            }

            $result = $this->cloudinary->uploadApi()->upload($dataUri, [
                'folder' => "poncafood/{$folder}",
            ]);

            return $result['secure_url'];
        } catch (\Exception $e) {
            Log::error('Cloudinary Upload Error: ' . $e->getMessage());
            throw $e;
        }
    }
}
