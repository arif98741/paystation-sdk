<?php

namespace Xenon\Paystation;

use Xenon\Paystation\Exception\PaystationException;
use Xenon\Paystation\Exception\PaystationPaymentParameterException;
use Xenon\Paystation\Request\PaystationPaymentRequest;
use Xenon\Paystation\Request\Response;
use Xenon\Paystation\Request\Token;

class Paystation
{
    private string $environment = 'sandbox';

    private array $config;

    private array $paymentParams = [];

    /**
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * @param array $paymentParams
     * @return string
     */
    public function setPaymentParams(array $paymentParams)
    {
        $this->paymentParams = $paymentParams;
        return self::class;
    }


    /**
     * @return array
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * @return void
     * @throws PaystationException
     * @throws PaystationPaymentParameterException
     * @throws \JsonException
     */
    public function payNow()
    {
        $this->validateParams();
        $this->createPayment();
    }

    /**
     * This is verification payment for payment
     * This will accept invoice_number and trx_id as argent parameters
     * @return \GuzzleHttp\Psr7\Response
     * @throws \JsonException|PaystationException
     */
    public function verifyPayment(string $invoiceNumber, string $transactionId)
    {
        $instance = PaystationPaymentRequest::getInstance();
        $header = [
            'token' => Token::getToken($this->config),
        ];
        $instance->setHeaders($header);
        $params = [
            'invoice_number' => $invoiceNumber,
            'trx_id' => $transactionId
        ];
        $requestResponse = $instance->post('retrive-transaction', $header, $params);
        return (new Response($requestResponse))->getJsonResponse();
    }

    /**
     * @return void
     * @throws \JsonException
     * @throws PaystationException
     */
    private function createPayment()
    {
        $instance = PaystationPaymentRequest::getInstance();
        $header = [
            'token' => Token::getToken($this->config),
        ];
        $instance->setHeaders($header);

        $requestResponse = $instance->post('create-payment', $header, $this->paymentParams);
        $paymentObject = (new Response($requestResponse))->getObjectResponse();

        if ($paymentObject->status_code == 200 && $paymentObject->status == 'success') {

            $url = json_encode($paymentObject->payment_url, JSON_THROW_ON_ERROR);
            echo "<script>window.open($url, '_self')</script>";
            exit;
        }

        throw new PaystationException("Failed to create payment url; status: " . json_encode($paymentObject, JSON_THROW_ON_ERROR));

    }

    /**
     * @throws PaystationPaymentParameterException
     */
    private function validateParams()
    {
        $requiredParams = [
            'invoice_number' => "",
            'currency' => "",
            'payment_amount' => "",
            'reference' => "",
            'cust_name' => "",
            'cust_phone' => "",
            'cust_email' => "",
            'cust_address' => "",
            'callback_url' => ""
        ];

        $unmatchedKeys = array_diff_key($requiredParams, $this->paymentParams);
        $unmatchedTotal = count($unmatchedKeys);
        if ($unmatchedTotal > 0) {
            $requiredParamsString = array_keys($unmatchedKeys);
            $requiredParamsString = implode(', ', $requiredParamsString);

            $string = 'is';
            if ($unmatchedTotal > 1) {
                $string = 'are';
            }

            throw new PaystationPaymentParameterException("Payment  parameter '$requiredParamsString' $string required. For better understanding visit https://www.paystation.com.bd/documentation/#create-request-parameters");
        }
    }

}
