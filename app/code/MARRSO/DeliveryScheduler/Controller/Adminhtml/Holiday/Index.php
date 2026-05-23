<?php
declare(strict_types=1);

namespace MARRSO\DeliveryScheduler\Controller\Adminhtml\Holiday;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;

/**
 * Holiday Index Controller
 */
class Index extends Action
{
    const ADMIN_RESOURCE = 'MARRSO_DeliveryScheduler::holidays_view';

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
        $resultPage->getConfig()->getTitle()->prepend(__('Holidays'));

        return $resultPage;
    }
}
