<?php

namespace App\Services;

use App\Support\VenueLayout;
use GdImage;
use RuntimeException;

/**
 * The hall as a PNG with some seats highlighted, for mails: mail clients
 * drop inline SVG. Drawn without text, as the server has no fonts; the mail
 * says what the picture shows.
 */
class SeatPlanImage
{
    public const WIDTH = 1200;

    /** Drawn larger and scaled down, which smooths the circles. */
    private const SUPERSAMPLE = 2;

    public function __construct(private readonly VenueLayout $layout) {}

    /**
     * @param  list<string>  $highlight  Seat ids.
     */
    public function png(array $highlight): string
    {
        $box = $this->layout->viewBox();
        $scale = self::WIDTH * self::SUPERSAMPLE / $box['width'];
        $width = (int) round($box['width'] * $scale);
        $height = (int) round($box['height'] * $scale);
        $point = fn (float $x, float $y): array => [(int) round(($x - $box['x']) * $scale), (int) round(($y - $box['y']) * $scale)];
        $radius = $this->layout->seatRadius() * $scale;

        $image = imagecreatetruecolor($width, $height) ?: throw new RuntimeException('GD could not create the image.');
        imagefill($image, 0, 0, $this->color($image, '#ffffff'));

        $stage = $this->layout->stage();
        [$x0, $y0] = $point($stage['x0'], $stage['y0']);
        [$x1, $y1] = $point($stage['x1'], $stage['y1']);
        imagefilledrectangle($image, $x0, $y0, $x1, $y1, $this->color($image, '#181818'));

        $pillar = $this->color($image, '#6b6b6b');
        foreach ($this->layout->pillars() as $p) {
            [$x, $y] = $point($p['x'], $p['y']);
            $half = (int) round($radius * 0.8);
            imagefilledrectangle($image, $x - $half, $y - $half, $x + $half, $y + $half, $pillar);
        }

        $other = $this->color($image, '#e8e5e3');
        $mine = $this->color($image, '#2342c7');
        $halo = $this->color($image, '#2342c7', 0.25);
        $wanted = array_flip($highlight);
        $chosen = [];

        foreach ($this->layout->sections() as $section) {
            foreach ($this->layout->seats($section['id']) as $seat) {
                [$x, $y] = $point($seat['x'], $seat['y']);

                if (isset($wanted[$seat['id']])) {
                    $chosen[] = [$x, $y];

                    continue;
                }

                $this->dot($image, $x, $y, $radius * 0.85, $other);
            }
        }

        // Last, so nothing covers them. The halo finds them at a glance.
        foreach ($chosen as [$x, $y]) {
            $this->dot($image, $x, $y, $radius * 2.6, $halo);
        }
        foreach ($chosen as [$x, $y]) {
            $this->dot($image, $x, $y, $radius * 1.3, $mine);
        }

        $scaled = imagescale($image, self::WIDTH, (int) round($height / self::SUPERSAMPLE), IMG_BILINEAR_FIXED)
            ?: throw new RuntimeException('GD could not scale the image.');
        imagedestroy($image);

        ob_start();
        imagepng($scaled, null, 9);
        imagedestroy($scaled);

        return (string) ob_get_clean();
    }

    private function dot(GdImage $image, int $x, int $y, float $radius, int $color): void
    {
        $size = (int) round($radius * 2);
        imagefilledellipse($image, $x, $y, $size, $size, $color);
    }

    private function color(GdImage $image, string $hex, float $opacity = 1.0): int
    {
        [$r, $g, $b] = sscanf($hex, '#%02x%02x%02x');
        $alpha = (int) round((1 - $opacity) * 127);

        return imagecolorallocatealpha($image, $r, $g, $b, $alpha);
    }
}
