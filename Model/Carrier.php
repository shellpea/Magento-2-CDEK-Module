<?php

namespace Shellpea\CDEK\Model;

use Shellpea\CDEK\Helper\Curl as HelperCurl;
use Magento\Framework\Math\Random;
use Magento\Directory\Helper\Data;
use Magento\Framework\Xml\Security;
use Magento\Framework\UrlInterface;
use Magento\Shipping\Helper\Carrier as CarrierHelper;
use Magento\Framework\Measure\Weight;
use Magento\Framework\Measure\Length;
use Magento\Sales\Model\OrderFactory;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Catalog\Model\ProductFactory;
use Magento\Shipping\Model\Order\TrackFactory;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\HTTP\AsyncClient\Request;
use Magento\Framework\Message\ManagerInterface;
use Magento\Directory\Model\PriceCurrencyFactory;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Framework\Exception\LocalizedException;
use Magento\Shipping\Model\Carrier\CarrierInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Shipping\Model\Carrier\AbstractCarrierOnline;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Config\Model\ResourceModel\Config\Data\CollectionFactory;
use Magento\Sales\Model\ResourceModel\Order\Shipment\CollectionFactory as ShipmentCollection;

class Carrier extends AbstractCarrierOnline implements CarrierInterface
{
    /**
     * @var string
     */
    protected $_code = 'cdek';

    protected $order;

    protected $weight;

    protected $random;

    protected $_errors;

    /**
     * @var bool
     */
    protected $_isFixed = true;

    protected $dateTime;

    private $helperCurl;

    /**
     * @var
     */
    private $accessToken;

    /**
     * @var Curl
     */
    protected $curlClient;

    protected $orderFactory;

    /**
     * @var WriterInterface
     */
    protected $configWriter;

    protected $_rateFactory;

    protected $trackFactory;

    protected $productFactory;
    /**
     * @var StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * @var string
     */
    protected $webservicesUrl;

    protected $carrierHelper;

    protected $priceCurrency;

    protected $messageManager;

    protected $listDeliveryMode;

    protected $shipmentCollection;

    protected $_rateMethodFactory;

    /**
     * @var Collection
     */
    protected $configCollection;

    protected $currencies = ['RUB' => 1, 'USD' => 3, 'EUR' => 4];

    protected $tariffCodes = [136, 137, 138, 139, 231, 232, 233, 234, 366, 368, 378];

    private $eurasianCustomsUnion = ['RU', 'KZ', 'AM', 'BY', 'KG'];

    public const CARRIER_CODE = 'cdek';

    private const ORDERS_PATH = 'orders';


    private const WEBHOOKS_PATH = 'webhooks';

    private const MAX_ALLOWABLE_WEIGHT= '30000';

    private const GRANT_TYPE = 'client_credentials';

    public const TEST_MODE_PATH = 'carriers/cdek/test_mode';

    private const PICKUP_POINT_PATH = 'pickup_point';

    private const ACCESS_TOKEN_PATH = 'shellpea/cdek/access_token';

    private const AUTHORIZATION_PATH = 'oauth/token';

    public const WEBHOOKS_UUID_PATH = 'shellpea/cdek/webhooks_uuid';

    public const WEBHOOKS_ENABLE_PATH = 'webhooks_enable';

    private const PRINT_BARCODES_PATH = 'print/barcodes';

    private const LIST_OF_CITIES_PATH = 'location/cities';

    private const TEST_ACCESS_TOKEN_PATH = 'shellpea/cdek/test_access_token';

    public const LIST_OF_PICKUP_POINTS_PATH = 'deliverypoints';

    public const LIST_POSTAL_CODES_OF_CITY = 'location/postalcodes';

    public const TARIFF_CODES_TO_PICKUP_POINT = [136, 138, 232, 234, 366, 368, 378];

    public const TARIFF_CODES_FROM_PICKUP_POINT = [136, 137, 233, 234, 368, 378];

