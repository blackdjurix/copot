<?php
require_once dirname(__DIR__,3).'/app/Core/MediaRepository.php';
require_once dirname(__DIR__,3).'/app/Core/MediaUploadExceptions.php';
if(!class_exists('MediaRepository',false))class_alias(\Copot\Core\MediaRepository::class,'MediaRepository');
