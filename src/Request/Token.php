<?php

namespace Xenon\Paystation\Request;

class Token
{
    /**
     * Get token based on merchantid and password
     * @throws \JsonException
     */
    public static function getToken($config)
    {
        $instance = PaystationPaymentRequest::getInstance();
        $response = $instance->post('grant-token', [
            'merchantId' => $config['merchantId'],
            'password' => $config['password'],
        ]);
        $responseArray = (new Response($response))->getArrayResponse();
        return $responseArray['token'];
    }

}
