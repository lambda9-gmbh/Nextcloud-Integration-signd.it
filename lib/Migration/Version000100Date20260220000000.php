<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: 2026 lambda9 GmbH <support@lambda9.de>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\IntegrationSignd\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version000100Date20260220000000 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if (!$schema->hasTable('integration_signd_processes')) {
            $table = $schema->createTable('integration_signd_processes');

            $table->addColumn('id', Types::INTEGER, [
                'autoincrement' => true,
                'notnull' => true,
            ]);
            $table->addColumn('file_id', Types::BIGINT, [
                'notnull' => true,
            ]);
            $table->addColumn('process_id', Types::STRING, [
                'notnull' => true,
                'length' => 255,
            ]);
            $table->addColumn('user_id', Types::STRING, [
                'notnull' => true,
                'length' => 64,
            ]);
            $table->addColumn('target_dir', Types::STRING, [
                'notnull' => false,
                'length' => 4000,
            ]);
            $table->addColumn('finished_pdf_path', Types::STRING, [
                'notnull' => false,
                'length' => 4000,
            ]);

            $table->setPrimaryKey(['id']);
            $table->addIndex(['file_id'], 'isignd_proc_file_id');
            $table->addIndex(['process_id'], 'isignd_proc_process_id');
            $table->addIndex(['user_id'], 'isignd_proc_user_id');
        }

        return $schema;
    }
}
