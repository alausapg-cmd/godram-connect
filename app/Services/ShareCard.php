<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

/**
 * Builds the 1200 x 630 picture that WhatsApp, Facebook and X show when a
 * GODRAM link is shared: the cover photo, a poster stripe and the title set
 * in the GODRAM display face. Cards are cached until the item changes.
 */
class ShareCard
{
    protected const W = 1200;

    protected const H = 630;

    public function render(string $key, string $eyebrow, string $title, ?string $subtitle, ?string $coverFile): string
    {
        $path = 'share-cards/'.sha1($key.'|'.$eyebrow.'|'.$title.'|'.$subtitle).'.png';
        $disk = Storage::disk('local');
        if ($disk->exists($path)) {
            return $disk->path($path);
        }

        $img = imagecreatetruecolor(self::W, self::H);
        $stage = $this->color($img, '#16120f');
        imagefilledrectangle($img, 0, 0, self::W, self::H, $stage);

        // Cover photo on the right, faded into the stage colour.
        $textWidth = self::W - 120;
        if ($coverFile && is_file($coverFile) && ($cover = @imagecreatefromstring((string) file_get_contents($coverFile)))) {
            $boxW = 560;
            $this->cover($img, $cover, self::W - $boxW, 0, $boxW, self::H);
            imagedestroy($cover);
            for ($x = 0; $x < 260; $x++) {
                $alpha = (int) round(127 * ($x / 260));
                $c = imagecolorallocatealpha($img, 0x16, 0x12, 0x0f, $alpha);
                imageline($img, self::W - $boxW + $x, 0, self::W - $boxW + $x, self::H, $c);
            }
            $textWidth = self::W - $boxW - 40;
        }

        // Poster stripe along the bottom, as on the 1990s crusade posters.
        imagefilledrectangle($img, 0, self::H - 22, self::W, self::H - 10, $this->color($img, '#b3261e'));
        imagefilledrectangle($img, 0, self::H - 10, self::W, self::H, $this->color($img, '#e5622a'));

        $display = resource_path('fonts/Oswald-Bold.ttf');
        $body = resource_path('fonts/Inter-Medium.ttf');
        $gold = $this->color($img, '#f0b03c');
        $paper = $this->color($img, '#f7f0e4');
        $soft = $this->color($img, '#c9bba6');

        if (function_exists('imagettftext')) {
            imagettftext($img, 20, 0, 64, 92, $gold, $body, mb_strtoupper($eyebrow));
            $size = mb_strlen($title) > 60 ? 46 : 58;
            $lines = array_slice($this->wrap(mb_strtoupper($title), $display, $size, $textWidth - 64), 0, 4);
            $y = 120;
            foreach ($lines as $line) {
                $y += (int) ($size * 1.25);
                imagettftext($img, $size, 0, 64, $y, $paper, $display, $line);
            }
            if ($subtitle) {
                foreach (array_slice($this->wrap($subtitle, $body, 22, $textWidth - 64), 0, 2) as $i => $line) {
                    imagettftext($img, 22, 0, 64, $y + 56 + $i * 34, $soft, $body, $line);
                }
            }
            imagettftext($img, 26, 0, 64, self::H - 56, $paper, $display, 'GODRAM');
            imagettftext($img, 15, 0, 196, self::H - 58, $gold, $body, 'C O N N E C T');
        } else {
            imagestring($img, 5, 64, 80, $title, $paper);
        }

        ob_start();
        imagepng($img, null, 6);
        $disk->put($path, (string) ob_get_clean());
        imagedestroy($img);

        return $disk->path($path);
    }

    protected function cover($dst, $src, int $x, int $y, int $w, int $h): void
    {
        $sw = imagesx($src);
        $sh = imagesy($src);
        $scale = max($w / $sw, $h / $sh);
        $cw = (int) ($w / $scale);
        $ch = (int) ($h / $scale);
        imagecopyresampled($dst, $src, $x, $y, (int) (($sw - $cw) / 2), (int) (($sh - $ch) / 4), $w, $h, $cw, $ch);
    }

    /** @return list<string> */
    protected function wrap(string $text, string $font, int $size, int $maxWidth): array
    {
        $lines = [];
        $line = '';
        foreach (preg_split('/\s+/', trim($text)) as $word) {
            $try = $line === '' ? $word : $line.' '.$word;
            $box = imagettfbbox($size, 0, $font, $try);
            if ($box[2] - $box[0] > $maxWidth && $line !== '') {
                $lines[] = $line;
                $line = $word;
            } else {
                $line = $try;
            }
        }
        if ($line !== '') {
            $lines[] = $line;
        }

        return $lines;
    }

    protected function color($img, string $hex): int
    {
        [$r, $g, $b] = sscanf($hex, '#%02x%02x%02x');

        return imagecolorallocate($img, $r, $g, $b);
    }
}
