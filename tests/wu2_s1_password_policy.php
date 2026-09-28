<?php

declare(strict_types=1);

use Copot\Core\InstallerAdministratorValidator;
use Copot\Core\InstallerValidationException;
use Copot\Core\PasswordHasher;
use Copot\Core\PasswordPolicy;
use Copot\Core\SettingsRegistry;
use Copot\Core\SettingsService;

$basePath = dirname(__DIR__);
require $basePath . '/bootstrap/autoload.php';
require $basePath . '/modules/users-access/Services/UsersValidationException.php';
require $basePath . '/modules/users-access/Services/UsersService.php';

final class Wu2S1SettingsServiceStub extends SettingsService
{
    public function __construct(private array $values)
    {
    }

    public function get(string $namespace, string $key, mixed $default = null): mixed
    {
        return $this->values[$namespace . '.' . $key] ?? $default;
    }
}

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;

    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$validInput = static function (string $password, ?string $confirmation = null): array {
    return [
        'admin_name' => 'Administrator',
        'admin_email' => 'admin-' . bin2hex(random_bytes(4)) . '@example.test',
        'admin_password' => $password,
        'admin_password_confirmation' => $confirmation ?? $password,
        'site_name' => 'COPOT',
        'site_tagline' => '',
        'timezone' => 'UTC',
        'locale' => 'en_US',
    ];
};

$validationErrors = static function (array $input, PasswordPolicy $policy): array {
    try {
        InstallerAdministratorValidator::validate($input, $policy);
    } catch (InstallerValidationException $exception) {
        return $exception->errors();
    }

    throw new RuntimeException('Expected installer validation failure.');
};

$policy = new PasswordPolicy();
$assert($policy->minimumLength() === 12, 'Default minimum password length is not 12 characters.');
$assert($policy->maximumLength() === 128, 'Default maximum password length is not 128 characters.');
$assert($policy->validate(str_repeat('a', 12)) === null, 'A 12-character password was rejected.');
$assert($policy->validate(str_repeat('a', 128)) === null, 'A 128-character password was rejected.');
$assert($policy->validate('') !== null, 'A blank password was accepted.');
$assert($policy->validate(str_repeat(' ', 12)) !== null, 'A whitespace-only password was accepted.');
$assert($policy->validate(str_repeat('a', 11)) !== null, 'An 11-character password was accepted.');
$assert($policy->validate(str_repeat('a', 129)) !== null, 'A 129-character password was accepted.');
$assert($policy->validate(str_repeat('a', 12)) === null, 'A password without composition characters was rejected.');

$installerShort = $validationErrors($validInput(str_repeat('a', 11)), $policy);
$assert(isset($installerShort['admin_password']), 'Installer accepted a below-minimum password.');
$installerLong = $validationErrors($validInput(str_repeat('a', 129)), $policy);
$assert(isset($installerLong['admin_password']), 'Installer accepted an above-maximum password.');
$installerMismatch = $validationErrors($validInput(str_repeat('a', 12), str_repeat('b', 12)), $policy);
$assert(isset($installerMismatch['admin_password_confirmation']), 'Installer accepted a confirmation mismatch.');
InstallerAdministratorValidator::validate($validInput(str_repeat('a', 12)), $policy);
InstallerAdministratorValidator::validate($validInput(str_repeat('a', 128)), $policy);
$assert(true, 'Installer boundary validation completed.');

$configured = new PasswordPolicy(new Wu2S1SettingsServiceStub([
    'security.password_min_length' => 5,
    'security.password_max_length' => 20,
]));
$assert($configured->minimumLength() === 5 && $configured->maximumLength() === 20, 'Configured policy was not resolved through SettingsService.');
$assert($configured->validate('1234') !== null && $configured->validate('12345') === null, 'Configured minimum was not enforced.');
$assert($configured->validate(str_repeat('x', 21)) !== null, 'Configured maximum was not enforced.');

$registry = SettingsRegistry::core();
$assert($registry->find('security', 'password_min_length')?->defaultValue() === 12, 'Minimum policy setting is not registered canonically.');
$assert($registry->find('security', 'password_max_length')?->defaultValue() === 128, 'Maximum policy setting is not registered canonically.');

$usersService = (new ReflectionClass(UsersService::class))->newInstanceWithoutConstructor();
$usersServiceReflection = new ReflectionClass(UsersService::class);
$passwordPolicyProperty = $usersServiceReflection->getProperty('passwordPolicy');
$passwordPolicyProperty->setAccessible(true);
$passwordPolicyProperty->setValue($usersService, $policy);
$passwordErrors = $usersServiceReflection->getMethod('passwordErrors');
$passwordErrors->setAccessible(true);
$assert($passwordErrors->invoke($usersService, str_repeat('a', 11), str_repeat('a', 11)) !== [], 'Users & Access accepted a below-minimum password.');
$assert($passwordErrors->invoke($usersService, str_repeat('a', 12), str_repeat('a', 12)) === [], 'Users & Access rejected the minimum boundary.');
$assert($passwordErrors->invoke($usersService, str_repeat('a', 128), str_repeat('a', 128)) === [], 'Users & Access rejected the maximum boundary.');
$assert($passwordErrors->invoke($usersService, str_repeat('a', 129), str_repeat('a', 129)) !== [], 'Users & Access accepted an above-maximum password.');
$assert(isset($passwordErrors->invoke($usersService, str_repeat('a', 12), str_repeat('b', 12))['password_confirmation']), 'Users & Access accepted a confirmation mismatch.');

$hash = (new PasswordHasher())->make('short-old');
$assert((new PasswordHasher())->verify('short-old', $hash), 'Existing hash verification was not independent of policy length.');
$assert((new PasswordPolicy())->validate('short-old') !== null, 'The hash fixture did not exercise an out-of-policy plaintext.');

fwrite(STDOUT, "WU2-S1 password policy assertions: {$assertions}\n");
