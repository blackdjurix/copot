<?php

require_once dirname(__DIR__, 3) . '/app/Core/Slugger.php';

if (!class_exists('Slugger', false)) class_alias(\Copot\Core\Slugger::class, 'Slugger');