    /**
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory $rateErrorFactory
     * @param \Psr\Log\LoggerInterface $logger
     * @param Security $xmlSecurity
     * @param \Magento\Shipping\Model\Simplexml\ElementFactory $xmlElFactory
     * @param \Magento\Shipping\Model\Rate\ResultFactory $rateFactory
     * @param \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory $rateMethodFactory
     * @param \Magento\Shipping\Model\Tracking\ResultFactory $trackFactory
     * @param \Magento\Shipping\Model\Tracking\Result\ErrorFactory $trackErrorFactory
     * @param \Magento\Shipping\Model\Tracking\Result\StatusFactory $trackStatusFactory
     * @param \Magento\Directory\Model\RegionFactory $regionFactory
     * @param \Magento\Directory\Model\CountryFactory $countryFactory
     * @param \Magento\Directory\Model\CurrencyFactory $currencyFactory
     * @param Data $directoryData
     * @param \Magento\CatalogInventory\Api\StockRegistryInterface $stockRegistry
     * @param ShipmentCollection $shipmentCollection
     * @param CollectionFactory $configCollection
     * @param StoreManagerInterface $storeManager
     * @param PriceCurrencyFactory $priceCurrency
     * @param ManagerInterface $messageManager
     * @param ProductFactory $productFactory
     * @param WriterInterface $configWriter
     * @param TrackFactory $trackingFactory
     * @param CarrierHelper $carrierHelper
     * @param OrderFactory $orderFactory
     * @param HelperCurl $helperCurl
     * @param DateTime $dateTime
     * @param Random $random
     * @param Curl $curl
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory $rateErrorFactory,
        \Psr\Log\LoggerInterface $logger,
        Security $xmlSecurity,
        \Magento\Shipping\Model\Simplexml\ElementFactory $xmlElFactory,
        \Magento\Shipping\Model\Rate\ResultFactory $rateFactory,
        \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory $rateMethodFactory,
        \Magento\Shipping\Model\Tracking\ResultFactory $trackFactory,
        \Magento\Shipping\Model\Tracking\Result\ErrorFactory $trackErrorFactory,
        \Magento\Shipping\Model\Tracking\Result\StatusFactory $trackStatusFactory,
        \Magento\Directory\Model\RegionFactory $regionFactory,
        \Magento\Directory\Model\CountryFactory $countryFactory,
        \Magento\Directory\Model\CurrencyFactory $currencyFactory,
        \Magento\Directory\Helper\Data $directoryData,
        \Magento\CatalogInventory\Api\StockRegistryInterface $stockRegistry,
        ShipmentCollection $shipmentCollection,
        CollectionFactory $configCollection,
        StoreManagerInterface $storeManager,
        PriceCurrencyFactory $priceCurrency,
        ManagerInterface $messageManager,
        ProductFactory $productFactory,
        WriterInterface $configWriter,
        TrackFactory $trackingFactory,
        CarrierHelper $carrierHelper,
        OrderFactory $orderFactory,
        HelperCurl $helperCurl,
        DateTime $dateTime,
        Random $random,
        Curl $curl,
        array $data = []
    ) {
        $this->random = $random;
        $this->curlClient = $curl;
        $this->dateTime = $dateTime;
        $this->helperCurl = $helperCurl;
        $this->orderFactory = $orderFactory;
        $this->configWriter = $configWriter;
        $this->_storeManager = $storeManager;
        $this->carrierHelper = $carrierHelper;
        $this->priceCurrency = $priceCurrency;
        $this->trackFactory = $trackingFactory;
        $this->messageManager = $messageManager;
        $this->productFactory = $productFactory;
        $this->configCollection = $configCollection;
        $this->shipmentCollection = $shipmentCollection;
        parent::__construct(
            $scopeConfig,
            $rateErrorFactory,
            $logger,
            $xmlSecurity,
            $xmlElFactory,
            $rateFactory,
            $rateMethodFactory,
            $trackFactory,
            $trackErrorFactory,
            $trackStatusFactory,
            $regionFactory,
            $countryFactory,
            $currencyFactory,
            $directoryData,
            $stockRegistry,
            $data
        );
    }

    /**
     * Custom Shipping Rates Collector
     *
     * @param RateRequest $request
     * @return \Magento\Shipping\Model\Rate\Result|bool
     */
    public function collectRates(RateRequest $request)
    {
        $this->_logger->info('test');
        $listDeliveryMode = $this->getConfigData('delivery_to_cdek') ? $this->getConfigData('delivery_mode_from_door')
            : $this->getConfigData('delivery_mode_from_pvz');
        if (!$this->getConfigFlag('active')
            || !$this->checkAllowableWeight($request->getAllItems())
            || !$listDeliveryMode
        ) {
            return false;
        }

        $this->listDeliveryMode = $listDeliveryMode;
        $this->clientAuthorization();
        if ($this->getConfigData('delivery_to_cdek') && !$this->getFromLocation()
            || !$this->getConfigData('delivery_to_cdek') && !$this->getConfigData(self::PICKUP_POINT_PATH)
            || empty($this->getCodeLocality($request->getDestCountryId(), $request->getDestPostcode()))
        ) {
            return false;
        }

        return $this->getRateCollectionByTariff($request);
    }

    /**
     * @return array
     */
    public function getAllowedMethods()
    {
        return [$this->_code => $this->getConfigData('name')];
    }

    public function _getAuthDetails()
    {
        return [
            'grant_type' => self::GRANT_TYPE,
            'client_id' => $this->getConfigData('account'),
            'client_secret' => $this->getConfigData('password')
        ];
    }

    protected function authorizationRequest()
    {
        $url = $this->webservicesUrl . self::AUTHORIZATION_PATH . '?';
        foreach ($this->_getAuthDetails() as $param => $value) {
            $url .= $param . '=' . $value . '&';
        }
        $this->getCurlClient()->post($url, []);

        return json_decode($this->getCurlClient()->getBody(), true);
    }

    protected function sendRequest($path, $type, $params, $pdf = false)
    {
        $url = $this->webservicesUrl . $path;
        if ($type == Request::METHOD_POST) {
            $url .= '?access_token=' .$this->accessToken;
            $this->getCurlClient()->addHeader("Content-Type", "application/json");
            $this->getCurlClient()->post($url, json_encode($params));
        } else if ($type == Request::METHOD_GET) {
            $url .= '?';
            foreach ($params as $param => $value) {
                $url .= $param . '=' . $value . '&';
            }
            $this->getCurlClient()->get($url);
        } else if ($type == Request::METHOD_DELETE) {
            $url .= '?';
            foreach ($params as $param => $value) {
                $url .= $param . '=' . $value . '&';
            }
            $this->helperCurl->delete($url);
            return json_decode($this->helperCurl->getBody(), true);
        }

        if ($pdf) {
            $this->_logger->info('$url = ' . $url);
        }

        return $pdf ? $this->getCurlClient()->getBody()
            : json_decode($this->getCurlClient()->getBody(), true);
    }

    public function getListOffices($value, $byPostalCode, $type = null)
    {
        $label = $byPostalCode ? 'postal_code' : 'country_code';
        $params = [
            'access_token' => $this->accessToken,
            $label => $value,
            'lang' => $this->getConfigData('country_id') == 'RU' ? 'rus' : 'eng',
            'type' => $type ?? 'PVZ',
        ];

        return $this->sendRequest(self::LIST_OF_PICKUP_POINTS_PATH, Request::METHOD_GET, $params);
    }

