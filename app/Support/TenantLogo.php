<?php

namespace App\Support;

use App\Models\Tenant;
use Illuminate\Support\Facades\Storage;

/**
 * A business's own logo (Settings > Company), ready to put on its invoices
 * and other documents. Given as a data URI, so it shows on screen, in print
 * and in DomPDF without the public storage link (often missing on shared
 * hosting). Large pictures are scaled down, and WebP or GIF become PNG,
 * which every PDF reader shows.
 */
class TenantLogo
{
    /** Widest the logo needs to be, in pixels (crisp in print at its size). */
    private const MAX_WIDTH = 640;

    /** The logo as a data URI, or null when there is none (or it cannot be read). */
    public static function dataUri(Tenant $tenant): ?string
    {
        $path = (string) $tenant->logo;
        if ($path === '' || str_contains($path, '..')) {
            return null;
        }

        $disk = Storage::disk('public');
        if (! $disk->exists($path)) {
            return null;
        }

        $bytes = (string) $disk->get($path);
        $mime = (string) ($disk->mimeType($path) ?: '');
        if ($bytes === '' || ! str_starts_with($mime, 'image/')) {
            return null;
        }

        // SVG is drawn as is (screen only; DomPDF cannot always draw it).
        if ($mime === 'image/svg+xml') {
            return 'data:image/svg+xml;base64,'.base64_encode($bytes);
        }

        $png = self::normalise($bytes, $mime);
        if ($png !== null) {
            return 'data:image/png;base64,'.base64_encode($png);
        }

        // No GD, or it could not read the picture: JPEG and PNG still work as they are.
        return in_array($mime, ['image/jpeg', 'image/png'], true)
            ? 'data:'.$mime.';base64,'.base64_encode($bytes)
            : null;
    }

    /** Two letters for businesses without a logo: "Our Family Farm Ltd" gives "OF". */
    public static function initials(string $name): string
    {
        $words = collect(preg_split('/\s+/u', trim($name)) ?: [])
            ->reject(fn ($w) => in_array(mb_strtolower((string) $w), ['ltd', 'ltd.', 'limited', 'plc', 'nig', 'nigeria', 'enterprises', 'the', 'and', '&'], true))
            ->values();

        $letters = $words->take(2)->map(fn ($w) => mb_substr((string) $w, 0, 1))->implode('');
        if (mb_strlen($letters) < 2 && $words->isNotEmpty()) {
            $letters = mb_substr((string) $words->first(), 0, 2);
        }

        return mb_strtoupper($letters !== '' ? $letters : 'MB');
    }

    /**
     * Re-save the picture as PNG, scaled down to MAX_WIDTH, keeping
     * transparency. Null when GD is missing or cannot read the picture.
     */
    private static function normalise(string $bytes, string $mime): ?string
    {
        if (! function_exists('imagecreatefromstring')) {
            return null;
        }
        // Small JPEG or PNG files are fine as they are.
        if (in_array($mime, ['image/png', 'image/jpeg'], true) && strlen($bytes) < 150_000) {
            $size = @getimagesizefromstring($bytes);
            if ($size && $size[0] <= self::MAX_WIDTH) {
                return $mime === 'image/png' ? $bytes : self::reencode($bytes);
            }
        }

        return self::reencode($bytes);
    }

    private static function reencode(string $bytes): ?string
    {
        $img = @imagecreatefromstring($bytes);
        if ($img === false) {
            return null;
        }
        $w = imagesx($img);
        $h = imagesy($img);
        if ($w > self::MAX_WIDTH) {
            $nh = max(1, (int) round($h * self::MAX_WIDTH / $w));
            $scaled = imagecreatetruecolor(self::MAX_WIDTH, $nh);
            imagealphablending($scaled, false);
            imagesavealpha($scaled, true);
            imagefill($scaled, 0, 0, (int) imagecolorallocatealpha($scaled, 0, 0, 0, 127));
            imagecopyresampled($scaled, $img, 0, 0, 0, 0, self::MAX_WIDTH, $nh, $w, $h);
            imagedestroy($img);
            $img = $scaled;
        } else {
            imagealphablending($img, false);
            imagesavealpha($img, true);
        }

        ob_start();
        imagepng($img, null, 6);
        $png = (string) ob_get_clean();
        imagedestroy($img);

        return $png !== '' ? $png : null;
    }
}
