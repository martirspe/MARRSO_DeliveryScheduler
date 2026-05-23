<?php
declare(strict_types=1);

namespace MARRSO\DeliveryScheduler\Controller\Adminhtml\PickupLocation;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;

/**
 * Pickup Location Index Controller
 */
class Index extends Action
{
    const ADMIN_RESOURCE = 'MARRSO_DeliveryScheduler::pickup_locations_view';

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
        $resultPage->setActiveMenu('MARRSO_DeliveryScheduler::pickup_locations');
        $resultPage->getConfig()->getTitle()->prepend(__('Pickup Locations'));

        return $resultPage;
    }
}
