<?php

namespace Shellpea\CDEK\Model\Shipping;

use Magento\Quote\Api\Data\AddressInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Shellpea\CDEK\Api\ShippingInformationInterface;

class ShippingInformation implements ShippingInformationInterface
{
    /**
     * @var CartRepositoryInterface
     */
    protected $quoteRepository;

    public function __construct(
        CartRepositoryInterface $quoteRepository,
    ) {
        $this->quoteRepository = $quoteRepository;
    }

    /**
     * Save address information.
     *
     * @param int $cartId
     * @param AddressInterface $addressInformation
     * @return void
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function saveAddressInformation(int $cartId, AddressInterface $addressInformation): void
    {
        /**
         * @var \Magento\Quote\Model\Quote $quote
         */
        $quote = $this->quoteRepository->getActive($cartId);
        $pickupPoint = $addressInformation->getExtensionAttributes()->getPickupPoint();
        $shippingAddress = $quote->getShippingAddress();
        if ($pickupPoint) {
            $shippingAddress->setPickupPoint($pickupPoint)->save();
        } else {
            $shippingAddress->setPickupPoint(null)->save();
        }
    }
}
