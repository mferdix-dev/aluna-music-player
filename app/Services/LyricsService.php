<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class LyricsService
{
    private const LRCLIB_URL = 'https://lrclib.net/api';

    public function getLyrics(string $title, string $artist, ?int $duration = null): ?array
    {
        try {
            // Clean title (remove (Official Video), (Lyric Video), etc.)
            $cleanTitle = preg_replace('/\s*(\(|\[)(Official.*|Music Video|Audio|Lyric.*|Live.*)(\)|\])/i', '', $title);
            $cleanTitle = trim($cleanTitle ?: $title);

            // 1. Try exact match
            $params = [
                'track_name' => $cleanTitle,
                'artist_name' => $artist,
            ];
            if ($duration && $duration > 0) {
                $params['duration'] = $duration;
            }

            $response = Http::withoutVerifying()
                ->timeout(5)
                ->withHeaders([
                    'User-Agent' => 'AlunaMusicPlayer/1.0 (https://github.com/metrolistgroup)',
                ])
                ->get(self::LRCLIB_URL . '/get', $params);

            if ($response->successful()) {
                $data = $response->json();
                return $this->formatLyrics($data);
            }

            // 2. Try search fallback
            $searchResponse = Http::withoutVerifying()
                ->timeout(5)
                ->withHeaders([
                    'User-Agent' => 'AlunaMusicPlayer/1.0 (https://github.com/metrolistgroup)',
                ])
                ->get(self::LRCLIB_URL . '/search', [
                    'q' => "$cleanTitle $artist",
                ]);

            if ($searchResponse->successful() && ! empty($searchResponse->json())) {
                $first = $searchResponse->json()[0] ?? null;
                if ($first) {
                    return $this->formatLyrics($first);
                }
            }

            return null;
        } catch (\Throwable $e) {
            report($e);
            return null;
        }
    }

    private function formatLyrics(array $data): array
    {
        return [
            'id' => $data['id'] ?? null,
            'track_name' => $data['trackName'] ?? '',
            'artist_name' => $data['artistName'] ?? '',
            'album_name' => $data['albumName'] ?? '',
            'synced_lyrics' => $data['syncedLyrics'] ?? null,
            'plain_lyrics' => $data['plainLyrics'] ?? null,
            'instrumental' => (bool) ($data['instrumental'] ?? false),
        ];
    }
}
