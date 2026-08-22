<?php
require_once dirname(__DIR__,3).'/app/Core/MediaUploadSource.php';
if(!class_exists('MediaUploadSource',false))class_alias(\Copot\Core\MediaUploadSource::class,'MediaUploadSource');
