<?php

namespace Shellpea\CDEK\Api;

use Magento\Quote\Api\Data\AddressInterface;

interface GuestShippingInformationInterface
{
    /**
     * @param string $cartId
     * @param AddressInterface $addressInformation
     * @return void
     */
    public function saveAddressInformation(
        string $cartId,
        AddressInterface $addressInformation
    );
}