    public function getListOfficesForAdmin($postalCode, $countryId)
    {
        $params = [
            'access_token' => $this->accessToken,
            'postal_code' => $postalCode,
            'type' => 'PVZ',
            'lang' => $countryId == 'RU' ? 'rus' : 'eng',
            'is_reception' => 1
        ];
        $pickupPoint = $postalCode ? $this->sendRequest(self::LIST_OF_PICKUP_POINTS_PATH, Request::METHOD_GET, $params)
            : '';
        if (($this->checkErrors($pickupPoint) || empty($pickupPoint)) && $countryId && $countryId != 'RU') {
            $params['country_code'] = $countryId;
            unset($params['postal_code']);
            $pickupPoint = $this->sendRequest(self::LIST_OF_PICKUP_POINTS_PATH, Request::METHOD_GET, $params);
        }

        return $pickupPoint;
    }

    public function getOfficeByCode($code)
    {
        $params = [
            'access_token' => $this->accessToken,
            'code' => $code,
            'lang' => $this->getConfigData('country_id') == 'RU' ? 'rus' : 'eng'
        ];

        return $this->sendRequest(self::LIST_OF_PICKUP_POINTS_PATH, Request::METHOD_GET, $params);
    }

    public function getCurlClient()
    {
        return $this->curlClient;
    }

    protected function _doShipmentRequest(\Magento\Framework\DataObject $request)
    {
        $this->_prepareShipmentRequest($request);
        $result = new \Magento\Framework\DataObject();

        $this->clientAuthorization();
        $this->order = $this->orderFactory->create()->load($request->getOrderShipment()->getOrderId());
        $cdekOrder = $this->orderRegistration($request);
        $errors = $this->checkErrors($cdekOrder);
        if (!empty($errors)) {
            $result->setErrors($errors);
        } else {
            $orderDetails = $this->getOrderDetailsByUuid($cdekOrder['entity']['uuid']);
            $this->_logger->info(json_encode($orderDetails));
            $errors = $this->checkErrors($orderDetails);
            if (!empty($errors)) {
                $result->setErrors($errors);
            } else {
                $trackNumber = $orderDetails['entity']['cdek_number'] ?? $orderDetails['entity']['number'];
                $result->setTrackingNumber($trackNumber);
                $creatingBarcode = $this->createBarcodeCPForOrder($cdekOrder['entity']['uuid']);
                if (!$this->checkErrors($creatingBarcode)) {
                    $result->setBarcodeUuid($creatingBarcode['entity']['uuid']);
                    $result->setOrderUuid($orderDetails['entity']['uuid']);
                } else {
                    $result->setErrors($errors);
                }
            }
        }

        return $result;
    }

    public function getConfigValue($path, $scopeType = null, $scopeCode = null)
    {
        return $scopeType && $scopeCode ? $this->_scopeConfig->getValue($path, $scopeType, $scopeCode)
            :$this->_scopeConfig->getValue($path);
    }

    public function setConfigValue($path, $value)
    {
        $this->configWriter->save($path, $value);
    }

    public function getCodeLocality($countryCode, $postalCode)
    {
        $params = [
            'access_token' => $this->accessToken,
            'country_codes' => $countryCode,
            'postal_code' => $postalCode
        ];
        return $this->sendRequest(self::LIST_OF_CITIES_PATH, Request::METHOD_GET, $params);
    }

    private function getTariffByCode($toLocation, $fromLocation, $items, $tariffCode)
    {
        $this->_logger->info('$tariffCode = ' . $tariffCode);
        $body = $this->getDataForCalculationTariffs($items);
        $body = array_merge($body, $toLocation, $fromLocation);
        $parameter = 0;
        foreach ($items as $item) {
            if (!array_key_exists($this->_storeManager->getStore()->getBaseCurrencyCode(), $this->currencies)) {
                $parameter += $this->getPriceInRubles($item) * $item->getQty();
            } else {
                $parameter += $item->getBasePrice() * $item->getQty();
            }
        }

        $services = [
            'code' => 'INSURANCE',
            'parameter' => $parameter
        ];

        $body['services'] = $services;
        $body['tariff_code'] = $tariffCode;

        $this->_logger->info('Calculation by tariff code ' . $tariffCode . '. Body: ' . json_encode($body));
        return $this->calculationByTariffCode($body);
    }

    private function calculationByTariffCode($body)
    {
        return $this->sendRequest('calculator/tariff', Request::METHOD_POST, $body);
    }

    private function getDataForCalculationTariffs($items)
    {
        $packageType = $this->getConfigData('packaging');
        if ($packageType == 'YOUR_PACKAGING' || !$packageType) {
            $length = $this->getConfigData('package_length');
            $width = $this->getConfigData('package_width');
            $height = $this->getConfigData('package_height');
        } else {
            $size = $this->getCode('packaging', $packageType);
            $length = $size['length'];
            $width = $size['width'];
            $height = $size['height'];
        }
        $packages = [];
        $weightUnit = $this->getWeightUnit();
        foreach ($items as $item) {
            if ($item->getRealProductType() != 'simple' || count($item->getChildren())) {
                continue;
            }
            $product = $this->productFactory->create()->load($item->getProduct()->getId());
            $dimensions = [
                floor($product->getLengthForCdek() ?: $length),
                floor($product->getWidthForCdek() ?: $width),
                floor($product->getHeightForCdek() ?: $height)
            ];
            sort($dimensions);
            $packages[] = [
                'weight' => $this->getWeightInGram($product->getWeight() * $item->getQty(), $weightUnit),
                'length' => $dimensions[0] * $item->getQty(),
                'width' => $dimensions[1],
                'height' => $dimensions[2]
            ];
        }

        return [
            'currency' => array_key_exists($this->_storeManager->getStore()->getBaseCurrencyCode(), $this->currencies) ?
                $this->currencies[$this->_storeManager->getStore()->getBaseCurrencyCode()] : 1,
            'lang' => $this->_storeManager->getStore()->getCurrentCurrencyCode() == 'RUB' ? 'rus' : 'eng',
            'type' => 1,
            'packages' => $packages
        ];
    }

