<?php

namespace Database\Seeders;

use App\Models\Track;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $songs = [
            ['Midnight Drive', 'SoundHelix', 'Nightfall', 372, 'midnight.svg', 1],
            ['Golden Hour', 'SoundHelix', 'Daydream', 409, 'golden.svg', 2],
            ['Blue Horizon', 'SoundHelix', 'Open Skies', 345, 'blue.svg', 3],
            ['Afterglow', 'SoundHelix', 'Soft Focus', 329, 'afterglow.svg', 4],
            ['Paper Planes', 'SoundHelix', 'Daydream', 388, 'paper.svg', 5],
            ['Slow Motion', 'SoundHelix', 'Nightfall', 366, 'midnight.svg', 6],
            ['Sunset Boulevard', 'SoundHelix', 'Soft Focus', 392, 'golden.svg', 7],
            ['The Long Way Home', 'SoundHelix', 'Open Skies', 351, 'blue.svg', 8],
        ];

        foreach ($songs as [$title, $artist, $album, $duration, $cover, $number]) {
            Track::firstOrCreate(['title' => $title, 'is_demo' => true], [
                'artist' => $artist,
                'album' => $album,
                'duration' => $duration,
                'cover_path' => 'covers/'.$cover,
                'audio_url' => 'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-'.$number.'.mp3',
            ]);
        }

    }
}
