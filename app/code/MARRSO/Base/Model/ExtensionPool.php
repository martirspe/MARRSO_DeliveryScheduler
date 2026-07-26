<?php
declare(strict_types=1);

namespace MARRSO\Base\Model;

use Magento\Framework\AuthorizationInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Event\ManagerInterface;

class ExtensionPool
{
    public const EVENT_COLLECT_EXTENSIONS = 'marrso_base_collect_extensions';

    public function __construct(
        private readonly ManagerInterface $eventManager,
        private readonly AuthorizationInterface $authorization
    ) {
    }

    /**
     * @return list<array{title: string, description: string, sort_order: int, dashboard_path: string, config_path: string|null, config_params: array<string, string>, acl_resource: string}>
     */
    public function getExtensions(): array
    {
        $transport = new DataObject(['extensions' => []]);
        $this->eventManager->dispatch(self::EVENT_COLLECT_EXTENSIONS, ['transport' => $transport]);

        $extensions = [];
        foreach ($transport->getExtensions() as $extension) {
            if (!is_array($extension)) {
                continue;
            }

            $aclResource = (string)($extension['acl_resource'] ?? 'MARRSO_Base::menu');
            if (!$this->authorization->isAllowed($aclResource)) {
                continue;
            }

            $extensions[] = [
                'title' => (string)($extension['title'] ?? ''),
                'description' => (string)($extension['description'] ?? ''),
                'sort_order' => (int)($extension['sort_order'] ?? 100),
                'dashboard_path' => (string)($extension['dashboard_path'] ?? ''),
                'config_path' => isset($extension['config_path']) ? (string)$extension['config_path'] : null,
                'config_params' => is_array($extension['config_params'] ?? null) ? $extension['config_params'] : [],
                'acl_resource' => $aclResource,
            ];
        }

        usort(
            $extensions,
            static fn (array $left, array $right): int => $left['sort_order'] <=> $right['sort_order']
        );

        return $extensions;
    }
}