    private function getAvailableTariffs($toLocation, $fromLocation, $items)
    {
        $body = $this->getDataForCalculationTariffs($items);
        return $this->calculationByAvailableTariffs(array_merge($body, $toLocation, $fromLocation));
    }

    private function calculationByAvailableTariffs($body)
    {
        return $this->sendRequest('calculator/tarifflist', Request::METHOD_POST, $body);
    }

    private function getTotalWeight($items)
    {
        $totalWeight = 0;
        foreach ($items as $item) {
            $totalWeight += $item->getRowWeight();
        }

        return $totalWeight;
    }

    private function getFromLocation()
    {
        $fromCountryCode = $this->getConfigData('country_id');
        $fromPostalCode = $this->getConfigData('postcode');
        $fromCity = $this->getConfigData('city');
        $fromAddress = $this->getConfigData('street_line2') ? $this->getConfigData('street_line1') . ', '
            . $this->getConfigData('street_line2') : $this->getConfigData('street_line1');
        $fromCodeLocality = $this->getCodeLocality($fromCountryCode, $fromPostalCode);

        $errors = $this->checkErrors($fromCodeLocality);
        if ($errors) {
            $this->_logger->error($errors);
            return false;
        }

        if (!$fromCountryCode || !$fromPostalCode || !$fromCity || !$fromAddress || !$fromCodeLocality) {
            return false;
        }

        return [
            'from_location' => [
                'code' => $fromCodeLocality[0]['code'],
                'postal_code' => $fromPostalCode,
                'country_code' => $fromCountryCode,
                'city' => $fromCity,
                'address' => $fromAddress
            ]
        ];
    }

    private function getOfficeLocation($office, $to = false)
    {
        $location = $to ? 'to_location' : 'from_location';

        return [
            $location => [
                'code' => $office['location']['city_code'],
                'postal_code' => $office['location']['postal_code'],
                'country_code' => $office['location']['country_code'],
                'city' => $office['location']['city'] ?? '',
                'address' => $office['location']['address']
            ]
        ];
    }

    private function  orderRegistration($request)
    {
        $number = $this->order->getIncrementId() . '-' . $this->random->getRandomString(5);
        $packages = $this->getPackages($request, $number);

        $tariff = $request->getShippingMethod();
        $body = [
            'type' => 1,
            'number' => $number,
            'tariff_code' => $tariff,
            'developer_key' => 'rrM?8nCF^~iXO&Pntv3Cd22HG%62uE)6',
            'recipient' => [
                'name' => $request->getRecipientContactPersonName(),
                'email' => $request->getRecipientEmail(),
                'phones' => [
                    'number' => $request->getRecipientContactPhoneNumber()
                ]
            ],
            'packages' => $packages,
            'print' => 'barcode'
        ];

        if (in_array($tariff, self::TARIFF_CODES_FROM_PICKUP_POINT)) {
            $body['shipment_point'] = $this->getConfigData(self::PICKUP_POINT_PATH);
        } else {
            if (!$this->getFromLocation()) {
                return ['error' => __('Shipping labels can\'t be created. Verify that the CDEK Delivery information and settings are complete and try again.')];
            }
            $body = array_merge($body, $this->getFromLocation());
        }

        $postalCode = $request->getRecipientAddressPostalCode();
        if (in_array($tariff, self::TARIFF_CODES_TO_PICKUP_POINT)) {
            $pickupPoint = $this->order->getShippingAddress()->getPickupPoint();
            $body['delivery_point'] = $pickupPoint ?: $this->getListOffices($postalCode, true)[0]['code'];
        } else {
            $countryCode = $request->getRecipientAddressCountryCode();
            $body['to_location'] = [
                'code' => $this->getCodeLocality($countryCode, $postalCode)[0]['code'],
                'postal_code' => $postalCode,
                'country_code' => $countryCode,
                'city' => $request->getRecipientAddressCity(),
                'address' => $request->getRecipientAddressStreet()
            ];
        }

        if ($this->isInternationalOrder($request)) {
            $dateInvoice = $this->order->getTotalPaid()
                ? date('Y-m-d', strtotime($this->order->getInvoiceCollection()->getFirstItem()->getCreatedAt()))
                : $this->dateTime->gmtDate();
            $body['date_invoice'] = $dateInvoice;
            $body['shipper_name'] = $request->getShipperContactPersonName();
            $body['shipper_address'] = $this->getShipperAddress($request);
            $body['seller'] = [
                'name' => $request->getShipperContactPersonName(),
                'address' => $this->getShipperAddress($request)
            ];
        }

        return $this->requestForOrderRegistration($body);
    }

    public function requestForOrderRegistration($body)
    {
        return $this->sendRequest(self::ORDERS_PATH, Request::METHOD_POST, $body);
    }

