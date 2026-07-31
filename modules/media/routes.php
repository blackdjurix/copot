<?php

require_once __DIR__ . '/Services/MediaUploadException.php';
require_once __DIR__ . '/Services/MediaId.php';
require_once __DIR__ . '/Services/Media.php';
require_once __DIR__ . '/Services/MediaVariant.php';
require_once __DIR__ . '/Services/MediaUsage.php';
require_once __DIR__ . '/Services/MediaVariantRepository.php';
require_once __DIR__ . '/Services/MediaUsageRepository.php';
require_once __DIR__ . '/Services/MediaRepository.php';
require_once __DIR__ . '/Services/MediaLifecycleService.php';
require_once __DIR__ . '/Services/MediaUploadSource.php';
require_once __DIR__ . '/Services/MediaUploadInspection.php';
require_once __DIR__ . '/Services/MediaStagedFile.php';
require_once __DIR__ . '/Services/MediaFileInspector.php';
require_once __DIR__ . '/Services/MediaFilesystemStorage.php';
require_once __DIR__ . '/Services/MediaDeliveryService.php';

$mediaDelivery = new MediaDeliveryService(new MediaRepository($app->database()), new MediaFileInspector(), new MediaFilesystemStorage($app->path('storage/media')));
$mediaId = static function (string $value): ?int { return preg_match('/^[1-9][0-9]*$/', $value) ? (int) $value : null; };
$notFound = static fn (): \Copot\Core\Response => \Copot\Core\Response::content('404 Not Found', 404, ['Content-Type' => 'text/plain; charset=UTF-8', 'Cache-Control' => 'no-store', 'X-Content-Type-Options' => 'nosniff']);
$app->router()->get('/media/{id}/download', static function ($request, array $params) use ($mediaDelivery, $mediaId, $notFound) { $id = $mediaId($params['id'] ?? ''); if ($id === null) return $notFound(); try { return $mediaDelivery->download($id); } catch (Throwable) { return $notFound(); } });
$app->router()->get('/media/{id}', static function ($request, array $params) use ($mediaDelivery, $mediaId, $notFound) { $id = $mediaId($params['id'] ?? ''); if ($id === null) return $notFound(); try { return $mediaDelivery->inline($id); } catch (Throwable) { return $notFound(); } });
