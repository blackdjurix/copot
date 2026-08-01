<?php

final class MediaProcessingRequest
{
    private function __construct(private MediaId $sourceId, private ?array $crop, private ?array $aspectRatio, private int $rotation, private ?int $resizeWidth, private ?int $resizeHeight, private string $fit, private ?string $outputFormat, private ?int $quality, private array $responsiveWidths, private ?string $profile = null) {}

    public static function fromArray(MediaId|int $sourceId, array $data): self
    {
        $sourceId = $sourceId instanceof MediaId ? $sourceId : new MediaId($sourceId);
        $crop = self::rect($data['crop'] ?? null, 'crop'); $aspect = self::ratio($data['aspect_ratio'] ?? null);
        if ($crop !== null && $aspect !== null) throw new MediaProcessingValidationException('Crop modes are mutually exclusive.');
        $rotation = $data['rotation'] ?? 0; if (!is_int($rotation) || !in_array($rotation, [0, 90, 180, 270], true)) throw new MediaProcessingValidationException('Rotation is invalid.');
        $resize = $data['resize'] ?? null; $resizeWidth = null; $resizeHeight = null;
        if ($resize !== null) { if (!is_array($resize)) throw new MediaProcessingValidationException('Resize is invalid.'); $resizeWidth = self::positiveInt($resize['width'] ?? null, 'resize width'); $resizeHeight = self::positiveInt($resize['height'] ?? null, 'resize height'); if ($resizeWidth === null && $resizeHeight === null) throw new MediaProcessingValidationException('Resize requires a dimension.'); }
        $responsive = $data['responsive_widths'] ?? []; if (!is_array($responsive)) throw new MediaProcessingValidationException('Responsive widths are invalid.');
        if (count($responsive) > 6) throw new MediaProcessingValidationException('Too many responsive widths were requested.');
        $responsive = array_values(array_unique(array_map(static fn ($value): int => self::positiveInt($value, 'responsive width'), $responsive))); foreach ($responsive as $width) if (!in_array($width, [320,640,960,1280,1920,2560], true)) throw new MediaProcessingValidationException('Responsive width is not allowed.');
        if ($responsive !== [] && $resize !== null) throw new MediaProcessingValidationException('Responsive widths cannot be combined with resize.');
        $fit = $data['fit'] ?? 'contain'; if (!is_string($fit) || !in_array($fit, ['contain','cover'], true)) throw new MediaProcessingValidationException('Fit mode is invalid.');
        $format = $data['output_format'] ?? null; if ($format !== null && (!is_string($format) || !in_array($format, ['jpg','png','webp'], true))) throw new MediaProcessingValidationException('Output format is invalid.');
        $quality = $data['quality'] ?? null; if ($quality !== null && (!is_int($quality) || $quality < 60 || $quality > 95)) throw new MediaProcessingValidationException('Quality is invalid.');
        $profile = $data['profile'] ?? null;
        if ($profile !== null && $profile !== 'content.featured') throw new MediaProcessingValidationException('Processing profile is invalid.');
        return new self($sourceId, $crop, $aspect, $rotation, $resizeWidth, $resizeHeight, $fit, $format, $quality, $responsive, $profile);
    }
    public function sourceId(): MediaId { return $this->sourceId; }
    public function crop(): ?array { return $this->crop; }
    public function aspectRatio(): ?array { return $this->aspectRatio; }
    public function rotation(): int { return $this->rotation; }
    public function resizeWidth(): ?int { return $this->resizeWidth; }
    public function resizeHeight(): ?int { return $this->resizeHeight; }
    public function fit(): string { return $this->fit; }
    public function outputFormat(): ?string { return $this->outputFormat; }
    public function quality(): ?int { return $this->quality; }
    public function responsiveWidths(): array { return $this->responsiveWidths; }
    public function profile(): ?string { return $this->profile; }
    public function canonical(): string { return json_encode(['profile'=>$this->profile,'crop'=>$this->crop,'aspect'=>$this->aspectRatio,'rotation'=>$this->rotation,'resize'=>[$this->resizeWidth,$this->resizeHeight],'fit'=>$this->fit,'format'=>$this->outputFormat,'quality'=>$this->quality,'responsive'=>$this->responsiveWidths], JSON_THROW_ON_ERROR); }
    public function semanticKey(): string { return ($this->profile === 'content.featured' ? 'content-featured-' : 'r1-') . substr(hash('sha256', $this->canonical()), 0, 32); }
    public function forWidth(int $width): self { return new self($this->sourceId, $this->crop, $this->aspectRatio, $this->rotation, $width, null, $this->fit, $this->outputFormat, $this->quality, [], $this->profile); }
    private static function rect(mixed $value, string $label): ?array { if ($value === null) return null; if (!is_array($value)) throw new MediaProcessingValidationException("{$label} is invalid."); $result=[]; foreach (['x','y','width','height'] as $key) { if (!array_key_exists($key,$value) || !is_int($value[$key]) || ($key !== 'x' && $key !== 'y' && $value[$key] <= 0) || (($key === 'x' || $key === 'y') && $value[$key] < 0)) throw new MediaProcessingValidationException("{$label} is invalid."); $result[$key]=$value[$key]; } return $result; }
    private static function ratio(mixed $value): ?array { if ($value === null) return null; if (!is_array($value) || !is_int($value['width'] ?? null) || !is_int($value['height'] ?? null) || $value['width'] <= 0 || $value['height'] <= 0) throw new MediaProcessingValidationException('Aspect ratio is invalid.'); return ['width'=>$value['width'],'height'=>$value['height']]; }
    private static function positiveInt(mixed $value, string $label): ?int { if ($value === null) return null; if (!is_int($value) || $value <= 0) throw new MediaProcessingValidationException("{$label} is invalid."); return $value; }
}
