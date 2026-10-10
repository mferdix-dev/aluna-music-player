<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CoverArtFinder
{
    public function find(string $title, string $artist): ?string
    {
        try {
            $client = Http::withOptions(['verify' => resource_path('certs/cacert.pem')]);
            $response = $client->timeout(6)->get('https://itunes.apple.com/search', [
                'term' => $artist.' '.$title,
                'entity' => 'song',
                'limit' => 10,
            ]);

            if (! $response->successful()) {
                return null;
            }

            $match = collect($response->json('results', []))->first(fn ($result) => is_array($result)
                && $this->normalize($result['trackName'] ?? '') === $this->normalize($title)
                && $this->normalize($result['artistName'] ?? '') === $this->normalize($artist)
            );

            if (! $match || ! is_string($match['artworkUrl100'] ?? null)) {
                return null;
            }

            $url = str_replace('100x100bb', '600x600bb', $match['artworkUrl100']);
            $host = parse_url($url, PHP_URL_HOST);
            if (parse_url($url, PHP_URL_SCHEME) !== 'https' || ! is_string($host) || ! preg_match('/(^|\.)mzstatic\.com$/i', $host)) {
                return null;
            }

            $image = $client->timeout(8)->withoutRedirecting()->get($url);
            if (! $image->successful() || strlen($image->body()) > 5 * 1024 * 1024) {
                return null;
            }

            $info = @getimagesizefromstring($image->body());
            $extension = match ($info[2] ?? null) {
                IMAGETYPE_JPEG => 'jpg',
                IMAGETYPE_PNG => 'png',
                IMAGETYPE_WEBP => 'webp',
                default => null,
            };
            if (! $extension || $info[0] > 3000 || $info[1] > 3000) {
                return null;
            }

            $path = 'covers/'.Str::uuid().'.'.$extension;

            return Storage::disk(config('filesystems.music_disk'))->put($path, $image->body()) ? $path : null;
        } catch (\Throwable) {
            // The online artwork service is optional; uploads should still succeed offline.
            return null;
        }
    }

    private function normalize(string $value): string
    {
        return trim(preg_replace('/[^\pL\pN]+/u', ' ', mb_strtolower($value)) ?? '');
    }
}
