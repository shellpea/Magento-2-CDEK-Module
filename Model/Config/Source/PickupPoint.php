<?php

namespace Shellpea\CDEK\Model\Config\Source;

use Psr\Log\LoggerInterface;
use Shellpea\CDEK\Model\CarrierFactory;
use Magento\Framework\Data\OptionSourceInterface;

class PickupPoint implements OptionSourceInterface
{
    protected $logger;

    protected $carrierFactory;

    public function __construct(
        LoggerInterface $logger,
        CarrierFactory $carrierFactory
    ) {
        $this->logger = $logger;
        $this->carrierFactory = $carrierFactory;
    }

    public function toOptionArray()
    {
        $options = [];
        $pickupPoints = $this->getListPickupPoints();
        $options[] = ['value' => '', 'label' => __('Select Pickup Point')];
        if ($pickupPoints) {
                foreach ($pickupPoints as $pickupPoint) {
                    $label = $pickupPoint['name'] . ' (' . $pickupPoint['location']['address_full'] . ')';
                    $options[] = ['value' => $pickupPoint['code'] , 'label' => __($label)];
                }
        }

        return $options;
    }

    public function getListPickupPoints()
    {
        $carrier = $this->carrierFactory->create();
        if (!$carrier->getConfigFlag('active')) {
            return null;
        }

        $carrier->clientAuthorization();
        $postalCode = $carrier->getConfigData('postcode');
        $countryId = $carrier->getConfigData('country_id');
        if (!$postalCode && !$countryId) {
            return null;
        }

        $pickupPoints = $carrier->getListOfficesForAdmin($postalCode, $countryId);
        if ($carrier->checkErrors($pickupPoints)) {
            $this->logger->error($carrier->checkErrors($pickupPoints));
            return null;
        }

        return $pickupPoints;
    }
}
