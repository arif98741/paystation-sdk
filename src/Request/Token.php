<?php

namespace Xenon\Paystation\Request;

use Xenon\Paystation\Environment;

class Token
{
    /**
     * Get token based on merchantid and password
     *
     * Honours the optional 'environment' key of the config so the token is
     * granted by the same host the payment is later created on.
     *
     * @throws \JsonException
     * @throws \Xenon\Paystation\Exception\PaystationException
     */
    public static function getToken($config)
    {
        $instance = PaystationPaymentRequest::getInstance();
        $instance->setEnvironment(Environment::fromConfig((array)$config));
        $response = $instance->post('grant-token', [
            'merchantId' => $config['merchantId'],
            'password' => $config['password'],
        ]);

        $responseArray = (new Response($response))->getArrayResponse();
        return $responseArray['token'];
    }

}
