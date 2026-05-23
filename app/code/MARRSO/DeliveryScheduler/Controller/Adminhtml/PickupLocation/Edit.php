<?php
declare(strict_types=1);

namespace MARRSO\DeliveryScheduler\Controller\Adminhtml\PickupLocation;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;

/**
 * Pickup Location New/Edit Controller
 */
class Edit extends Action
{
    const ADMIN_RESOURCE = 'MARRSO_DeliveryScheduler::pickup_locations_update';

    /**
     * @var PageFactory
     */
    protected $resultPageFactory;

    public function __construct(Context $context, PageFactory $resultPageFactory)
    {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
    }

    public function execute()
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->getConfig()->getTitle()->prepend(__('Edit Pickup Location'));

        return $resultPage;
    }
}
