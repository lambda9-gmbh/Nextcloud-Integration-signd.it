<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: 2026 lambda9 GmbH <support@lambda9.de>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\IntegrationSignd\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/** @template-extends QBMapper<Process> */
class ProcessMapper extends QBMapper {
    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'integration_signd_processes', Process::class);
    }

    /**
     * @return Process[]
     */
    public function findByFileId(int $fileId): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('file_id', $qb->createNamedParameter($fileId, IQueryBuilder::PARAM_INT)))
            ->orderBy('id', 'DESC');

        return $this->findEntities($qb);
    }

    /**
     * @return Process[]
     */
    public function findAll(): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')->from($this->getTableName());

        return $this->findEntities($qb);
    }

    /**
     * @throws DoesNotExistException
     */
    public function findByProcessId(string $processId): Process {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('process_id', $qb->createNamedParameter($processId)));

        return $this->findEntity($qb);
    }
}
