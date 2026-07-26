<?php
declare(strict_types=1);

namespace MARRSO\Base\Block\Adminhtml;

use MARRSO\Base\Model\ExtensionPool;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;

class Dashboard extends Template
{
    public function __construct(
        Context $context,
        private readonly ExtensionPool $extensionPool,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @return list<array{title: string, description: string, dashboard_url: string, config_url: string|null}>
     */
    public function getExtensions(): array
    {
        $extensions = [];

        foreach ($this->extensionPool->getExtensions() as $extension) {
            if ($extension['dashboard_path'] === '') {
                continue;
            }

            $extensions[] = [
                'title' => $extension['title'],
                'description' => $extension['description'],
                'dashboard_url' => $this->getUrl($extension['dashboard_path']),
                'config_path' => $extension['config_path'],
                'config_params' => $extension['config_params'] ?? [],
                'config_url' => $extension['config_path']
                    ? $this->getUrl($extension['config_path'], $extension['config_params'] ?? [])
                    : null,
            ];
        }

        return $extensions;
    }
}
