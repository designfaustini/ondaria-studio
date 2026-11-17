<?php
declare(strict_types=1);

namespace App\Services;

/** Leitura mínima de título e artista (ID3v2.2/2.3/2.4 e ID3v1). */
final class Id3
{
    public static function read(string $path): array
    {
        $out = ['title' => null, 'artist' => null];
        $fh = @fopen($path, 'rb');
        if (!$fh) {
            return $out;
        }
        $head = (string) fread($fh, 10);
        if (strlen($head) === 10 && str_starts_with($head, 'ID3')) {
            $ver = ord($head[3]);
            $size = self::syncsafe(substr($head, 6, 4));
            $tag = (string) fread($fh, min($size, 512 * 1024));
            $out = self::parseV2($tag, $ver, ord($head[5]));
        }
        if ($out['title'] === null) {
            $stat = fstat($fh);
            if ($stat && $stat['size'] > 128) {
                fseek($fh, -128, SEEK_END);
                $v1 = (string) fread($fh, 128);
                if (str_starts_with($v1, 'TAG')) {
                    $out['title'] = self::clean(substr($v1, 3, 30), 'ISO-8859-1');
                    $out['artist'] ??= self::clean(substr($v1, 33, 30), 'ISO-8859-1');
                }
            }
        }
        fclose($fh);
        return $out;
    }

    private static function parseV2(string $tag, int $ver, int $flags): array
    {
        $out = ['title' => null, 'artist' => null];
        $pos = 0;
        if ($flags & 0x40 && $ver >= 3) {
            $ext = $ver === 4 ? self::syncsafe(substr($tag, 0, 4)) : unpack('N', substr($tag, 0, 4))[1] + 4;
            $pos = $ext;
        }
        $idLen = $ver === 2 ? 3 : 4;
        $hdrLen = $ver === 2 ? 6 : 10;
        $map = $ver === 2 ? ['TT2' => 'title', 'TP1' => 'artist'] : ['TIT2' => 'title', 'TPE1' => 'artist'];
        $len = strlen($tag);
        while ($pos + $hdrLen < $len) {
            $id = substr($tag, $pos, $idLen);
            if (!preg_match('/^[A-Z0-9]+$/', $id)) {
                break;
            }
            $sizeBytes = substr($tag, $pos + $idLen, $ver === 2 ? 3 : 4);
            $size = match ($ver) {
                2 => unpack('N', "\0" . $sizeBytes)[1],
                4 => self::syncsafe($sizeBytes),
                default => unpack('N', $sizeBytes)[1],
            };
            if ($size <= 0 || $pos + $hdrLen + $size > $len) {
                break;
            }
            if (isset($map[$id])) {
                $data = substr($tag, $pos + $hdrLen, $size);
                $enc = ord($data[0] ?? "\0");
                $charset = [0 => 'ISO-8859-1', 1 => 'UTF-16', 2 => 'UTF-16BE', 3 => 'UTF-8'][$enc] ?? 'ISO-8859-1';
                $out[$map[$id]] = self::clean(substr($data, 1), $charset);
            }
            $pos += $hdrLen + $size;
        }
        return $out;
    }

    private static function syncsafe(string $b): int
    {
        $b = str_pad($b, 4, "\0");
        return (ord($b[0]) & 0x7f) << 21 | (ord($b[1]) & 0x7f) << 14 | (ord($b[2]) & 0x7f) << 7 | (ord($b[3]) & 0x7f);
    }

    private static function clean(string $s, string $charset): ?string
    {
        $s = @mb_convert_encoding($s, 'UTF-8', $charset) ?: '';
        $s = trim(str_replace("\0", '', $s));
        // Tag corrompida ou com codificação errada (caracteres de controle, U+FFFD, uso privado):
        // descarta, e o título passa a vir do nome do arquivo.
        if ($s === '' || preg_match('/[\x{0080}-\x{009F}\x{FFFD}\x{E000}-\x{F8FF}]|[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $s) || !mb_check_encoding($s, 'UTF-8')) {
            return null;
        }
        return mb_substr($s, 0, 250);
    }
}
