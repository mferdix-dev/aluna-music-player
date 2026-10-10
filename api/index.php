<?php

// Vercel's code filesystem is read-only. Laravel's compiled views and logs
// need a writable scratch directory; persistent uploads use cloud storage.
$storage = '/tmp/aluna-storage';
foreach (['framework/views', 'framework/cache/data', 'framework/sessions', 'logs'] as $directory) {
    if (! is_dir($storage.'/'.$directory)) {
        mkdir($storage.'/'.$directory, 0775, true);
    }
}

$_ENV['LARAVEL_STORAGE_PATH'] = $storage;
$_SERVER['LARAVEL_STORAGE_PATH'] = $storage;

require __DIR__.'/../public/index.php';
