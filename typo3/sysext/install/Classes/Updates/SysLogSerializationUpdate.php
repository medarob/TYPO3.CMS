<?php

declare(strict_types=1);

namespace TYPO3\CMS\Install\Updates;

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

use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Log\LogDataTrait;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * @internal This class is only meant to be used within EXT:install and is not part of the TYPO3 Core API.
 */
class SysLogSerializationUpdate implements UpgradeWizardInterface
{
    use LogDataTrait;
    private const TABLE_NAME = 'sys_log';

    public function getIdentifier(): string
    {
        return 'sysLogSerialization';
    }

    public function getTitle(): string
    {
        return 'Migrate sys_log entries to a JSON formatted value.';
    }

    public function getDescription(): string
    {
        return 'All sys_log_entries are now updated to contain JSON values in the "log_data" field.';
    }

    public function getPrerequisites(): array
    {
        return [
            DatabaseUpdatedPrerequisite::class,
        ];
    }

    public function updateNecessary(): bool
    {
        return $this->hasRecordsToUpdate();
    }

    public function executeUpdate(): bool
    {
        $connection = $this->getConnectionPool()->getConnectionForTable(self::TABLE_NAME);

        foreach ($this->getRecordsToUpdate() as $record) {
            $logData = $this->unserializeLogData($record['log_data'] ?? '');
            $connection->update(
                self::TABLE_NAME,
                ['log_data' => json_encode($logData)],
                ['uid' => (int)$record['uid']]
            );
        }
        return true;
    }

    protected function hasRecordsToUpdate(): bool
    {
        $queryBuilder = $this->getPreparedQueryBuilder();
        return (bool)$queryBuilder
            ->count('uid')
            ->where(
                $queryBuilder->expr()->like('log_data', $queryBuilder->createNamedParameter('a:%'))
            )
            ->executeQuery()
            ->fetchOne();
    }

    protected function getRecordsToUpdate(): array
    {
        $queryBuilder = $this->getPreparedQueryBuilder();
        return $queryBuilder
            ->select('uid', 'log_data')
            ->where(
                $queryBuilder->expr()->like('log_data', $queryBuilder->createNamedParameter('a:%'))
            )
            ->executeQuery()
            ->fetchAllAssociative();
    }

    protected function getPreparedQueryBuilder(): QueryBuilder
    {
        $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable(self::TABLE_NAME);
        $queryBuilder->from(self::TABLE_NAME);
        return $queryBuilder;
    }

    protected function getConnectionPool(): ConnectionPool
    {
        return GeneralUtility::makeInstance(ConnectionPool::class);
    }
}
