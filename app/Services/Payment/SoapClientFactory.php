<?php

namespace App\Services\Payment;

use SoapClient;
class SoapClientFactory
{
    public function create(string $url): SoapClient
    {
        return new SoapClient($url, ['cache_wsdl' => WSDL_CACHE_NONE]);
    }
}
