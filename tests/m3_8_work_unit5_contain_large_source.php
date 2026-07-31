<?php

declare(strict_types=1);

$base = dirname(__DIR__);
foreach (['MediaId', 'MediaProcessingException', 'MediaProcessingFacts', 'MediaProcessingRequest', 'MediaImageProcessor', 'MediaGdImageProcessor'] as $file) {
    require_once $base . '/modules/media/Services/' . $file . '.php';
}

$source = tempnam(sys_get_temp_dir(), 'copot-wu5-contain-source-');
$output = tempnam(sys_get_temp_dir(), 'copot-wu5-contain-output-');
$image = imagecreatetruecolor(1600, 900);
imagefilledrectangle($image, 0, 0, 1599, 899, imagecolorallocate($image, 20, 60, 90));
imagepng($image, $source, 6);
imagedestroy($image);

try {
    $processor = new MediaGdImageProcessor();
    $facts = new MediaProcessingFacts('image/png', 'png', filesize($source), 1600, 900);
    $request = MediaProcessingRequest::fromArray(1, ['resize' => ['width' => 1280, 'height' => 1280], 'fit' => 'contain']);
    $result = $processor->write($source, $output, $facts, $request);
    if ($result->width() !== 1280 || $result->height() !== 720) {
        throw new RuntimeException('Contain processing did not preserve aspect ratio without upscaling.');
    }
    echo "M3.8 contain large-source regression passed." . PHP_EOL;
} finally {
    @unlink($source);
    @unlink($output);
}
