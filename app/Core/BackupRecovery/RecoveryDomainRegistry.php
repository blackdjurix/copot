<?php

namespace Copot\Core\BackupRecovery;

final class RecoveryDomainRegistry
{
    /** @var array<string, RecoveryDomain> */
    private array $domains;

    /**
     * @param array<int, RecoveryDomain> $domains
     */
    public function __construct(array $domains)
    {
        $byIdentifier = [];
        $byOwnership = [];

        foreach ($domains as $domain) {
            if (!$domain instanceof RecoveryDomain) {
                throw new RecoveryInvariantException('Recovery domain registry contains an invalid domain.');
            }

            $definition = $domain->definition();
            $identifier = $definition->identifier();
            $ownershipKey = $definition->ownershipKey();

            if (isset($byIdentifier[$identifier]) || isset($byOwnership[$ownershipKey])) {
                throw new RecoveryInvariantException('Recovery domain registry contains duplicate or ambiguous ownership.');
            }

            $byIdentifier[$identifier] = $domain;
            $byOwnership[$ownershipKey] = true;
        }

        ksort($byIdentifier, SORT_STRING);
        $this->domains = $byIdentifier;
    }

    /** @return array<int, RecoveryDomain> */
    public function all(): array
    {
        return array_values($this->domains);
    }

    public function has(string $identifier): bool
    {
        return array_key_exists($identifier, $this->domains);
    }

    public function get(string $identifier): RecoveryDomain
    {
        if (!array_key_exists($identifier, $this->domains)) {
            throw new RecoveryInvariantException('Recovery domain is not registered.');
        }

        return $this->domains[$identifier];
    }
}
