<?php
declare(strict_types=1);

namespace MARRSO\Base\Setup\Patch\Data;

use Magento\Authorization\Model\ResourceModel\Rules\CollectionFactory as RulesCollectionFactory;
use Magento\Authorization\Model\RulesFactory;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class GrantBaseMenuToExistingRoles implements DataPatchInterface
{
    private const BASE_RESOURCE = 'MARRSO_Base::menu';
    private const EXTENSION_RESOURCE = 'MARRSO_DeliveryScheduler::menu';

    public function __construct(
        private readonly RulesCollectionFactory $rulesCollectionFactory,
        private readonly RulesFactory $rulesFactory
    ) {
    }

    public function apply(): void
    {
        $roleIds = [];
        $rulesCollection = $this->rulesCollectionFactory->create();
        $rulesCollection->addFieldToFilter('resource_id', self::EXTENSION_RESOURCE);

        foreach ($rulesCollection as $rule) {
            if ($rule->getPermission() === 'allow') {
                $roleIds[(int)$rule->getRoleId()] = true;
            }
        }

        if ($roleIds === []) {
            return;
        }

        $existingBaseRules = $this->rulesCollectionFactory->create();
        $existingBaseRules->addFieldToFilter('resource_id', self::BASE_RESOURCE);

        foreach ($existingBaseRules as $rule) {
            unset($roleIds[(int)$rule->getRoleId()]);
        }

        foreach (array_keys($roleIds) as $roleId) {
            $rule = $this->rulesFactory->create();
            $rule->setRoleId($roleId)
                ->setResourceId(self::BASE_RESOURCE)
                ->setPermission('allow')
                ->save();
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
