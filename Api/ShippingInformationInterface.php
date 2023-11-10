<?php

namespace Shellpea\CDEK\Api;

use Magento\Quote\Api\Data\AddressInterface;

interface ShippingInformationInterface
{
    /**
     * @param int $cartId
     * @param AddressInterface $addressInformation
     * @return void
     */
    public function saveAddressInformation(
        int $cartId,
        AddressInterface $addressInformation
    );
}
