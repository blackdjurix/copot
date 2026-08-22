<?php

require_once dirname(__DIR__, 3) . '/app/Core/ContentService.php';

foreach ([\Copot\Core\ContentService::class => 'ContentService', \Copot\Core\ContentWriteException::class => 'ContentWriteException', \Copot\Core\ContentDuplicateSlugException::class => 'ContentDuplicateSlugException'] as $source => $alias) {
    if (!class_exists($alias, false)) class_alias($source, $alias);
}
