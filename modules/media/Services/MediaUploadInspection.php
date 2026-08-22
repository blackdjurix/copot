<?php
require_once dirname(__DIR__,3).'/app/Core/MediaUploadInspection.php';
require_once dirname(__DIR__,3).'/app/Core/MediaUploadExceptions.php';
if(!class_exists('MediaUploadInspection',false))class_alias(\Copot\Core\MediaUploadInspection::class,'MediaUploadInspection');