    public function clientAuthorization($testMode = true)
    {
        $this->webservicesUrl = !$this->getConfigData('test_mode') || !$testMode
            ? $this->getConfigData('production_webservices_url')
            : $this->getConfigData('test_webservices_url');

        $accessTokenPath = !$this->getConfigData('test_mode') || !$testMode ? self::ACCESS_TOKEN_PATH
            : self::TEST_ACCESS_TOKEN_PATH;
        $accessTokenData = $this->getConfigDataByPath($accessTokenPath);
        if ($accessTokenData->getId() && $accessTokenData->getValue() && (time() - strtotime($accessTokenData->getUpdatedAt())) < 3000) {
            $accessToken = $accessTokenData->getValue();
        } else {
            $authorization = $this->authorizationRequest();
            $accessToken = $authorization['access_token'] ?? '';
            $this->setConfigValue($accessTokenPath, $accessToken);
        }
        $this->accessToken = $accessToken;
    }

    public function getOrderDetailsByUuid($uuid)
    {
        return $this->requestForOrderDetails($uuid);
    }

    private function requestForOrderDetails($uuid)
    {
        $params = [
            'access_token' => $this->accessToken,
        ];
        return $this->sendRequest(self::ORDERS_PATH . '/' . $uuid, Request::METHOD_GET, $params);
    }

    protected function getWeightInGram($weight, $configWeightUnit)
    {
        $weight = $this->carrierHelper->convertMeasureWeight(
            (float)$weight,
            $configWeightUnit,
            Weight::GRAM
        );

        return sprintf('%.0f', $weight);
    }

    protected function getLengthInCM($length, $configLengthUnit)
    {
        $length = $this->carrierHelper->convertMeasureDimension(
            (float)$length,
            $configLengthUnit,
            Length::CENTIMETER
        );

        return sprintf('%.0f', $length);
    }

    public function checkErrors($response)
    {
        $errors = [];
        if (isset($response['errors'])) {
            foreach ($response['errors'] as $error) {
                $errors[] = $error['message'];
            }
            return implode(', ', $errors);
        } else if (isset($response['requests'][0]['errors'])) {
            foreach ($response['requests'][0]['errors'] as $error) {
                $errors[] = $error['message'];
            }
            return implode(', ', $errors);
        } else if (isset($response['error'])) {
            $errors = $response['error'];
        } else if (isset($response['requests'][0]['state']) && $response['requests'][0]['state'] == 'INVALID') {
            $errors = 'Incorrect order';
        }

        return $errors;
    }

    public function createBarcodeCPForOrder($uuid)
    {
        $body = [
            'orders' => [
                'order_uuid' => $uuid,
            ],
            'copy_count' => $this->getConfigData('copy_count') ?? 1,
            'format' => $this->getConfigData('barcode_format') ?? 'A4'
        ];

        return $this->requestCreateBarcodeCPForOrder($body);
    }

    private function requestCreateBarcodeCPForOrder($body)
    {
        return $this->sendRequest(self::PRINT_BARCODES_PATH, Request::METHOD_POST, $body);
    }

    public function receivingBarcodeCPForOrder($uuid)
    {
        $params = [
            'access_token' => $this->accessToken,
        ];

        return $this->sendRequest(self::PRINT_BARCODES_PATH . '/' . $uuid, Request::METHOD_GET, $params);
    }

    public function getContentCPForOrder($url)
    {
        $params = [
            'access_token' => $this->accessToken,
        ];

        return $this->sendRequest(self::PRINT_BARCODES_PATH . '/' . $url, Request::METHOD_GET, $params, true);
    }

    public function getTracking($trackingNumber)
    {
        $result = $this->_trackFactory->create();
        $carrierTitle = $this->getConfigData('title');
        $tracking = $this->_trackStatusFactory->create();
        $tracking->setCarrier($this->_code);
        $tracking->setCarrierTitle($carrierTitle);
        $trackNumber = $this->trackFactory->create()->getCollection()
            ->addFieldToFilter('track_number', $trackingNumber)->getFirstItem();
        $uuid = $trackNumber->getOrderUuid();
        if ($uuid && !$trackNumber->getCdekNumber()) {
            $this->clientAuthorization();
            $orderDetail = $this->getOrderDetailsByUuid($uuid);
            if (isset($orderDetail['entity']['cdek_number'])) {
                $tracking->setUrl('https://www.cdek.ru/ru/tracking?order_id=' . $orderDetail['entity']['cdek_number']);
                $trackNumber->setCdekNumber($orderDetail['entity']['cdek_number'])->save();
            } else {
                $weight = 0;
                foreach ($orderDetail['entity']['packages'] as $package) {
                    $weight += $package['weight'];
                }
                $tracking->setWeight($weight);
                $tracking->setStatus(end($orderDetail['entity']['statuses'])['name']);
            }
        } else if ($uuid) {
            $tracking->setUrl('https://www.cdek.ru/ru/tracking?order_id=' . $trackNumber->getCdekNumber());
        } else {
            $tracking->setUrl('https://www.cdek.ru/ru/tracking?order_id=' . $trackingNumber);
        }

        $tracking->setTracking($trackingNumber);
        $result->append($tracking);

        return $result;
    }

    public function convertPriceFromRubles($price)
    {
        return round($price / $this->priceCurrency->create()->getCurrency()->getRate('RUB'), 2);
    }

    public function convertPriceToRubles($price, $storeId)
    {
        return $this->priceCurrency->create()
            ->convert($price, $storeId, 'RUB');
    }

