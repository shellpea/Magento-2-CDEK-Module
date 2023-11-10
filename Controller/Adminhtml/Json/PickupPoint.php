<?php

namespace Shellpea\CDEK\Controller\Adminhtml\Json;

use Psr\Log\LoggerInterface;
use Magento\Backend\App\Action\Context;
use Shellpea\CDEK\Model\CarrierFactory;
use Magento\Framework\Json\Helper\Data;

class PickupPoint extends \Magento\Backend\App\Action
{
    protected $logger;

    protected $carrierFactory;

    public function __construct(
        Context $context,
        LoggerInterface $logger,
        CarrierFactory $carrierFactory
    ) {
        $this->logger = $logger;
        $this->carrierFactory = $carrierFactory;
        parent::__construct($context);

    }

    /**
     * Return JSON-encoded array of country regions
     *
     * @return string
     */
    public function execute()
    {
        $carrier = $this->carrierFactory->create();
        if (!$carrier->getConfigFlag('active')) {
            return null;
        }

        $carrier->clientAuthorization();
        $postcode = $this->getRequest()->getParam('postcode');
        $countryId = $this->getRequest()->getParam('countryId');
        if (!$postcode && !$countryId) {
            return null;
        }

        $pickupPoints = $carrier->getListOfficesForAdmin($postcode, $countryId);
        $errors = $carrier->checkErrors($pickupPoints);
        if ($errors == 'Cannot parse null string' || empty($pickupPoints)) {
            $codeLocality = $carrier->getCodeLocality($countryId, $postcode)[0]['code'];
            $listPostcode = $carrier->getListPostalCodesOfCity($codeLocality)['postal_codes'];
            if (count($listPostcode))
                foreach ($listPostcode as $postalCode) {
                    $pickupPoints = $carrier->getListOfficesForAdmin($postalCode, $countryId);
                    $errors = $carrier->checkErrors($pickupPoints);
                    if (!$errors && !empty($pickupPoints)) {
                        break;
                    }
                }
        }

        if ($errors) {
            $this->logger->error($errors);
            $response = [
                "message" => $errors,
                'status' => 'error'
            ];
        } else {
            $response = [
                "pickup_points" => $pickupPoints,
                "status" => 'success'
            ];
        }

        return $this->getResponse()->representJson(
            $this->_objectManager->get(Data::class)->jsonEncode($response)
        );
    }
}
