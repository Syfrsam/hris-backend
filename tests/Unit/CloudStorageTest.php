<?php

namespace Tests\Unit;

use App\Services\CloudStorageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CloudStorageTest extends TestCase
{
    public function test_can_upload_to_cloudinary_when_configured(): void
    {
        config([
            'services.cloudinary.cloud_name' => 'dr5slbx0q',
            'services.cloudinary.api_key' => '732827966187897',
            'services.cloudinary.api_secret' => 'mock_secret',
        ]);

        Http::fake([
            'https://api.cloudinary.com/*' => Http::response([
                'secure_url' => 'https://res.cloudinary.com/dr5slbx0q/image/upload/v12345/hris/tests/sample.jpg',
            ], 200),
        ]);

        $service = app(CloudStorageService::class);
        $file = UploadedFile::fake()->image('avatar.jpg');

        $url = $service->upload($file, 'hris/tests');

        $this->assertEquals('https://res.cloudinary.com/dr5slbx0q/image/upload/v12345/hris/tests/sample.jpg', $url);
    }

    public function test_falls_back_to_local_storage_when_cloudinary_not_configured(): void
    {
        config([
            'services.cloudinary.cloud_name' => null,
            'services.cloudinary.api_key' => null,
            'services.cloudinary.api_secret' => null,
        ]);

        Storage::fake('public');

        $service = app(CloudStorageService::class);
        $file = UploadedFile::fake()->image('doc.jpg');

        $path = $service->upload($file, 'contracts');

        $this->assertNotEmpty($path);
        Storage::disk('public')->assertExists($path);
    }
}
