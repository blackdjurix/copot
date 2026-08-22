<?php
require_once dirname(__DIR__,3).'/app/Core/MediaUsageRepository.php';
if(!class_exists('MediaUsageRepository',false))class_alias(\Copot\Core\MediaUsageRepository::class,'MediaUsageRepository');
