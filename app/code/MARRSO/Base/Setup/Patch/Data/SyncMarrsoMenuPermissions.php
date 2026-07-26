<?php
declare(strict_types=1);

namespace MARRSO\Base\Setup\Patch\Data;

use Magento\Authorization\Model\ResourceModel\Rules\CollectionFactory as RulesCollectionFactory;
use Magento\Authorization\Model\RulesFactory;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * Keeps MARRSO_Base and MARRSO_DeliveryScheduler menu ACL in sync for existing roles.
 */
class SyncMarrsoMenuPermissions implements DataPatchInterface
{
    private const ADMIN_ROLE_ID = 1;

    private const BASE_RESOURCE = 'MARRSO_Base::menu';

    private const DELIVERY_RESOURCES = [
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
        private readonly RulesCollectionFactory $rulesCollectionFactory,
        private readonly RulesFactory $rulesFactory
    ) {
    }

    public function apply(): void
    {
        $this->grantResourcesToRole(self::ADMIN_ROLE_ID, array_merge([self::BASE_RESOURCE], self::DELIVERY_RESOURCES));

        $rolesWithBase = $this->getRoleIdsWithAllow(self::BASE_RESOURCE);
        $rolesWithDelivery = $this->getRoleIdsWithAllow('MARRSO_DeliveryScheduler::menu');

        foreach ($rolesWithBase as $roleId) {
            $this->grantResourcesToRole($roleId, ['MARRSO_DeliveryScheduler::menu']);
        }

        foreach ($rolesWithDelivery as $roleId) {
            $this->grantResourcesToRole($roleId, [self::BASE_RESOURCE]);
        }
    }

    /**
     * @param list<string> $resourceIds
     */
    private function grantResourcesToRole(int $roleId, array $resourceIds): void
    {
        $existing = $this->getExistingResourcesForRole($roleId, $resourceIds);

        foreach ($resourceIds as $resourceId) {
            if (isset($existing[$resourceId])) {
                continue;
            }

            $rule = $this->rulesFactory->create();
            $rule->setRoleId($roleId)
                ->setResourceId($resourceId)
                ->setPermission('allow')
                ->save();
        }
    }

    /**
     * @param list<string> $resourceIds
     * @return array<string, true>
     */
    private function getExistingResourcesForRole(int $roleId, array $resourceIds): array
    {
        $collection = $this->rulesCollectionFactory->create();
        $collection->addFieldToFilter('role_id', $roleId);
        $collection->addFieldToFilter('resource_id', ['in' => $resourceIds]);

        $existing = [];
        foreach ($collection as $rule) {
            if ($rule->getPermission() === 'allow') {
                $existing[(string)$rule->getResourceId()] = true;
            }
        }

        return $existing;
    }

    /**
     * @return list<int>
     */
    private function getRoleIdsWithAllow(string $resourceId): array
    {
        $collection = $this->rulesCollectionFactory->create();
        $collection->addFieldToFilter('resource_id', $resourceId);
        $collection->addFieldToFilter('permission', 'allow');

        $roleIds = [];
        foreach ($collection as $rule) {
            $roleIds[] = (int)$rule->getRoleId();
        }

        return array_values(array_unique($roleIds));
    }

    public static function getDependencies(): array
    {
        return [
            GrantBaseMenuToExistingRoles::class,
            \MARRSO\DeliveryScheduler\Setup\Patch\Data\AssignDeliverySchedulerAcl::class,
        ];
    }

    public function getAliases(): array
    {
        return [];
    }
}
