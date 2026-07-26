<?php
declare(strict_types=1);

namespace MARRSO\DeliveryScheduler\Observer;

use Magento\Framework\DataObject;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use MARRSO\Base\Model\ExtensionPool;

class RegisterBaseExtension implements ObserverInterface
{
    public function execute(Observer $observer): void
    {
        /** @var DataObject $transport */
        $transport = $observer->getData('transport');
        $extensions = $transport->getExtensions();
        if (!is_array($extensions)) {
            $extensions = [];
        }

        $extensions[] = [
            'title' => (string)__('Delivery Scheduler'),
            'description' => (string)__(
                'Pickup points, home delivery, express slots and Falabella-style checkout scheduling.'
            ),
            'sort_order' => 10,
            'dashboard_path' => 'marrso_delivery_scheduler/dashboard/index',
            'config_path' => 'adminhtml/system_config/edit',
            'config_params' => ['section' => 'marrso_delivery_scheduler'],
            'acl_resource' => 'MARRSO_DeliveryScheduler::menu',
        ];

        $transport->setExtensions($extensions);
    }
}
