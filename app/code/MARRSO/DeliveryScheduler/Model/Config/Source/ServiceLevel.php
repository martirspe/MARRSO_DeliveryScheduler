<?php
declare(strict_types=1);

namespace MARRSO\DeliveryScheduler\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use MARRSO\DeliveryScheduler\Model\ServiceLevel as ServiceLevelConstants;

class ServiceLevel implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => ServiceLevelConstants::SCHEDULED, 'label' => __('Scheduled delivery')],
            ['value' => ServiceLevelConstants::EXPRESS_24, 'label' => __('Express 24 hours')],
            ['value' => ServiceLevelConstants::EXPRESS_180, 'label' => __('Express 180 minutes')],
        ];
    }
}