    public function getPriceInRubles($item)
    {
        if ($this->_storeManager->getStore()->getBaseCurrencyCode() == 'RUB') {
            return $item->getBasePrice();
        } elseif ($this->_storeManager->getStore()->getCurrentCurrencyCode() == 'RUB') {
            return $item->getPrice();
        } else {
            return $this->priceCurrency->create()
                ->convert($item->getPrice(), $this->_storeManager->getStore()->getStoreId(), 'RUB');
        }
    }

    protected function getPackages($request, $number)
    {
        $packages = [];
        foreach ($request->getPackages() as $key => $package) {
            $items = [];
            $packageParams = $package['params'];
            $dimensionUnits = $packageParams['dimension_units'];
            foreach ($package['items'] as $packageItem) {
                $shipmentItem = '';
                foreach ($request->getOrderShipment()->getItems() as $item) {
                    if ($item->getOrderItemId() == $packageItem['order_item_id']) {
                        $shipmentItem = $item;
                        break;
                    }
                }

                $weight = $this->getWeightInGram($shipmentItem->getWeight(), $this->getWeightUnit());
                $order = $this->orderFactory->create()->load($request->getOrderShipment()->getOrderId());
                $price = $order->getOrderCurrencyCode() == 'RUB' ? $shipmentItem->getPrice()
                    : $this->convertPriceToRubles($shipmentItem->getPrice(), $this->getStore());

                $this->_logger->info('base price = ' . $shipmentItem->getPrice());
                $this->_logger->info('convertPriceToRubles = ' . $this->convertPriceToRubles($shipmentItem->getPrice(), $this->getStore()));
                $productItem = [
                    'name' => $packageItem['name'],
                    'ware_key' => $shipmentItem->getSku(),
                    'payment' => [
                        'value' => 0,
                        'vat_sum' => 0,
                        'vat_rate' => 0
                    ],
                    'cost' => $price,
                    'weight' => $weight,
                    'amount' => $packageItem['qty']
                ];

                if ($this->isInternationalOrder($request)) {
                    $productItem['weight_gross'] = $weight;
                }
                $items[] = $productItem;
            }

            $packageData = [
                'number' => $number . '-' . $key,
                'weight' => $this->getWeightInGram($packageParams['weight'], $packageParams['weight_units']),
                'items' => $items
            ];

            $packageType = $this->getConfigData('packaging');
            if ($packageType && $packageType != 'YOUR_PACKAGING') {
                $size = $this->getCode('packaging', $packageType);
            }
            if ($packageParams['length']) {
                $packageData['length'] = $dimensionUnits == Length::CENTIMETER ? $packageParams['length']
                    : $this->getLengthInCM($packageParams['length'], $dimensionUnits);
            } else {
                $packageData['length'] = isset($size) ? $size['length'] : $this->getConfigData('package_length');
            }

            if ($packageParams['width']) {
                $packageData['width'] = $dimensionUnits == Length::CENTIMETER ? $packageParams['width']
                    : $this->getLengthInCM($packageParams['width'], $dimensionUnits);
            } else {
                $packageData['width'] = isset($size) ? $size['width'] : $this->getConfigData('package_width');
            }

            if ($packageParams['height']) {
                $packageData['height'] = $dimensionUnits == Length::CENTIMETER ? $packageParams['height']
                    : $this->getLengthInCM($packageParams['height'], $dimensionUnits);
            } else {
                $packageData['height'] = isset($size) ? $size['height'] : $this->getConfigData('package_height');
            }

            $packages[] = $packageData;
        }

        return $packages;
    }

    public function getCode($type, $code = '')
    {
        $codes = [
            'packaging' => [
                'CARTON_BOX_XS' => [
                    'title' => __('Box XS (0.5 kg 17x12x9 cm)'),
                    'weight' => 0.5,
                    'length' => 17,
                    'width' => 12,
                    'height' => 9,
                ],
                'CARTON_BOX_S' => [
                    'title' => __('Box S (2 kg 21x20x11 cm)'),
                    'weight' => 2,
                    'length' => 21,
                    'width' => 20,
                    'height' => 11,
                ],
                'CARTON_BOX_M' => [
                    'title' => __('Box M (5 kg 33x25x15 cm)'),
                    'weight' => 5,
                    'length' => 33,
                    'width' => 25,
                    'height' => 15,
                ],
                'CARTON_BOX_L' => [
                    'title' => __('Box L (12 kg 34x33x26 cm)'),
                    'weight' => 12,
                    'length' => 34,
                    'width' => 33,
                    'height' => 26,
                ],
                'CARTON_BOX_500GR' => [
                    'title' => __('Box (0.5 kg 17x12x10 cm)'),
                    'weight' => 0.5,
                    'length' => 17,
                    'width' => 12,
                    'height' => 10,
                ],
                'CARTON_BOX_1KG' => [
                    'title' => __('Box (1 kg 24x17x10 cm)'),
                    'weight' => 1,
                    'length' => 24,
                    'width' => 17,
                    'height' => 10,
                ],
                'CARTON_BOX_2KG' => [
                    'title' => __('Box (2 kg 34x24x10 cm)'),
                    'weight' => 2,
                    'length' => 34,
                    'width' => 24,
                    'height' => 10,
                ],
                'CARTON_BOX_3KG' => [
                    'title' => __('Box (3 kg 24x24x21 cm)'),
                    'weight' => 3,
                    'length' => 24,
                    'width' => 24,
                    'height' => 21,
                ],
                'CARTON_BOX_5KG' => [
                    'title' => __('Box (5 kg 40x24x21 cm)'),
                    'weight' => 5,
                    'length' => 40,
                    'width' => 24,
                    'height' => 21,
                ],
                'CARTON_BOX_10KG' => [
                    'title' => __('Box (10 kg 40x35x28 cm)'),
                    'weight' => 10,
                    'length' => 40,
                    'width' => 35,
                    'height' => 28,
                ],
                'CARTON_BOX_15KG' => [
                    'title' => __('Box (15 kg 60x35x29 cm)'),
                    'weight' => 15,
                    'length' => 60,
                    'width' => 35,
                    'height' => 29,
                ],
                'CARTON_BOX_20KG' => [
                    'title' => __('Box (20 kg 47x40x43 cm)'),
                    'weight' => 20,
                    'length' => 47,
                    'width' => 40,
                    'height' => 43,
                ],
                'CARTON_BOX_30KG' => [
                    'title' => __('Box (30 kg 69x39x42 cm)'),
                    'weight' => 30,
                    'length' => 69,
                    'width' => 39,
                    'height' => 42,
                ],
                'YOUR_PACKAGING' => [
                    'title' => __('Your Packaging'),
                    'weight' => 0,
                    'length' => 0,
                    'width' => 0,
                    'height' => 0,
                ],
            ],
            'delivery_mode_from_door' => [
                1 => __('Delivery by courier'),
                2 => __('Delivery to the pick-up point'),
                6 => __('Delivery to the parcel terminal'),
            ],
            'delivery_mode_from_pvz' => [
                3 => __('Delivery by courier'),
                4 => __('Delivery to the pick-up point'),
                7 => __('Delivery to the parcel terminal')
            ],
            'delivery_to_cdek' => [
                0 => __('I will bring the parcel to Cdek myself'),
                1 => __('The courier must pick up the parcel'),
            ],
            'barcode_format' => [
                'A4' => 'A4',
                'A5' => 'A5',
                'A6' => 'A6',
                'A7' => 'A7'
            ]
        ];

        if (!isset($codes[$type])) {
            return false;
        } elseif ('' === $code) {
            return $codes[$type];
        }

        if (!isset($codes[$type][$code])) {
            return false;
        } else {
            return $codes[$type][$code];
        }
    }

