<?php

namespace Tests\Unit;

use App\Services\CloudStorageService;
use Tests\TestCase;

class CloudStorageTest extends TestCase
{
    public function test_can_upload_to_cloudinary(): void
    {
        $service = app(CloudStorageService::class);
        $base64Image = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

        $url = $service->upload($base64Image, 'hris/tests');

        $this->assertNotEmpty($url);
        $this->assertStringStartsWith('https://res.cloudinary.com/dr5slbx0q/', $url);
    }
}
