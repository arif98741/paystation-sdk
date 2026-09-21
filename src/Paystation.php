<?php

namespace Xenon\Paystation;

use Xenon\Paystation\Exception\PaystationException;
use Xenon\Paystation\Exception\PaystationPaymentParameterException;
use Xenon\Paystation\Request\PaystationPaymentRequest;
use Xenon\Paystation\Request\Response;
use Xenon\Paystation\Request\Token;

class Paystation
{
    private string $environment;

    private array $config;

    private array $paymentParams = [];

    /**
     * @param array $config accepts 'merchantId', 'password' and an optional
     *                      'environment' of 'sandbox' or 'live'. Omitting the
     *                      environment keeps talking to the live host.
     * @throws PaystationException
     */
    public function __construct(array $config)
    {
        $this->config = $config;
        $this->setEnvironment($config['environment'] ?? null);
    }

    /**
     * Credentials are issued per merchant and differ between sandbox and live,
     * so they always come from the caller.
     *
     * Checked when a request is about to be made rather than in the constructor,
     * so building the object early (a container binding, say) never throws.
     *
     * @throws PaystationException
     */
    private function validateConfig(): void
    {
        foreach (['merchantId', 'password'] as $key) {
            if (!isset($this->config[$key]) || trim((string)$this->config[$key]) === '') {
                throw new PaystationException(
                    "Paystation config '$key' is required. Pass your sandbox or live credentials, e.g. "
                    . "new Paystation(['merchantId' => '...', 'password' => '...', 'environment' => 'sandbox'])."
                );
            }
        }
    }

    /**
     * Switch between the sandbox and live gateway.
     *
     * @param string|null $environment 'sandbox' or 'live'
     * @return $this
     * @throws PaystationException
     */
    public function setEnvironment($environment): self
    {
        $this->environment = Environment::normalize($environment);
        return $this;
    }

    /**
     * Config handed to Token::getToken() so the token is granted by the host
     * this instance is currently pointed at. The resolved environment wins over
     * whatever spelling arrived in the original config.
     */
    private function tokenConfig(): array
    {
        $this->validateConfig();
        return array_merge($this->config, ['environment' => $this->environment]);
    }

    /**
     * @return string
     */
    public function getEnvironment(): string
    {
        return $this->environment;
    }

    /**
     * Host the current environment talks to.
     *
     * @throws PaystationException
     */
    public function getBaseUrl(): string
    {
        return Environment::baseUrl($this->environment);
    }

    /**
     * @throws PaystationException
     */
    public function isSandbox(): bool
    {
        return $this->environment === Environment::SANDBOX;
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
            'token' => Token::getToken($this->tokenConfig()),
        ];
        $instance->setEnvironment($this->environment);
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
            'token' => Token::getToken($this->tokenConfig()),
        ];
        $instance->setEnvironment($this->environment);
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
