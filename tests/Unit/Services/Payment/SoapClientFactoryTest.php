<?php

declare(strict_types=1);

use App\Services\Payment\SoapClientFactory;

it('creates a SoapClient instance from a local WSDL file', function (): void {
    $wsdlContent = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<definitions name="TestService"
    targetNamespace="urn:TestService"
    xmlns:tns="urn:TestService"
    xmlns:soap="http://schemas.xmlsoap.org/wsdl/soap/"
    xmlns:xsd="http://www.w3.org/2001/XMLSchema"
    xmlns="http://schemas.xmlsoap.org/wsdl/">
    <message name="TestRequest" />
    <message name="TestResponse" />
    <portType name="TestPortType">
        <operation name="TestOperation">
            <input message="tns:TestRequest" />
            <output message="tns:TestResponse" />
        </operation>
    </portType>
    <binding name="TestBinding" type="tns:TestPortType">
        <soap:binding style="document" transport="http://schemas.xmlsoap.org/soap/http" />
        <operation name="TestOperation">
            <soap:operation soapAction="urn:TestAction" />
            <input>
                <soap:body use="literal" />
            </input>
            <output>
                <soap:body use="literal" />
            </output>
        </operation>
    </binding>
    <service name="TestService">
        <port name="TestPort" binding="tns:TestBinding">
            <soap:address location="http://localhost/test" />
        </port>
    </service>
</definitions>
XML;

    $tempPath = tempnam(sys_get_temp_dir(), 'wsdl_test_');
    $wsdlPath = $tempPath.'.wsdl';
    rename($tempPath, $wsdlPath);
    file_put_contents($wsdlPath, $wsdlContent);

    $factory = new SoapClientFactory();

    try {
        $client = $factory->create('file://'.$wsdlPath);
        expect($client)->toBeInstanceOf(SoapClient::class);
    } finally {
        @unlink($wsdlPath);
    }
});
