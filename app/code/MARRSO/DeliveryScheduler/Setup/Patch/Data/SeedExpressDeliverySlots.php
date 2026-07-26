<?php
declare(strict_types=1);

namespace MARRSO\DeliveryScheduler\Setup\Patch\Data;

use Magento\Framework\Setup\Patch\DataPatchInterface;
use MARRSO\DeliveryScheduler\Model\Service\ExpressSlotGenerator;

/**
 * Seeds express delivery slots for demo / initial install.
 */
class SeedExpressDeliverySlots implements DataPatchInterface
{
    public function __construct(
        private readonly ExpressSlotGenerator $expressSlotGenerator
    ) {
    }

    public function apply(): void
    {
        $this->expressSlotGenerator->generate();
    }

    public static function getDependencies(): array
    {
        return [SeedLimaDemoData::class];
    }

    public function getAliases(): array
    {
        return [];
    }
}
