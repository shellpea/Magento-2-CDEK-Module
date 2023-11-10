<?php

namespace Shellpea\CDEK\Model\Shipping;

use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Quote\Api\Data\AddressInterface;
use Shellpea\CDEK\Api\ShippingInformationInterface;
use Shellpea\CDEK\Api\GuestShippingInformationInterface;

class GuestShippingInformation implements GuestShippingInformationInterface
{
    /**
     * @var QuoteIdMaskFactory
     */
    protected $quoteIdMaskFactory;

    /**
     * @var ShippingInformationInterface
     */
    protected $shippingInformation;

    /**
     * @param QuoteIdMaskFactory $quoteIdMaskFactory
     * @param ShippingInformationInterface $shippingInformation
     * @codeCoverageIgnore
     */
    public function __construct(
        QuoteIdMaskFactory $quoteIdMaskFactory,
        ShippingInformationInterface $shippingInformation
    ) {
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
        $this->shippingInformation = $shippingInformation;
    }

    /**
     * @param mixed $cartId
     * @param AddressInterface $addressInformation
     * @return void
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function saveAddressInformation(string $cartId, AddressInterface $addressInformation)
    {
        $quoteIdMask = $this->quoteIdMaskFactory->create()->load($cartId, 'masked_id');
        $this->shippingInformation->saveAddressInformation(
            $quoteIdMask->getQuoteId(),
            $addressInformation
        );
    }
}
