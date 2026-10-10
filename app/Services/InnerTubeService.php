<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class InnerTubeService
{
    private const SEARCH_URL = 'https://music.youtube.com/youtubei/v1/search?prettyPrint=false';

    public function search(string $query, int $limit = 20): array
    {
        if (trim($query) === '') {
            return [];
        }

        try {
            $response = Http::withoutVerifying()
                ->timeout(8)
                ->withHeaders([
                    'Referer' => 'https://music.youtube.com/',
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                    'Origin' => 'https://music.youtube.com',
                    'Content-Type' => 'application/json',
                ])
                ->post(self::SEARCH_URL, [
                    'context' => [
                        'client' => [
                            'clientName' => 'WEB_REMIX',
                            'clientVersion' => '1.20240101.01.00',
                            'hl' => 'id',
                            'gl' => 'ID',
                        ],
                    ],
                    'query' => $query,
                ]);

            if (! $response->successful()) {
                return [];
            }

            $json = $response->json();
            $items = [];
            $this->extractRenderers($json, $items);

            $results = [];
            foreach ($items as $item) {
                $parsed = $this->parseTrackItem($item);
                if ($parsed && ! empty($parsed['youtube_id'])) {
                    $results[] = $parsed;
                    if (count($results) >= $limit) {
                        break;
                    }
                }
            }

            return $results;
        } catch (\Throwable $e) {
            report($e);
            return [];
        }
    }

    private function extractRenderers(mixed $data, array &$results): void
    {
        if (! is_array($data)) {
            return;
        }

        if (isset($data['musicResponsiveListItemRenderer'])) {
            $results[] = $data['musicResponsiveListItemRenderer'];
        }

        foreach ($data as $value) {
            $this->extractRenderers($value, $results);
        }
    }

    private function parseTrackItem(array $renderer): ?array
    {
        $videoId = $renderer['playlistItemData']['videoId']
            ?? $renderer['overlay']['musicItemThumbnailOverlayRenderer']['content']['musicPlayButtonRenderer']['playNavigationEndpoint']['watchEndpoint']['videoId']
            ?? null;

        if (! $videoId && isset($renderer['flexColumns'][0]['musicResponsiveListItemFlexColumnRenderer']['text']['runs'][0]['navigationEndpoint']['watchEndpoint']['videoId'])) {
            $videoId = $renderer['flexColumns'][0]['musicResponsiveListItemFlexColumnRenderer']['text']['runs'][0]['navigationEndpoint']['watchEndpoint']['videoId'];
        }

        if (! $videoId) {
            return null;
        }

        // Title
        $title = $renderer['flexColumns'][0]['musicResponsiveListItemFlexColumnRenderer']['text']['runs'][0]['text'] ?? 'Unknown Title';

        // Artist, Album, Duration from secondary column runs
        $artist = 'Unknown Artist';
        $album = null;
        $durationSeconds = 0;

        $runs = $renderer['flexColumns'][1]['musicResponsiveListItemFlexColumnRenderer']['text']['runs'] ?? [];
        $parts = [];
        foreach ($runs as $run) {
            $text = trim($run['text'] ?? '');
            if ($text !== '' && $text !== '•' && $text !== '·') {
                $parts[] = $text;
            }
        }

        if (count($parts) >= 1) {
            $artist = $parts[0];
        }
        if (count($parts) >= 2) {
            // Check if last part is duration (e.g. "3:45" or "1:12:00")
            $last = end($parts);
            if (preg_match('/^\d+:\d+(:\d+)?$/', $last)) {
                $durationSeconds = $this->parseDuration($last);
                if (count($parts) >= 3) {
                    $album = $parts[1];
                }
            } else {
                $album = $parts[1];
            }
        }

        // Thumbnail
        $thumbnailUrl = null;
        $thumbnails = $renderer['thumbnail']['musicThumbnailRenderer']['thumbnail']['thumbnails'] ?? [];
        if (! empty($thumbnails)) {
            $lastThumb = end($thumbnails);
            $thumbnailUrl = $lastThumb['url'] ?? null;
            // High-res adjustment if it's a googleusercontent / ytimg URL
            if ($thumbnailUrl && str_contains($thumbnailUrl, '=w') && str_contains($thumbnailUrl, '-h')) {
                $thumbnailUrl = preg_replace('/=w\d+-h\d+/', '=w500-h500', $thumbnailUrl);
            }
        }

        return [
            'youtube_id' => $videoId,
            'title' => $title,
            'artist' => $artist,
            'album' => $album ?: 'YouTube Music',
            'duration' => $durationSeconds,
            'cover' => $thumbnailUrl ?: asset('covers/default.svg'),
        ];
    }

    private function parseDuration(string $timeStr): int
    {
        $parts = array_map('intval', explode(':', $timeStr));
        if (count($parts) === 2) {
            return ($parts[0] * 60) + $parts[1];
        } elseif (count($parts) === 3) {
            return ($parts[0] * 3600) + ($parts[1] * 60) + $parts[2];
        }
        return 0;
    }
}
