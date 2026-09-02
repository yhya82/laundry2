<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

class MediaUrl
{
    /**
     * Every upload (clothing item photos, branding logo) is stored on the
     * 's3' disk explicitly, regardless of the app's default filesystem disk
     * -- see ClothingItemController/SettingsController. Reading it back needs
     * that same disk, but a signed request can fail (bucket not configured,
     * credentials revoked, S3 outage) -- when it does, this returns null
     * instead of throwing, so a broken thumbnail never takes down the whole
     * page it's rendered on. Callers fall back to their existing placeholder
     * (an initial-letter square, "No image", or simply not rendering a logo).
     */
    public static function temporary(?string $path, int $minutes = 10): ?string
    {
        if (! $path) {
            return null;
        }

        try {
            return Storage::disk('s3')->temporaryUrl($path, now()->addMinutes($minutes));
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }
}
