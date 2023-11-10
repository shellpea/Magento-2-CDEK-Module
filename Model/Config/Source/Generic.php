<?php

namespace Shellpea\CDEK\Model\Config\Source;

use Shellpea\CDEK\Model\Carrier;

class Generic implements \Magento\Framework\Data\OptionSourceInterface
{
    /**
     * @var Carrier
     */
    protected $_shippingCdek;

    /**
     * Carrier code
     *
     * @var string
     */
    protected $_code = '';

    /**
     * @param Carrier $shippingCdek
     */
    public function __construct(
        Carrier $shippingCdek
    ) {
        $this->_shippingCdek = $shippingCdek;
    }

    /**
     * Returns array to be used in multiselect on back-end
     *
     * @return array
     */
    public function toOptionArray()
    {
        $configData = $this->_shippingCdek->getCode($this->_code);
        $arr = [];
        if ($configData) {
            $arr = array_map(
                function ($code, $data) {
                    return [
                        'value' => $code,
                        'label' => is_array($data) ? $data['title'] : $data
                    ];
                },
                array_keys($configData),
                $configData
            );
        }

        return $arr;
    }
}
