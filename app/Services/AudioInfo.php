<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Duração aproximada lida no servidor (WAV e MP3 CBR/VBR com cabeçalho Xing/VBRI).
 * Para outros formatos devolve 0; o estúdio mede a duração real na primeira vez que toca.
 */
final class AudioInfo
{
    private const BR_V1 = [0, 32, 40, 48, 56, 64, 80, 96, 112, 128, 160, 192, 224, 256, 320];
    private const BR_V2 = [0, 8, 16, 24, 32, 40, 48, 56, 64, 80, 96, 112, 128, 144, 160];
    private const SR = [3 => [44100, 48000, 32000], 2 => [22050, 24000, 16000], 0 => [11025, 12000, 8000]];

    public static function durationMs(string $path, string $ext): int
    {
        try {
            return match ($ext) {
                'wav' => self::wav($path),
                'mp3' => self::mp3($path),
                default => 0,
            };
        } catch (\Throwable) {
            return 0;
        }
    }

    private static function wav(string $path): int
    {
        $fh = fopen($path, 'rb');
        $head = (string) fread($fh, 12);
        if (!str_starts_with($head, 'RIFF') || substr($head, 8, 4) !== 'WAVE') {
            fclose($fh);
            return 0;
        }
        $byteRate = 0;
        while (!feof($fh)) {
            $ch = (string) fread($fh, 8);
            if (strlen($ch) < 8) {
                break;
            }
            $id = substr($ch, 0, 4);
            $size = unpack('V', substr($ch, 4, 4))[1];
            if ($id === 'fmt ') {
                $fmt = (string) fread($fh, $size);
                $byteRate = unpack('V', substr($fmt, 8, 4))[1];
                continue;
            }
            if ($id === 'data') {
                fclose($fh);
                return $byteRate > 0 ? (int) round($size / $byteRate * 1000) : 0;
            }
            fseek($fh, $size + ($size & 1), SEEK_CUR);
        }
        fclose($fh);
        return 0;
    }

    private static function mp3(string $path): int
    {
        $size = filesize($path);
        $fh = fopen($path, 'rb');
        $start = 0;
        $id3 = (string) fread($fh, 10);
        if (str_starts_with($id3, 'ID3')) {
            $b = substr($id3, 6, 4);
            $start = 10 + ((ord($b[0]) & 0x7f) << 21 | (ord($b[1]) & 0x7f) << 14 | (ord($b[2]) & 0x7f) << 7 | (ord($b[3]) & 0x7f)) + ((ord($id3[5]) & 0x10) ? 10 : 0);
        }
        fseek($fh, $start);
        $buf = (string) fread($fh, 16384);
        fclose($fh);
        $len = strlen($buf);
        for ($i = 0; $i + 4 < $len; $i++) {
            if (ord($buf[$i]) !== 0xFF || (ord($buf[$i + 1]) & 0xE0) !== 0xE0) {
                continue;
            }
            $b1 = ord($buf[$i + 1]);
            $b2 = ord($buf[$i + 2]);
            $b3 = ord($buf[$i + 3]);
            $ver = ($b1 >> 3) & 3;      // 3 = MPEG1, 2 = MPEG2, 0 = MPEG2.5
            $layer = ($b1 >> 1) & 3;    // 1 = Layer III
            $brIdx = $b2 >> 4;
            $srIdx = ($b2 >> 2) & 3;
            if ($ver === 1 || $layer !== 1 || $brIdx === 0 || $brIdx === 15 || $srIdx === 3) {
                continue;
            }
            $bitrate = ($ver === 3 ? self::BR_V1 : self::BR_V2)[$brIdx] * 1000;
            $sr = self::SR[$ver][$srIdx];
            $spf = $ver === 3 ? 1152 : 576;
            $mono = ($b3 >> 6) === 3;
            $side = $ver === 3 ? ($mono ? 17 : 32) : ($mono ? 9 : 17);
            $x = $i + 4 + $side;
            $tag = substr($buf, $x, 4);
            if (($tag === 'Xing' || $tag === 'Info') && (unpack('N', substr($buf, $x + 4, 4))[1] & 1)) {
                $frames = unpack('N', substr($buf, $x + 8, 4))[1];
                return (int) round($frames * $spf / $sr * 1000);
            }
            if (substr($buf, $i + 36, 4) === 'VBRI') {
                $frames = unpack('N', substr($buf, $i + 36 + 14, 4))[1];
                return (int) round($frames * $spf / $sr * 1000);
            }
            return (int) round(($size - $start - $i) * 8 / $bitrate * 1000);
        }
        return 0;
    }
}
