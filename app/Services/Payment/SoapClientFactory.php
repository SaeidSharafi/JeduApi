<?php

declare(strict_types=1);

namespace App\Services\Payment;

use SoapClient;

final class SoapClientFactory
{
    public function create(string $url): SoapClient
    {
        return new SoapClient($url, ['cache_wsdl' => WSDL_CACHE_NONE]);
    }
}
