<?php

namespace Tests\Unit;

use App\Services\LyricsService;
use Tests\TestCase;

class LyricsServiceTest extends TestCase
{
    public function test_can_fetch_lyrics(): void
    {
        $service = new LyricsService();
        $lyrics = $service->getLyrics('Yellow', 'Coldplay', 269);

        $this->assertIsArray($lyrics);
        $this->assertArrayHasKey('synced_lyrics', $lyrics);
        $this->assertNotEmpty($lyrics['synced_lyrics']);
    }
}
