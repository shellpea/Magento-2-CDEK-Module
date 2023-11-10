<?php


namespace Shellpea\CDEK\Model\Quote\Address;

use Shellpea\CDEK\Model\Carrier;
use Magento\Quote\Model\Quote\Address;
use Magento\Sales\Api\Data\OrderAddressInterface;

class ToOrderAddress extends \Magento\Quote\Model\Quote\Address\ToOrderAddress
{
    /**
     * @param Address $object
     * @param array $data
     * @return OrderAddressInterface
     */
    public function convert(Address $object, $data = [])
    {
        $orderAddress = $this->orderAddressRepository->create();
        $orderAddressData = $this->objectCopyService->getDataFromFieldset(
            'sales_convert_quote_address',
            'to_order_address',
            $object
        );

        $this->dataObjectHelper->populateWithArray(
            $orderAddress,
            array_merge($orderAddressData, $data),
            OrderAddressInterface::class
        );

        if ($object->getAddressType() == 'shipping'
            && $object->getLimitCarrier() == 'cdek'
        ) {
            $tariff = substr(strrchr($object->getShippingMethod(), "_"), 1);
            if (in_array($tariff, Carrier::TARIFF_CODES_TO_PICKUP_POINT)) {
                $orderAddress->setPickupPoint($object->getPickupPoint());
            }
        }

        return $orderAddress;
    }
}