    public function getWeightUnit()
    {
        return $this->getConfigValue(Data::XML_PATH_WEIGHT_UNIT) == 'lbs' ? Weight::POUND : Weight::KILOGRAM;
    }

    public function checkAllowableWeight($items)
    {
        $totalWeight = $this->getWeightInGram($this->getTotalWeight($items), $this->getWeightUnit());
        if ($totalWeight <= self::MAX_ALLOWABLE_WEIGHT) {
            return true;
        } else {
            return false;
        }
    }

    public function isInternationalOrder($request)
    {
        if ($request->getShipperAddressCountryCode() != $request->getRecipientAddressCountryCode()
            && in_array($request->getShipperAddressCountryCode(), $this->eurasianCustomsUnion)
            && in_array($request->getRecipientAddressCountryCode(), $this->eurasianCustomsUnion)
        ) {
            return true;
        }

        return false;
    }

    /**
     * Do request to shipment
     *
     * @param Request $request
     * @return \Magento\Framework\DataObject
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function requestToShipment($request)
    {
        $packages = $request->getPackages();
        if (!is_array($packages) || !$packages) {
            throw new LocalizedException(__('No packages for request'));
        }
        if ($request->getStoreId() != null) {
            $this->setStore($request->getStoreId());
        }
        $data = [];
        $result = $this->_doShipmentRequest($request);

        if ($result->hasErrors()) {
            $this->rollBack($data);
        } else {
            if ($result->getTrackingNumber()) {
                $data['tracking_number'] = $result->getTrackingNumber();
                $this->_logger->info('Tracking Number ' . $result->getTrackingNumber());
                $data['barcode_uuid'] = $result->getBarcodeUuid();
                $this->_logger->info('Barcode Uuid Is ' . $result->getBarcodeUuid());
                $data['order_uuid'] = $result->getOrderUuid();
                $this->_logger->info('Order Uuid ' . $result->getOrderUuid());
            }
        }
        $request->setMasterTrackingId($result->getTrackingNumber());


        $response = new \Magento\Framework\DataObject(['info' => $data]);
        if ($result->getErrors()) {
            $this->_logger->info('Error ' . $result->getErrors());
            $response->setErrors($result->getErrors());
        }

            return $response;
    }

    public function getRateCollectionByTariff($request)
    {
        $result = $this->_rateFactory->create();
//        $fromLocation = $this->getConfigData('delivery_to_cdek') ? $this->getFromLocation()
//        : $this->getOfficeLocation($this->getOfficeByCode($this->getConfigData(self::PICKUP_POINT_PATH))[0]);
        if ($this->getConfigData('delivery_to_cdek')) {
            $fromLocation = $this->getFromLocation();
        } else {
            $office = $this->getOfficeByCode($this->getConfigData(self::PICKUP_POINT_PATH));
            $errors = $this->checkErrors($office);
            if ($errors) {
                $this->_logger->error($errors);
                return false;
            }
            $fromLocation = $this->getOfficeLocation($office[0]);
        }
        $toLocation = $this->getToLocationFromRequest($request);

        $tariffs = $this->getAvailableTariffs($toLocation, $fromLocation, $request->getAllItems());

        $errors = $this->checkErrors($tariffs);
        if ($errors) {
            $this->_logger->error($errors);
            return false;
        }

        foreach ($tariffs['tariff_codes'] as $tariff) {
            if (!in_array($tariff['delivery_mode'], explode(',', $this->listDeliveryMode))
                || !in_array($tariff['tariff_code'], $this->tariffCodes)
            ) {
                continue;
            }

            if (in_array($tariff['delivery_mode'], [2, 4, 6, 7])) {
                $type = in_array($tariff['delivery_mode'], [2, 4]) ? 'PVZ' : 'POSTAMAT';
                $listOffices = $this->getListOffices($request->getDestPostcode(), true, $type);
                $errors = $this->checkErrors($listOffices);
                if ($errors == 'Cannot parse null string' || empty($listOffices)) {
                    $listPostcode = $this->getListPostalCodesOfCity($toLocation['to_location']['code'])['postal_codes'];
                    if (count($listPostcode))
                    foreach ($listPostcode as $postalCode) {
                        $listOffices = $this->getListOffices($postalCode, true, $type);
                        $errors = $this->checkErrors($listOffices);
                        if (!$errors && !empty($listOffices)) {
                            break;
                        }
                    }
                }
                if ($errors || empty($listOffices)) {
                    continue;
                }
            }

            $method = $this->_rateMethodFactory->create();
            $method->setCarrier($this->_code);
            $method->setCarrierTitle($this->getConfigData('title'));

            $title = '';
            if (in_array($tariff['delivery_mode'], [1, 3])) {
                $title = __('Delivery by Courier');
            } else if (in_array($tariff['delivery_mode'], [2, 4])) {
                $title = __('Delivery to Pick-up Point');
            } else if (in_array($tariff['delivery_mode'], [6, 7])) {
                $title = __('Delivery to Parcel Terminal');
            }

            $maxDays = $tariff['calendar_min'] == $tariff['calendar_max'] ? $tariff['calendar_max']
                : $tariff['calendar_min'] . ' - ' . $tariff['calendar_max'];
            $methodTitle = $title . ' ('. $maxDays . ' ' . __('days') . ')';
            $method->setMethod($tariff['tariff_code']);
            $method->setMethodTitle($methodTitle);

            $tariff = $this->getTariffByCode(
                $toLocation,
                $fromLocation,
                $request->getAllItems(),
                $tariff['tariff_code']
            );

            if ($this->checkErrors($tariff)) {
                continue;
            }

            $shippingCost = !array_key_exists($this->_storeManager->getStore()->getBaseCurrencyCode(), $this->currencies)
                ? $this->convertPriceFromRubles($tariff['total_sum'])
                : $tariff['total_sum'];

            $method->setPrice($shippingCost);
            $method->setCost($shippingCost);

            $result->append($method);
        }

        return $result;
    }

    public function getToLocationFromRequest($request)
    {
        $postalCode = $request->getDestPostcode();
        $countryCode = $request->getDestCountryId();

        return [
            'to_location' => [
                'code' => $this->getCodeLocality($countryCode, $postalCode)[0]['code'],
                'postal_code' => $postalCode,
                'country_code' => $countryCode,
                'city' => $request->getDestCity(),
                'address' => $request->getDestStreet()
            ]
        ];
    }

    public function addWebhookSubscription()
    {
        try
        {
            $url = $this->getWebhookUrl();
            $body = [
                'access_token' => $this->accessToken,
                'type' => 'PRINT_FORM',
                'url' => $url
            ];

            return $this->sendRequest(self::WEBHOOKS_PATH, Request::METHOD_POST, $body);
        } catch (\Exception $e) {
            $this->_logger->error(__("Cannot generate webhooks URL: " . $e->getMessage()));
            return null;
        }
    }

    /**
     * @throws NoSuchEntityException
     */
    public function getWebhookUrl()
    {
        $this->_storeManager->setCurrentStore($this->getStoreId());
        $url = $this->_storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_WEB, true);

