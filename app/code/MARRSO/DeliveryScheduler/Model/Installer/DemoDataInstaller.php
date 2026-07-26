<?php
declare(strict_types=1);

namespace MARRSO\DeliveryScheduler\Model\Installer;

use MARRSO\DeliveryScheduler\Model\Service\ExpressSlotGenerator;
use MARRSO\DeliveryScheduler\Setup\Patch\Data\SeedExtendedDemoData;
use MARRSO\DeliveryScheduler\Setup\Patch\Data\SeedLimaDemoData;
use MARRSO\DeliveryScheduler\Setup\Patch\Data\SeedPickupSlotsManualControl;

/**
 * Idempotent demo data installer for QA / local environments.
 */
class DemoDataInstaller
{
    public function __construct(
        private readonly SeedLimaDemoData $seedLimaDemoData,
        private readonly ExpressSlotGenerator $expressSlotGenerator,
        private readonly SeedExtendedDemoData $seedExtendedDemoData,
        private readonly SeedPickupSlotsManualControl $seedPickupSlotsManualControl
    ) {
    }

    public function install(): void
    {
        $this->seedLimaDemoData->apply();
        $this->expressSlotGenerator->generate();
        $this->seedExtendedDemoData->apply();
        $this->seedPickupSlotsManualControl->apply();
    }
}
