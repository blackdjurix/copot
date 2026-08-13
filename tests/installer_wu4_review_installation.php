<?php

declare(strict_types=1);

$base = dirname(__DIR__);
$committer = file_get_contents($base . '/app/Core/InstallerInstallationCommitter.php');
$administrator = file_get_contents($base . '/app/Core/InstallerAdministratorSetup.php');
$finalizer = file_get_contents($base . '/app/Core/InstallerFinalizer.php');
$bootstrap = file_get_contents($base . '/bootstrap/installer.php');
$view = file_get_contents($base . '/resources/views/installer/index.php');
$css = file_get_contents($base . '/public/installer-assets/css/installer.css');

foreach ([$committer, $administrator, $finalizer, $bootstrap, $view, $css] as $source) {
    if (!is_string($source)) {
        throw new RuntimeException('WU4 sources could not be read.');
    }
}

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$assert(str_contains($bootstrap, "'label' => 'Installation Result'"), 'Installation Result is not an explicit installer phase.');
$assert(str_contains($bootstrap, "'result' => 'Installation Result'"), 'Result phase is not part of current-step derivation.');
$assert(str_contains($bootstrap, 'new InstallerInstallationCommitter('), 'Review & Install does not construct the commit coordinator.');
$assert(str_contains($bootstrap, 'use Copot\\Core\\InstallerSchemaRunner;'), 'Review & Install does not import the schema runner used by the commit coordinator.');
$assert(str_contains($bootstrap, "if (\$action === 'finalize_installation')"), 'Installation mutation is not bounded to the Review & Install action.');
$assert(str_contains($bootstrap, '$installationResult = [\'status\' => \'success\''), 'Successful installation does not produce an explicit result state.');
$assert(str_contains($bootstrap, '$installationResult = [\'status\' => \'failure\''), 'Failed installation does not produce an explicit sanitized result state.');
$assert(str_contains($bootstrap, 'Installation could not be completed safely.'), 'Failure result does not use sanitized user-visible messaging.');
$assert(str_contains($bootstrap, "'state' => \$currentStep === 'result'"), 'Review & Install is not completed after entering the Result phase.');
$assert(str_contains($bootstrap, "'state' => \$currentStep === 'result' ? 'current' : 'pending'"), 'Installation Result is not current after Install executes.');
$assert(str_contains($committer, 'InstallerDatabaseValidator())->validate'), 'Final commit does not revalidate the staged database input.');
$assert(str_contains($committer, '$this->probe->inspect($configuration)'), 'Final commit does not re-inspect the target database.');
$assert(str_contains($committer, 'InstallerRoutingPlanner())->plan'), 'Final commit does not revalidate installation intent eligibility.');
$assert(str_contains($committer, 'InstallerAdministratorValidator::validate($administratorInput)'), 'Final commit does not revalidate Administrator & Site input before mutation.');
$assert(str_contains($committer, '$this->environment->persist($configuration);'), 'Final commit does not persist the accepted database environment.');
$assert(str_contains($committer, '$this->schema->install($configuration)'), 'Schema materialization is not owned by the commit coordinator.');
$assert(str_contains($committer, '$administrator->installPrepared'), 'First Administrator/Site creation is not part of the commit operation.');
$assert(str_contains($committer, '$finalizer->finalizePrepared()'), 'Theme, baseline Module, and lifecycle finalization are not part of the commit operation.');
$assert(str_contains($committer, '$created = array_values(array_diff($after, $beforeTables))'), 'Failure cleanup does not distinguish newly created tables from pre-existing tables.');
$assert(str_contains($committer, 'DROP TABLE'), 'Ownership-aware cleanup does not remove current-attempt tables after failure.');
$assert(str_contains($committer, '!$environmentExisted && is_file($environmentPath)'), 'Failure cleanup does not remove a newly created environment file.');
$assert(str_contains($committer, '$lock = $this->mutex->acquire()'), 'Commit operation does not acquire the installation coordination boundary.');
$assert(str_contains($committer, 'already been finalized'), 'Repeated installation safety is not guarded.');
$assert(str_contains($view, 'class="installer-summary installer-list"'), 'Review/Result does not use the shared generic summary primitive.');
$assert(str_contains($view, 'Administrator email'), 'Review does not summarize the staged Administrator/Site plan.');
$assert(str_contains($view, 'DB Namespace'), 'Review does not summarize the staged namespace.');
$reviewStart = strpos($view, "<?php elseif ((\$currentStep ?? '') === 'finalize'): ?>");
$resultStart = strpos($view, "<?php elseif ((\$currentStep ?? '') === 'result'): ?>");
$reviewMarkup = ($reviewStart !== false && $resultStart !== false) ? substr($view, $reviewStart, $resultStart - $reviewStart) : '';
$resultMarkup = $resultStart !== false ? substr($view, $resultStart) : '';
$assert($reviewMarkup !== '' && !str_contains($reviewMarkup, 'password'), 'Review markup exposes a staged secret.');
$assert(str_contains($view, 'Installation Result'), 'The Result view is missing.');
$assert(str_contains($resultMarkup, 'installer-footer installer-actions'), 'Successful Result does not use the shared installer footer.');
$assert(str_contains($resultMarkup, 'installer-footer__end"><a class="nav-button install-action"'), 'Continue to Admin is not placed in the shared footer end column.');
$assert(!str_contains($resultMarkup, '<p><a class="button"'), 'Continue to Admin remains in the Result content region.');
$assert(substr_count($bootstrap, "'label' => '") >= 5 && !str_contains($bootstrap, "'label' => 'Modules'"), 'Installer progression does not preserve the authoritative five-phase flow.');
$assert(str_contains($view, 'class="nav-button"'), 'Previous/Next controls do not use the shared navigation primitive.');
$assert(str_contains($view, 'class="nav-button install-action"'), 'Install is not distinguishable as the final mutation action.');
$assert(!str_contains($view, 'Return to Review &amp; Install'), 'Obsolete contextual return navigation remains visible.');
$assert(str_contains($view, 'role="<?= $resultStatus === \'error\' ? \'alert\' : \'status\' ?>"'), 'Result status does not preserve semantic status roles.');
$assert(str_contains($css, '.installer-list'), 'WU4 summary does not use a generic shared installer CSS primitive.');
$assert(!str_contains($view, '<style'), 'WU4 view introduces inline CSS.');
$assert(!str_contains($view, 'review.css') && !str_contains($view, 'result.css'), 'WU4 view introduces page-specific stylesheets.');

fwrite(STDOUT, "WU4 Review/Installation assertions: {$assertions}\n");
