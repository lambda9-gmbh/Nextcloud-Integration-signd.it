<?php

declare(strict_types=1);

// SPDX-FileCopyrightText: 2026 lambda9 GmbH <support@lambda9.de>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\IntegrationSignd\Tests\Unit\Db;

use OCA\IntegrationSignd\Db\Process;
use PHPUnit\Framework\TestCase;

class ProcessTest extends TestCase {
    public function testEntityGettersAndSetters(): void {
        $process = new Process();
        $process->setFileId(42);
        $process->setProcessId('proc-abc-123');
        $process->setUserId('admin');

        $this->assertSame(42, $process->getFileId());
        $this->assertSame('proc-abc-123', $process->getProcessId());
        $this->assertSame('admin', $process->getUserId());
    }

    public function testNullableFieldsDefaultToNull(): void {
        $process = new Process();

        $this->assertNull($process->getTargetDir());
        $this->assertNull($process->getFinishedPdfPath());
    }

    public function testNullableFieldsCanBeSet(): void {
        $process = new Process();

        $process->setTargetDir('/Documents/contracts');
        $this->assertSame('/Documents/contracts', $process->getTargetDir());

        $process->setFinishedPdfPath('/Documents/contracts/contract_signed.pdf');
        $this->assertSame('/Documents/contracts/contract_signed.pdf', $process->getFinishedPdfPath());
    }

    public function testNullableFieldsCanBeSetBackToNull(): void {
        $process = new Process();

        $process->setTargetDir('/some/dir');
        $process->setTargetDir(null);
        $this->assertNull($process->getTargetDir());

        $process->setFinishedPdfPath('/some/file.pdf');
        $process->setFinishedPdfPath(null);
        $this->assertNull($process->getFinishedPdfPath());
    }

    public function testFileIdTypeIsInteger(): void {
        $process = new Process();
        $process->setFileId(100);

        // fileId is typed as integer via addType in constructor
        $this->assertIsInt($process->getFileId());
    }
}
