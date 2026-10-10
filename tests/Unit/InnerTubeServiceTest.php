<?php

namespace Tests\Unit;

use App\Services\InnerTubeService;
use Tests\TestCase;

class InnerTubeServiceTest extends TestCase
{
    public function test_can_search_tracks(): void
    {
        $service = new InnerTubeService();
        $results = $service->search('Coldplay Yellow', 3);

        $this->assertIsArray($results);
        if (!empty($results)) {
            $this->assertArrayHasKey('youtube_id', $results[0]);
            $this->assertArrayHasKey('title', $results[0]);
            $this->assertArrayHasKey('artist', $results[0]);
            $this->assertNotEmpty($results[0]['youtube_id']);
        }
    }
}
