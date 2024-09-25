<?php declare(strict_types=1);

namespace Adyen\Shopware\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1727273187AlterAdyenPayment extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1727273187;
    }

    public function update(Connection $connection): void
    {
        try {
            $connection->executeStatement(<<<SQL
            ALTER TABLE `adyen_payment` DROP FOREIGN KEY `fk.adyen_payment.order_transaction_id`;
        SQL
            );
        } catch (Exception) {
        }
    }
}
