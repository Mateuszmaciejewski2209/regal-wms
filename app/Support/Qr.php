<?php

namespace App\Support;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * Generowanie kodów QR jako SVG (bez rozszerzeń GD/Imagick).
 */
final class Qr
{
    public static function svg(string $content, int $size = 200, int $margin = 0): string
    {
        $renderer = new ImageRenderer(new RendererStyle($size, $margin), new SvgImageBackEnd);
        $svg = (new Writer($renderer))->writeString($content);

        // Usuwamy deklarację XML, by SVG można było osadzić bezpośrednio w HTML.
        return trim(preg_replace('/^<\?xml.*?\?>/', '', $svg));
    }

    public static function dataUri(string $content, int $size = 200): string
    {
        return 'data:image/svg+xml;base64,'.base64_encode(self::svg($content, $size));
    }
}
