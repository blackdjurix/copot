<?php

namespace Copot\Core;

final class PackageV3Identity
{
    public static function manifestDigest(string $rawManifest): string { return hash('sha256', $rawManifest); }
    public static function digest(string $manifestSha256, string $deliveredPayloadIntegrityDigest): string
    {
        self::hash($manifestSha256); self::hash($deliveredPayloadIntegrityDigest);
        return hash('sha256', "COPOT-V3-PACKAGE\0" . hex2bin($manifestSha256) . hex2bin($deliveredPayloadIntegrityDigest));
    }
    public static function identity(string $manifestSha256, string $deliveredPayloadIntegrityDigest): string { return 'v3-sha256:' . self::digest($manifestSha256, $deliveredPayloadIntegrityDigest); }
    public static function assertIdentity(string $identity): void { if (preg_match('/^v3-sha256:[a-f0-9]{64}$/D', $identity) !== 1) { throw new \InvalidArgumentException('V3 package identity is invalid.'); } }
    private static function hash(string $value): void { if (preg_match('/^[a-f0-9]{64}$/D', $value) !== 1) { throw new \InvalidArgumentException('SHA-256 identity is invalid.'); } }
}
