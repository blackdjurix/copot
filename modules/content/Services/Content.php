<?php

require_once dirname(__DIR__, 3) . '/app/Core/Content.php';

if (!class_exists('Content', false)) class_alias(\Copot\Core\Content::class, 'Content');
