<?php

require_once dirname(__DIR__, 3) . '/app/Core/ContentRepository.php';

if (!class_exists('ContentRepository', false)) class_alias(\Copot\Core\ContentRepository::class, 'ContentRepository');
if (!class_exists('ContentStaleWriteException', false)) class_alias(\Copot\Core\ContentStaleWriteException::class, 'ContentStaleWriteException');
