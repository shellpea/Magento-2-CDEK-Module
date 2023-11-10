<?php

namespace Shellpea\CDEK\Model\Shipping;

use Magento\Sales\Model\Order\Shipment;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\LocalizedException;

class LabelGenerator extends \Magento\Shipping\Model\Shipping\LabelGenerator
{
    /**
     * @param Shipment $shipment
     * @param RequestInterface $request
     * @return void
     * @throws LocalizedException
     */
    public function create(Shipment $shipment, RequestInterface $request)
    {
        $order = $shipment->getOrder();
        $carrier = $this->carrierFactory->create($order->getShippingMethod(true)->getCarrierCode());
        if (!$carrier->isShippingLabelsAvailable()) {
            throw new LocalizedException(__('Shipping labels is not available.'));
        }
        $shipment->setPackages($request->getParam('packages'));
        $response = $this->labelFactory->create()->requestToShipment($shipment);
        if ($response->hasErrors()) {
            throw new LocalizedException(__($response->getErrors()));
        }
        if (!$response->hasInfo()) {
            throw new LocalizedException(__('The response to the request is empty.'));
        }
        $labelsContent = [];
        $trackingNumbers = [];
        $info = $response->getInfo();

        if (!empty($info['tracking_number'])) {
            $trackingNumbers[] = $info['tracking_number'];
        }

        if (count($labelsContent)) {
            $outputPdf = $this->combineLabelsPdf($labelsContent);
            $shipment->setShippingLabel($outputPdf->render());
        }
        $carrierCode = $carrier->getCarrierCode();
        $carrierTitle = $this->scopeConfig->getValue(
            'carriers/' . $carrierCode . '/title',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $shipment->getStoreId()
        );
        if (!empty($trackingNumbers)) {
            $this->addTrackingNumbersToShipment(
                $shipment,
                $trackingNumbers,
                $carrierCode,
                $carrierTitle,
                $info['order_uuid'] ?? null,
                $info['barcode_uuid'] ?? null,
                isset($info['order_uuid']) || isset($info['barcode_uuid'])
            );
        }
    }

    /**
     * @param \Magento\Sales\Model\Order\Shipment $shipment
     * @param array $trackingNumbers
     * @param string $carrierCode
     * @param string $carrierTitle
     *
     * @return void
     */
    public function addTrackingNumbersToShipment(
        Shipment $shipment,
        $trackingNumbers,
        $carrierCode,
        $carrierTitle,
        $orderUuid,
        $barcodeUuid,
        $labelGenerated
    ) {
        foreach ($shipment->getTracksCollection() as $trackNumber) {
            if ($trackNumber->getLabelGenerated()) {
                $trackNumber->setLabelGenerated(false);
            }
        }
        foreach ($trackingNumbers as $number) {
            if (is_array($number)) {
                $this->addTrackingNumbersToShipment($shipment, $number, $carrierCode, $carrierTitle);
            } else {
                $shipment->addTrack(
                    $this->trackFactory->create()
                        ->setNumber($number)
                        ->setCarrierCode($carrierCode)
                        ->setTitle($carrierTitle)
                        ->setOrderUuid($orderUuid)
                        ->setBarcodeUuid($barcodeUuid)
                        ->setLabelGenerated($labelGenerated)
                );
            }
        }
    }
}
