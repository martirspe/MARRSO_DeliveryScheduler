<?php
declare(strict_types=1);

namespace MARRSO\DeliveryScheduler\Setup\Patch\Data;

use Magento\Authorization\Model\ResourceModel\Rules\CollectionFactory as RulesCollectionFactory;
use Magento\Authorization\Model\Rules;
use Magento\Authorization\Model\RulesFactory;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;

/**
 * Grants Delivery Scheduler ACL to the Administrators role.
 */
class AssignDeliverySchedulerAcl implements DataPatchInterface
{
    private const ADMIN_ROLE_ID = 1;

    private const RESOURCES = [
        'MARRSO_DeliveryScheduler::menu',
        'MARRSO_DeliveryScheduler::config',
        'MARRSO_DeliveryScheduler::pickup_locations',
        'MARRSO_DeliveryScheduler::pickup_locations_view',
        'MARRSO_DeliveryScheduler::pickup_locations_create',
        'MARRSO_DeliveryScheduler::pickup_locations_update',
        'MARRSO_DeliveryScheduler::pickup_locations_delete',
        'MARRSO_DeliveryScheduler::delivery_slots',
        'MARRSO_DeliveryScheduler::delivery_slots_view',
        'MARRSO_DeliveryScheduler::delivery_slots_create',
        'MARRSO_DeliveryScheduler::delivery_slots_update',
        'MARRSO_DeliveryScheduler::delivery_slots_delete',
        'MARRSO_DeliveryScheduler::pickup_slots',
        'MARRSO_DeliveryScheduler::pickup_slots_view',
        'MARRSO_DeliveryScheduler::pickup_slots_create',
        'MARRSO_DeliveryScheduler::pickup_slots_update',
        'MARRSO_DeliveryScheduler::pickup_slots_delete',
        'MARRSO_DeliveryScheduler::holidays',
        'MARRSO_DeliveryScheduler::holidays_view',
        'MARRSO_DeliveryScheduler::holidays_create',
        'MARRSO_DeliveryScheduler::holidays_update',
        'MARRSO_DeliveryScheduler::holidays_delete',
    ];

    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly RulesCollectionFactory $rulesCollectionFactory,
        private readonly RulesFactory $rulesFactory
    ) {
    }

    public function apply(): void
    {
        $this->moduleDataSetup->getConnection()->startSetup();

        $collection = $this->rulesCollectionFactory->create();
        $collection->addFieldToFilter('role_id', self::ADMIN_ROLE_ID);
        $collection->addFieldToFilter('resource_id', ['in' => self::RESOURCES]);

        $existing = [];
        foreach ($collection as $rule) {
            $existing[$rule->getResourceId()] = true;
        }

        foreach (self::RESOURCES as $resourceId) {
            if (isset($existing[$resourceId])) {
                continue;
            }

            /** @var Rules $rule */
            $rule = $this->rulesFactory->create();
            $rule->setRoleId(self::ADMIN_ROLE_ID)
                ->setResourceId($resourceId)
                ->setPermission('allow');
            $rule->save();
        }

        $this->moduleDataSetup->getConnection()->endSetup();
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
