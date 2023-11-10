<?php

namespace Shellpea\CDEK\Controller\Checkout;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;

class YandexApi extends Action
{
    /**
     * Core store config
     *
     * @var ScopeConfigInterface
     */
    protected $_scopeConfig;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        Context $context
    ) {
        $this->_scopeConfig = $scopeConfig;
        parent::__construct($context);
    }

    public function execute()
    {
        /** @var \Magento\Framework\Controller\Result\Json $resultJson */
        $resultJson = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $resultJson->setData([
            'enable' => $this->_scopeConfig->isSetFlag("carriers/cdek/enable_yandex_map"),
            'apiKey' => $this->_scopeConfig->getValue("carriers/cdek/yandex_api"),
        ]);
        return $resultJson;
    }
}
