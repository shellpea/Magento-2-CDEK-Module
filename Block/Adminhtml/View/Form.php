<?php

namespace Shellpea\CDEK\Block\Adminhtml\View;

use Magento\Backend\Block\Widget\Button;
use Magento\Framework\Exception\LocalizedException;

class Form extends \Magento\Shipping\Block\Adminhtml\View\Form
{
    /**
     * @var string
     */
    protected $trackId;

    /**
     * @var string
     */
    protected $orderUuid = '';

    /**
     * @var string
     */
    protected $barcodeUuid = '';

    /**
     * @var bool
     */
    protected $needDownloadPdf = false;

    public function checkShippingLabel($order)
    {
        if (str_contains($order->getShippingMethod(), 'cdek')) {
            foreach ($this->getShipment()->getTracksCollection() as $trackNumber) {
                if ($trackNumber->getLabelGenerated()) {
                    $this->needDownloadPdf = true;
                    $this->trackId = $trackNumber->getId();
                    $this->orderUuid = $trackNumber->getOrderUuid();
                    $this->barcodeUuid = $trackNumber->getBarcodeUuid();
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * @return string
     * @throws LocalizedException
     */
    public function getPrintLabelButton()
    {
        $data['shipment_id'] = $this->getShipment()->getId();
        if ($this->needDownloadPdf) {
            $path = 'cdek/shipment/printLabel';
            $data['barcode_uuid'] = $this->barcodeUuid;
            $data['order_uuid'] = $this->orderUuid;
            $data['track_id'] = $this->trackId;
        } else {
            $path = 'adminhtml/order_shipment/printLabel';
        }
        $url = $this->getUrl($path, $data);

        return $this->getLayout()->createBlock(
            Button::class
        )->setData(
            ['label' => __('Print Shipping Label'), 'onclick' => 'setLocation(\'' . $url . '\')']
        )->toHtml();
    }
}
