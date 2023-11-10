<?php

namespace Shellpea\CDEK\Observer;

use Psr\Log\LoggerInterface;
use Shellpea\CDEK\Model\Carrier;
use Magento\Framework\Event\Observer;
use Shellpea\CDEK\Model\CarrierFactory;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Exception\CouldNotDeleteException;

class CdekWebhooks implements ObserverInterface
{
    protected $logger;

    protected $carrier;

    protected $messageManager;

    public function __construct(
        CarrierFactory $carrier,
        LoggerInterface $logger,
        ManagerInterface $messageManager
    ) {
        $this->logger = $logger;
        $this->carrier = $carrier;
        $this->messageManager = $messageManager;
    }

    public function execute(Observer $observer)
    {
        $carrier = $this->carrier->create();
        if (!$carrier->getConfigFlag('active') || $carrier->getConfigValue(Carrier::TEST_MODE_PATH)) {
            return null;
        }

        try {
            $carrier->clientAuthorization();
        } catch (CouldNotDeleteException $error) {
            $this->logger->error(__("Couldn\'t add a subscription to the webhooks: " . $error));
            return null;
        }

        $webhooksEnable = $carrier->getConfigData(Carrier::WEBHOOKS_ENABLE_PATH);
        $uuid = $carrier->getConfigDataByPath(Carrier::WEBHOOKS_UUID_PATH)->getValue();
        if (!$uuid && $webhooksEnable) {
            $response = $carrier->addWebhookSubscription();
        } else if ($uuid && !$webhooksEnable) {
            $response = $carrier->deleteWebhookSubscription($uuid);
        } else {
            $this->logger->error(__("A subscription to webhooks has already been added." ));
            return null;
        }

        if (!$response) {
            $this->logger->error(__("Couldn\'t add a subscription to the webhooks." ));
            return null;
        }

        if ($carrier->checkErrors($response)) {
            $this->logger->error(__('CDEK Delivery: ' . $carrier->checkErrors($response)));
            return null;
        }

        $value = $webhooksEnable ? $response['entity']['uuid'] : null;
        $carrier->setConfigValue(Carrier::WEBHOOKS_UUID_PATH, $value);
    }
}
