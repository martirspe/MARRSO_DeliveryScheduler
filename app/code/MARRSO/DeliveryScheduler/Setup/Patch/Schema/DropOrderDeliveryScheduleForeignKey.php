<?php
declare(strict_types=1);

namespace MARRSO\DeliveryScheduler\Setup\Patch\Schema;

use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\SchemaPatchInterface;

/**
 * Removes legacy FK on order_id so declarative schema can alter the column safely.
 */
class DropOrderDeliveryScheduleForeignKey implements SchemaPatchInterface
{
    private const LEGACY_FK_NAME = 'MARRSO_ORDER_DELIVERY_SCHEDULE_ORDER_ID_SALES_ORDER_ENTITY_ID';

    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup
    ) {
    }

    public function apply(): void
    {
        $connection = $this->moduleDataSetup->getConnection();
        $tableName = $this->moduleDataSetup->getTable('marrso_order_delivery_schedule');

        if (!$connection->isTableExists($tableName)) {
            return;
        }

        $this->dropForeignKeyIfExists($connection, $tableName, self::LEGACY_FK_NAME);

        foreach ($connection->getForeignKeys($tableName) as $foreignKey) {
            if (($foreignKey['COLUMN_NAME'] ?? '') === 'order_id') {
                $this->dropForeignKeyIfExists($connection, $tableName, (string)$foreignKey['FK_NAME']);
            }
        }
    }

    private function dropForeignKeyIfExists(
        AdapterInterface $connection,
        string $tableName,
        string $foreignKeyName
    ): void {
        if ($foreignKeyName === '') {
            return;
        }

        try {
            $connection->dropForeignKey($tableName, $foreignKeyName);
        } catch (\Exception $e) {
            // FK already removed or never existed.
        }
    }

    public static function getDependencies(): array
    {
        return [];
    }

    public function getAliases(): array
    {
        return [];
    }
}
