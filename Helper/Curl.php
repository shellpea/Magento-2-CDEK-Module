<?php

namespace Shellpea\CDEK\Helper;

class Curl extends \Magento\Framework\HTTP\Client\Curl
{
    public function delete($uri)
    {
        $this->makeRequest("DELETE", $uri);
    }
}
