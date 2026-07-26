<?php
declare(strict_types=1);

namespace MARRSO\Base\Controller\Adminhtml\Dashboard;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;

class Index extends Action
{
    public const ADMIN_RESOURCE = 'MARRSO_Base::menu';

    public function __construct(
        Context $context,
        private readonly PageFactory $resultPageFactory
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('MARRSO_Base::marrso');
        $resultPage->getConfig()->getTitle()->prepend(__('MARRSO'));

        return $resultPage;
    }
}
