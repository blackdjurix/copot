<?php

final class MediaFileInspector
{
    public const MAX_IMAGE_BYTES = 16777216;
    private const MAP = [
        'image/jpeg' => ['jpg', self::MAX_IMAGE_BYTES], 'image/png' => ['png', self::MAX_IMAGE_BYTES],
        'image/webp' => ['webp', self::MAX_IMAGE_BYTES], 'image/gif' => ['gif', self::MAX_IMAGE_BYTES],
        'image/x-icon' => ['ico', 2097152], 'image/vnd.microsoft.icon' => ['ico', 2097152],
        'application/pdf' => ['pdf', self::MAX_IMAGE_BYTES],
    ];

    public function inspect(string $path): MediaUploadInspection
    {
        if (!is_file($path) || is_link($path) || !is_readable($path)) throw new MediaUploadValidationException('The uploaded file could not be inspected.');
        $size = filesize($path);
        if ($size === false || $size <= 0) throw new MediaUploadValidationException('The uploaded file is invalid.');
        $finfo = class_exists('finfo') ? new finfo(FILEINFO_MIME_TYPE) : null;
        if (!$finfo) throw new MediaUploadValidationException('The uploaded file type could not be verified.');
        $mime = $finfo->file($path);
        if (!is_string($mime) || !isset(self::MAP[$mime]) || $size > self::MAP[$mime][1]) throw new MediaUploadValidationException('The uploaded file type or size is not allowed.');
        [$extension] = self::MAP[$mime];
        if ($mime === 'application/pdf') {
            $handle = fopen($path, 'rb');
            $head = $handle ? fread($handle, 5) : false;
            if ($handle) { fseek($handle, max(0, $size - 4096)); $tail = stream_get_contents($handle); fclose($handle); } else $tail = false;
            if ($head !== '%PDF-' || !is_string($tail) || !preg_match('/%%EOF[ \t\r\n]*\z/s', $tail)) throw new MediaUploadValidationException('The PDF structure is invalid.');
            return new MediaUploadInspection($mime, $extension, $size, null, null);
        }
        if ($mime === 'image/x-icon' || $mime === 'image/vnd.microsoft.icon') return $this->inspectIco($path, $mime, $extension, $size);
        $dimensions = @getimagesize($path);
        if (!is_array($dimensions) || (int) ($dimensions[0] ?? 0) < 1 || (int) ($dimensions[1] ?? 0) < 1 || $dimensions[0] > 10000 || $dimensions[1] > 10000 || ((int) $dimensions[0] * (int) $dimensions[1]) > 25000000 || ($dimensions['mime'] ?? '') !== $mime) throw new MediaUploadValidationException('The image structure or dimensions are invalid.');
        return new MediaUploadInspection($mime, $extension, $size, (int) $dimensions[0], (int) $dimensions[1]);
    }

    private function inspectIco(string $path, string $mime, string $extension, int $size): MediaUploadInspection
    {
        $bytes = file_get_contents($path);
        if (!is_string($bytes) || strlen($bytes) < 6) throw new MediaUploadValidationException('The icon structure is invalid.');
        $header = unpack('vreserved/vtype/vcount', substr($bytes, 0, 6));
        $count = (int) ($header['count'] ?? 0);
        if (($header['reserved'] ?? 1) !== 0 || ($header['type'] ?? 0) !== 1 || $count < 1 || $count > 64 || 6 + 16 * $count > $size) throw new MediaUploadValidationException('The icon structure is invalid.');
        $firstWidth = null; $firstHeight = null;
        for ($i = 0; $i < $count; $i++) {
            $entry = unpack('Cwidth/Cheight/Ccolors/Creserved/vplanes/vbits/Vbytes/Voffset', substr($bytes, 6 + 16 * $i, 16));
            $width = $entry['width'] ?: 256; $height = $entry['height'] ?: 256;
            if ($i === 0) { $firstWidth = $width; $firstHeight = $height; }
            $offset = (int) $entry['offset']; $length = (int) $entry['bytes'];
            if ($width < 1 || $height < 1 || $width > 512 || $height > 512 || $length < 1 || $offset < 6 + 16 * $count || $offset > $size || $length > $size - $offset) throw new MediaUploadValidationException('The icon structure is invalid.');
        }
        return new MediaUploadInspection($mime, $extension, $size, $firstWidth, $firstHeight);
    }
}
