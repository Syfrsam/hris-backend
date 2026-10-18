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
                Log::error('Cloudinary upload exception, falling back to local storage: '.$e->getMessage());
            }
        }

        // Fallback to standard Laravel public storage
        if ($file instanceof UploadedFile) {
            return $file->store($folder, 'public');
        }

        return $file;
    }
}
