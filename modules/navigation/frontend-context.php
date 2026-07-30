<?php

require_once __DIR__ . '/Services/NavigationFrontendContextContributor.php';

return new NavigationFrontendContextContributor($app->database());
