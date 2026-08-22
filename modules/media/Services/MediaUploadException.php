<?php
require_once dirname(__DIR__,3).'/app/Core/MediaUploadExceptions.php';
foreach([\Copot\Core\MediaUploadException::class=>'MediaUploadException',\Copot\Core\MediaUploadValidationException::class=>'MediaUploadValidationException',\Copot\Core\MediaStorageException::class=>'MediaStorageException',\Copot\Core\MediaDeliveryException::class=>'MediaDeliveryException'] as $s=>$a)if(!class_exists($a,false))class_alias($s,$a);