        if (empty($url)) {
            throw new \Exception("Please configure a store BASE URL.");
        }

        $url = filter_var($url, FILTER_SANITIZE_URL);
        $url = rtrim(trim($url), "/");
        $url .= '/cdek/webhooks';
        return $url;
    }

    public function deleteWebhookSubscription($uuid)
    {
        $params = [
            'access_token' => $this->accessToken,
        ];
        return $this->sendRequest(self::WEBHOOKS_PATH . '/' . $uuid, Request::METHOD_DELETE, $params);
    }

    public function getShipperAddress($request)
    {
        $address = $this->getCountryName($request->getShipperAddressCountryCode())
            . ', ' . $request->getShipperAddressStateOrProvinceCode()
            . ', ' . $request->shipper_address_postal_code()
            . ', ' . $request->getShipperAddressCity()
            . ', ' . $request->getShipperAddressStreet1();

        return $request->getShipperAddressStreet2() ? $address . ', ' . $request->getShipperAddressStreet2() : $address;
    }

    public function getCountryName($code)
    {
        return $this->_countryFactory->create()
            ->loadByCode($code)
            ->getName() ;
    }

    public function getSellerAddress($request)
    {
        $address = $this->getCountryName($this->getConfigData('country_id'))
            . ', ' . $this->getConfigData('region_id')
            . ', ' . $this->getConfigData('postcode')
            . ', ' . $this->getConfigData('city')
            . ', ' . $this->getConfigData('street_line1');

        return $this->getConfigData('street_line2') ? $address . ', ' . $this->getConfigData('street_line2') : $address;
    }

    public function getConfigDataByPath($path)
    {
        return $this->configCollection->create()->addFieldToFilter('path', $path)->getFirstItem();
    }

    public function getListPostalCodesOfCity($code)
    {
        $params = [
            'access_token' => $this->accessToken,
            'code' => $code
        ];
        return $this->sendRequest(self::LIST_POSTAL_CODES_OF_CITY, Request::METHOD_GET, $params);
    }
}
