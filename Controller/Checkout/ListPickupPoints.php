<?php

namespace Shellpea\CDEK\Controller\Checkout;

use Shellpea\CDEK\Model\CarrierFactory;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;

class ListPickupPoints extends Action
{
    private $carrier;

    public function __construct(
        CarrierFactory $carrier,
        Context $context
    ) {
        $this->carrier = $carrier->create();
        parent::__construct($context);
    }

    public function execute()
    {
        $postCode = $this->getRequest()->getParam('postalCode');
        $type = $this->getRequest()->getParam('type');
        $countryId = $this->getRequest()->getParam('countryId');
        $this->carrier->clientAuthorization();
        $listOffices = $this->carrier->getListOffices($postCode, true, $type);

        /** @var \Magento\Framework\Controller\Result\Json $resultJson */
        $resultJson = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $errors = $this->carrier->checkErrors($listOffices);
        if ($errors) {
            if ($errors == 'Cannot parse null string' || empty($listOffices)) {
                $codeLocality = $this->carrier->getCodeLocality($countryId, $postCode)[0]['code'];
                $listPostcode = $this->carrier->getListPostalCodesOfCity($codeLocality)['postal_codes'];
                if (count($listPostcode))
                    foreach ($listPostcode as $postalCode) {
                        $listOffices = $this->carrier->getListOffices($postalCode, true, $type);
                        $errors = $this->carrier->checkErrors($listOffices);
                        if (!$errors && !empty($listOffices)) {
                            break;
                        }
                    }
            }

            if ($errors) {
                $resultJson->setData([
                    "message" => $errors,
                    'status' => 'error'
                ]);

                return $resultJson;
            }
        }

        if (empty($listOffices)) {
            $countryId = $this->getRequest()->getParam('countryId');
            $listOffices = $this->carrier->getListOffices($countryId, false, $type);
            $errors = $this->carrier->checkErrors($listOffices);
            if ($errors || empty($listOffices)) {
                $resultJson->setData([
                    "message" => empty($errors) ? 'No pick-up points available' : $errors,
                    'status' => 'error'
                ]);

                return $resultJson;
            }
        }

        $pickupPoints = [];
        foreach ($listOffices as $office) {
            $pickupPoints[] = [
                'code' => $office['code'],
                'name' => $office['name'] . ' (' . $office['location']['address_full'] . ')',
                'coordinates' => [$office['location']['longitude'], $office['location']['latitude']],
                'location' => $office['location'],
                'latitude' => $office['location']['latitude'],
                'longitude' => $office['location']['longitude'],
                'fullAddress' => $office['location']['address_full'],
                'workTime' => $office['work_time'],
                'phone' => $office['phones'][0]['number'],
                'type' => $office['type'],
                'cash' => $office['have_cash'],
                'cashless' => $office['have_cashless'],

            ];
        }

        $resultJson->setData([
            "pickup_points" => $pickupPoints,
            "status" => 'success'
        ]);

        return $resultJson;
    }
}
