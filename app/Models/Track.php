<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Track extends Model
{
    protected $fillable = ['title', 'artist', 'album', 'duration', 'cover_path', 'audio_path', 'audio_url', 'youtube_id', 'is_demo', 'is_public', 'user_id'];

    protected $casts = ['is_demo' => 'boolean', 'is_public' => 'boolean'];

    public function playlists()
    {
        return $this->belongsToMany(Playlist::class)->withTimestamps();
    }

    public function getCoverUrlAttribute(): string
    {
        if ($this->cover_path && (str_starts_with($this->cover_path, 'http://') || str_starts_with($this->cover_path, 'https://'))) {
            return $this->cover_path;
        }

        return $this->cover_path && ! $this->is_demo
            ? route('tracks.cover', $this)
            : asset($this->cover_path ?: 'covers/default.svg');
    }

    public function getStreamUrlAttribute(): ?string
    {
        return $this->audio_path ? route('tracks.audio', $this) : $this->audio_url;
    }
}
