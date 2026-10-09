<?php

namespace App\Services\EInvoicing;

use App\Services\SvgSanitizer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/** Turns QR text into an SVG picture when NRS gave text and not an image. */
final class QrImage
{
    public static function svg(string $text, int $size = 160): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle($size, 1), new SvgImageBackEnd));

        return SvgSanitizer::sanitize($writer->writeString($text));
    }
}
