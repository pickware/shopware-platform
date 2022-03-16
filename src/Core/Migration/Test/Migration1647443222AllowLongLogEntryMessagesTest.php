<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use DateTime;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

class Migration1647443222AllowLongLogEntryMessagesTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testLongMessagesInLogEntriesCanBeWritten(): void
    {
        /** @var Connection $connection */
        $connection = $this->getContainer()->get(Connection::class);

        $logEntryId = Uuid::randomBytes();
        // This string is now 1000 characters long, well beyond the old limit of 255 characters
        $longMessage = str_repeat('some-long-', 100);
        $payload = [
            'id' => $logEntryId,
            'message' => $longMessage,
            'level' => 500,
            'channel' => 'some-test-channel',
            'context' => json_encode([]),
            'extra' => json_encode([]),
            'updated_at' => null,
            'created_at' => (new DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ];

        $connection->insert('log_entry', $payload);

        $logEntry = $connection->fetchAssociative(
            'SELECT `message` FROM `log_entry` WHERE `id` = :id',
            ['id' => $logEntryId],
        );

        static::assertEquals($longMessage, $logEntry['message']);
    }
}
