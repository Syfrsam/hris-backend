<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CloudStorageService
{
    /**
     * Upload an uploaded file or file content to Cloudinary (or fallback to local disk).
     *
     * @return string Returns full URL (if Cloudinary) or relative path (if local)
     */
    public function upload(UploadedFile|string $file, string $folder = 'general'): string
    {
        $cloudName = config('services.cloudinary.cloud_name');
        $apiKey = config('services.cloudinary.api_key');
        $apiSecret = config('services.cloudinary.api_secret');

        if ($cloudName && $apiKey && $apiSecret) {
            try {
                $timestamp = time();
                $paramsToSign = [
                    'folder' => $folder,
                    'timestamp' => (string) $timestamp,
                ];
                ksort($paramsToSign);

                $signString = '';
                foreach ($paramsToSign as $k => $v) {
                    $signString .= "{$k}={$v}&";
                }
                $signString = rtrim($signString, '&').$apiSecret;
                $signature = sha1($signString);

                $endpoint = "https://api.cloudinary.com/v1_1/{$cloudName}/auto/upload";

                $httpClient = Http::timeout(25);

                if ($file instanceof UploadedFile) {
                    $response = $httpClient->attach(
                        'file',
                        file_get_contents($file->getRealPath()),
                        $file->getClientOriginalName()
                    )->post($endpoint, [
                        'api_key' => $apiKey,
                        'timestamp' => (string) $timestamp,
                        'signature' => $signature,
                        'folder' => $folder,
                    ]);
                } else {
                    $response = $httpClient->post($endpoint, [
                        'file' => $file,
                        'api_key' => $apiKey,
                        'timestamp' => (string) $timestamp,
                        'signature' => $signature,
                        'folder' => $folder,
                    ]);
                }

                if ($response->successful() && $response->json('secure_url')) {
                    return $response->json('secure_url');
                }

                Log::warning('Cloudinary upload returned non-200, falling back to local storage', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            } catch (\Throwable $e) {
                Log::warning('Cloudinary upload exception, falling back: '.$e->getMessage());
            }
        }

        // If running in unit test environment, bypass external services and use local storage directly
        if (app()->runningUnitTests()) {
            if ($file instanceof UploadedFile) {
                return $file->store($folder, 'public');
            }
            return (string) $file;
        }

        // 2. Fallback to FreeImage.host (permanent Cloudflare CDN image hosting) for images
        try {
            $isImage = false;
            if ($file instanceof UploadedFile) {
                $mime = $file->getMimeType() ?? '';
                $isImage = str_starts_with($mime, 'image/') || in_array(strtolower($file->getClientOriginalExtension()), ['jpg', 'jpeg', 'png', 'webp', 'gif']);
            } elseif (is_string($file)) {
                $isImage = str_starts_with($file, 'data:image/') || preg_match('/\.(jpg|jpeg|png|webp|gif)(\?.*)?$/i', $file);
            }

            if ($isImage) {
                $httpClient = Http::timeout(20);
                if ($file instanceof UploadedFile) {
                    $response = $httpClient->attach(
                        'source',
                        file_get_contents($file->getRealPath()),
                        $file->getClientOriginalName()
                    )->post('https://freeimage.host/api/1/upload', [
                        'key' => '6d207e02198a847aa98d0a2a901485a5',
                        'action' => 'upload',
                    ]);
                } else {
                    $response = $httpClient->post('https://freeimage.host/api/1/upload', [
                        'key' => '6d207e02198a847aa98d0a2a901485a5',
                        'action' => 'upload',
                        'source' => $file,
                    ]);
                }

                if ($response->successful() && $response->json('image.url')) {
                    return $response->json('image.url');
                }

                Log::warning('FreeImage upload non-200, trying local disk', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('FreeImage upload exception: '.$e->getMessage());
        }

        // 3. Fallback to standard Laravel public storage (when filesystem is writable)
        if ($file instanceof UploadedFile) {
            try {
                return $file->store($folder, 'public');
            } catch (\Throwable $e) {
                Log::warning('Local storage failed (serverless read-only filesystem): '.$e->getMessage());
            }
        }

        // 4. Safe fallback for strings or placeholder
        if (is_string($file) && !empty($file)) {
            return $file;
        }

        return 'https://ui-avatars.com/api/?name=User&background=1358D0&color=fff';
    }
}
