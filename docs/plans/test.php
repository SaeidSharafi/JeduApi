<?php

declare(strict_types=1);
/**
 * Debugging & Execution Script for EMS LMS SOAP Client
 *
 * Requirements:
 * - PHP must have the 'soap' extension enabled (php_soap.dll in Windows or php-soap in Linux).
 * - You must define the $token variable.
 */

include_once './config.php';
// 2. Define the Token
// IMPORTANT: Replace 'YOUR_ACTUAL_TOKEN_HERE' with a valid token from your system.
// Without a valid token, the API will likely return an authentication error.
$token = 'YOUR_ACTUAL_TOKEN_HERE';

// 3. Configure SOAP Client Options for Debugging
$options = [
    'trace'              => true,
    'exceptions'         => true,
    'cache_wsdl'         => WSDL_CACHE_NONE,
    'connection_timeout' => 10,
    'user_agent'         => 'PHP-Soap-Debug/1.0',
];
$client = null;
try {
    // 4. Initialize SOAP Client
    echo "Initializing SOAP Client...\n";
    $client = new SoapClient('http://tms.ems.gov.ir/lms/lms.asmx?WSDL', $options);
    echo "SOAP Client initialized successfully.\n";

    // 5. Prepare Parameters
    // Note: The WSDL structure might require specific parameter naming.
    // Based on your code, you are passing an array inside an array.
    $params = [
        'token'    => $token,
        'PafToken' => '4c+mXeMZZf3KjlJFVZAQIGRBQYqYfhs/',
    ];

    // 6. Make the SOAP Call
    echo "Calling IsAuthenticateCustom...\n";
    $StudentInfo = $client->__soapCall('IsAuthenticateCustom', [$params]);

    // 7. Extract Result
    // Depending on the WSDL, the result might be in a property or the object itself.
    if (isset($StudentInfo->IsAuthenticateCustomResult)) {
        $info = $StudentInfo->IsAuthenticateCustomResult;
        echo "Success! Authentication Result:\n";
        var_dump($info); // Use var_dump to see the structure (string, bool, object, etc.)

        // If it's a boolean or simple value:
        if (is_bool($info)) {
            echo 'IsAuthenticated: '.($info ? 'True' : 'False')."\n";
        } elseif (is_string($info)) {
            echo 'Message: '.$info."\n";
        }
    } else {
        // If the property doesn't exist, print the whole object to debug
        echo "Warning: 'IsAuthenticateCustomResult' property not found.\n";
        echo "Response Object Structure:\n";
        var_dump($StudentInfo);
    }

} catch (SoapFault $fault) {
    // 8. Handle SOAP Errors
    echo "SOAP Fault Caught!\n";
    echo 'Fault Code: '.$fault->faultcode."\n";
    echo 'Fault String: '.$fault->faultstring."\n";
    echo 'Fault Actor: '.$fault->faultactor."\n";

    // Debug: Show the last request and response for troubleshooting
    echo "\n--- Last Request ---\n";
    echo $client->__getLastRequest();
    echo "\n--- Last Response ---\n";
    echo $client->__getLastResponse();

} catch (Exception $e) {
    // 9. Handle General Errors
    echo "General Exception Caught!\n";
    echo 'Message: '.$e->getMessage()."\n";
    echo "Trace:\n".$e->getTraceAsString()."\n";
}
