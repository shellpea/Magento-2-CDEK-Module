<?php

namespace Shellpea\CDEK\Controller\Webhooks;

use Psr\Log\LoggerInterface;
use Shellpea\CDEK\Model\CarrierFactory;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Shipping\Model\Order\TrackFactory;
use Shellpea\CDEK\Model\Shipping\LabelGenerator;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Sales\Model\ResourceModel\Order\Shipment\CollectionFactory;

class Index extends Action implements HttpPostActionInterface, CsrfAwareActionInterface
{
    protected $logger;

    protected $carrier;

    protected $trackFactory;

    protected $labelGenerator;

    protected $shipmentCollection;

    public function __construct(
        Context $context,
        CarrierFactory $carrier,
        LoggerInterface $logger,
        TrackFactory $trackFactory,
        LabelGenerator $labelGenerator,
        CollectionFactory $shipmentCollection
    ) {
        $this->logger = $logger;
        $this->carrier = $carrier;
        $this->trackFactory = $trackFactory;
        $this->labelGenerator = $labelGenerator;
        $this->shipmentCollection = $shipmentCollection;
        parent::__construct($context);
    }

    public function execute()
    {
        $body = $this->_request->getContent();
        $response = json_decode($body, true);
        $this->logger->error(__('Webhooks Controller'));
        if ($response['type'] == 'PRINT_FORM'
            && isset($response['attributes']['type'])
            && $response['attributes']['type'] == 'BARCODE'
        ) {
            $url = $response['attributes']['url'];
            $url = substr(strrchr($url, '/'), 1);
            $carrier = $this->carrier->create();
            $carrier->clientAuthorization();
            $content = $carrier->getContentCPForOrder($url);
            if ($content) {
                $trackNumber = $this->trackFactory->create()->getCollection()
                    ->addFieldToFilter('barcode_uuid', $response['uuid'])->getFirstItem();
                $shipment = $this->shipmentCollection->create()
                    ->addFieldToFilter('entity_id', $trackNumber->getParentId())->getFirstItem();
                $outputPdf = $this->labelGenerator->combineLabelsPdf([$content]);
                if ($shipment->getId()) {
                    $shipment->setShippingLabel($outputPdf->render())->save();
                    $trackNumber->setLabelGenerated(false)->save();
                    $this->logger->info('Create Shipping Label ' . $trackNumber->getTrackNumber() . ' for shipment ' . $shipment->getId());
                } else {
                    $this->logger->error(__('Shipment was not found. Barcode Uuid = ' . $response['uuid']));
                }
            } else {
                $this->logger->error(__('Content is empty. Uuid = ' . $response['uuid']));
            }
        }
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}
