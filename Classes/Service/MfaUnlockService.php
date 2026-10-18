<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

namespace GeorgRinger\MfaUnlock\Service;

use Doctrine\DBAL\ParameterType;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Authentication\Mfa\MfaProviderPropertyManager;
use TYPO3\CMS\Core\Authentication\Mfa\MfaProviderRegistry;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final readonly class MfaUnlockService
{
    public function __construct(
        private ConnectionPool $connectionPool,
        private MfaProviderRegistry $mfaProviderRegistry,
    ) {}

    /**
     * @return list<LockedUser>
     */
    public function findLockedUsers(): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('be_users');
        $queryBuilder->getRestrictions()->removeAll();
        $rows = $queryBuilder
            ->select('*')
            ->from('be_users')
            ->where(
                $queryBuilder->expr()->eq('deleted', 0),
                $queryBuilder->expr()->isNotNull('mfa'),
                $queryBuilder->expr()->neq('mfa', $queryBuilder->createNamedParameter('')),
            )
            ->orderBy('username')
            ->executeQuery()
            ->fetchAllAssociative();

        $lockedUsers = [];
        foreach ($rows as $row) {
            $providers = [];
            foreach ($this->mfaProviderRegistry->getLockedProviders($this->createUser($row)) as $identifier => $provider) {
                $providers[$identifier] = $provider->getTitle();
            }
            if ($providers !== []) {
                $lockedUsers[] = new LockedUser(
                    (int)$row['uid'],
                    (string)$row['username'],
                    (string)$row['realName'],
                    (bool)$row['disable'],
                    $providers,
                );
            }
        }
        return $lockedUsers;
    }

    /**
     * Find a backend user by uid or by username. A numeric value is looked up as uid first and
     * as username when no such uid exists.
     *
     * @return array<string, mixed>|null
     */
    public function findUser(string $uidOrUsername): ?array
    {
        if (ctype_digit($uidOrUsername)) {
            $row = $this->fetchUser('uid', (int)$uidOrUsername, ParameterType::INTEGER);
            if ($row !== null) {
                return $row;
            }
        }
        return $this->fetchUser('username', $uidOrUsername, ParameterType::STRING);
    }

    /**
     * Resets the failed attempts of all locked providers of the user.
     *
     * @param array<string, mixed> $row be_users record
     * @return list<string> identifiers of the unlocked providers
     */
    public function unlock(array $row): array
    {
        $user = $this->createUser($row);
        $request = new ServerRequest();
        $unlocked = [];
        foreach ($this->mfaProviderRegistry->getLockedProviders($user) as $identifier => $provider) {
            if ($provider->unlock($request, MfaProviderPropertyManager::create($provider, $user))) {
                $unlocked[] = $identifier;
            }
        }
        return $unlocked;
    }

    /**
     * @return int number of users with at least one unlocked provider
     */
    public function unlockAll(): int
    {
        $count = 0;
        foreach ($this->findLockedUsers() as $lockedUser) {
            $row = $this->findUser((string)$lockedUser->uid);
            if ($row !== null && $this->unlock($row) !== []) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchUser(string $field, int|string $value, ParameterType $type): ?array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('be_users');
        $queryBuilder->getRestrictions()->removeAll();
        $row = $queryBuilder
            ->select('*')
            ->from('be_users')
            ->where(
                $queryBuilder->expr()->eq('deleted', 0),
                $queryBuilder->expr()->eq($field, $queryBuilder->createNamedParameter($value, $type)),
            )
            ->executeQuery()
            ->fetchAssociative();
        return $row === false ? null : $row;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function createUser(array $row): BackendUserAuthentication
    {
        $user = GeneralUtility::makeInstance(BackendUserAuthentication::class);
        $user->user = $row;
        return $user;
    }
}
