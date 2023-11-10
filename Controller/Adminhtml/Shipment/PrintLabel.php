<?php

namespace Shellpea\CDEK\Controller\Adminhtml\Shipment;

use Psr\Log\LoggerInterface;
use Magento\Backend\App\Action;
use Shellpea\CDEK\Model\Carrier;
use Shellpea\CDEK\Model\CarrierFactory;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Shipping\Controller\Adminhtml\Order\ShipmentLoader;
use Magento\Shipping\Model\Shipping\LabelGenerator as LabelGeneratorAlias;

class PrintLabel extends Action
{
    /**
     * Authorization level of a basic admin session
     *
     * @see _isAllowed()
     */
    const ADMIN_RESOURCE = 'Magento_Sales::shipment';

    /**
     * @var Carrier
     */
    protected $cdekCarrier;

    /**
     * @var FileFactory
     */
    protected $_fileFactory;

    /**
     * @var ShipmentLoader
     */
    protected $shipmentLoader;

    /**
     * @var LabelGeneratorAlias
     */
    protected $labelGenerator;

    /**
     * @param Action\Context $context
     * @param FileFactory $fileFactory
     * @param CarrierFactory $cdekCarrier
     * @param ShipmentLoader $shipmentLoader
     * @param LabelGeneratorAlias $labelGenerator
     */
    public function __construct(
        Action\Context $context,
        FileFactory $fileFactory,
        CarrierFactory $cdekCarrier,
        ShipmentLoader $shipmentLoader,
        LabelGeneratorAlias $labelGenerator,
    ) {
        $this->cdekCarrier = $cdekCarrier->create();
        $this->_fileFactory = $fileFactory;
        $this->shipmentLoader = $shipmentLoader;
        $this->labelGenerator = $labelGenerator;
        parent::__construct($context);
    }

    /**
     * Print label for one specific shipment
     *
     * @return ResponseInterface|void
     */
    public function execute()
    {
        try {
            $this->shipmentLoader->setOrderId($this->getRequest()->getParam('order_id'));
            $this->shipmentLoader->setShipmentId($this->getRequest()->getParam('shipment_id'));
            $this->shipmentLoader->setShipment($this->getRequest()->getParam('shipment'));
            $this->shipmentLoader->setTracking($this->getRequest()->getParam('tracking'));
            $shipment = $this->shipmentLoader->load();

            $carrier = $this->cdekCarrier;
            $carrier->clientAuthorization();
            $trackId = $this->getRequest()->getParam('track_id');
            $trackNumber = $shipment->getTrackById($trackId);
            $barcodeUuid = $this->getRequest()->getParam('barcode_uuid');
            $orderUuid = $this->getRequest()->getParam('order_uuid');
            $this->_objectManager->get(LoggerInterface::class)
                ->info(__('$orderUuid = ' . $orderUuid));
            $url = '';
            $errors = '';
            if ($barcodeUuid) {
                $receivingBarcode = $carrier->receivingBarcodeCPForOrder($barcodeUuid);
                $errors = $carrier->checkErrors($receivingBarcode);
                $this->_objectManager->get(LoggerInterface::class)
                    ->info(__('@ Errors ' . json_encode($errors)));
                if (!$errors || isset($receivingBarcode['entity']['url'])) {
                    $url = $receivingBarcode['entity']['url'];
                }
            }
            if (!$url && $orderUuid) {
                $orderDetails = $carrier->getOrderDetailsByUuid($orderUuid);
                $errors = $carrier->checkErrors($orderDetails);
                $this->_objectManager->get(LoggerInterface::class)
                    ->info(__('@@ Errors : ' . json_encode($errors)));
                if (!$errors) {
                    $barcodeCPF = $carrier->receivingBarcodeCPForOrder($orderDetails['related_entities'][0]['uuid']);
                    $errors = $carrier->checkErrors($barcodeCPF);
                    $this->_objectManager->get(LoggerInterface::class)
                        ->info(__('@@@ Errors : ' . json_encode($errors)));
                    $this->_objectManager->get(LoggerInterface::class)
                        ->info(__('set $barcodeCPF = ' . $orderDetails['related_entities'][0]['uuid']));
                    if (isset($barcodeCPF['entity']['url'])) {
                        $url = $barcodeCPF['entity']['url'];
                    }
                }
            }
            if ($errors) {
                throw new LocalizedException(__('CDEK error: ' . $errors . ' <br>Please create a new Shipping Label.'));
            }
            if (!$url) {
                throw new \Exception(__('The Url for downloading PDF from CDEK was not generated.'));
            }
            $url = substr(strrchr($url, '/'), 1);
            $content = $carrier->getContentCPForOrder($url);
            $outputPdf = $this->labelGenerator->combineLabelsPdf([$content]);
            $shipment->setShippingLabel($outputPdf->render())->save();
            $trackNumber->setLabelGenerated(false)->save();
            $this->_objectManager->get(LoggerInterface::class)
                ->info(__('Create Shipping Label ' . $trackNumber->getTrackNumber() . ' for shipment ' . $shipment->getId()));

            $labelContent = $shipment->getShippingLabel();
            if ($labelContent) {
                if (stripos($labelContent, '%PDF-') !== false) {
                    $pdfContent = $labelContent;
                } else {
                    $pdf = new \Zend_Pdf();
                    $page = $this->labelGenerator->createPdfPageFromImageString($labelContent);
                    if (!$page) {
                        $this->messageManager->addError(
                            __(
                                'We don\'t recognize or support the file extension in this shipment: %1.',
                                $shipment->getIncrementId()
                            )
                        );
                    }
                    $pdf->pages[] = $page;
                    $pdfContent = $pdf->render();
                }
                $this->messageManager->addError(__('An error occurred while creating shipping label.'));

                return $this->_fileFactory->create(
                    'ShippingLabel(' . $shipment->getIncrementId() . ').pdf',
                    $pdfContent,
                    DirectoryList::VAR_DIR,
                    'application/pdf'
                );
            }
        } catch (LocalizedException $e) {
            $this->messageManager->addError($e->getMessage());
        } catch (\Exception $e) {
            $this->_objectManager->get(LoggerInterface::class)->critical($e);
            $this->messageManager->addError(__('An error occurred while creating shipping label.'));
        }
        $this->_redirect(
            'adminhtml/order_shipment/view',
            ['shipment_id' => $this->getRequest()->getParam('shipment_id')]
        );
    }
}
