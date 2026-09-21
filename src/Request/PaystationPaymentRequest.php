<?php

namespace Xenon\Paystation\Request;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Xenon\Paystation\Environment;
use Xenon\Paystation\Exception\PaystationException;

class PaystationPaymentRequest
{
    private static $instance;

    private string $baseUrl = 'https://api.paystation.com.bd';

    private array $headers = [];

    /**
     * @param array $headers
     */
    public function setHeaders(array $headers): void
    {
        $this->headers = $headers;

    }

    /**
     * Point this request at a specific host.
     *
     * @return $this
     */
    public function setBaseUrl(string $baseUrl): self
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        return $this;
    }

    /**
     * Point this request at the host belonging to the given environment.
     *
     * @return $this
     * @throws PaystationException
     */
    public function setEnvironment(string $environment): self
    {
        return $this->setBaseUrl(Environment::baseUrl($environment));
    }

    /**
     * @return string
     */
    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    /**
     * @return mixed
     */
    public static function getInstance()
    {
        if (!isset(self::$instance)) {
            self::$instance = new static();
        }
        return self::$instance;
    }

    /**
     * @param $url
     * @param array $headers
     * @param array $body
     * @return \Psr\Http\Message\ResponseInterface
     * @throws PaystationException
     */
    public function post($url, array $headers = [], array $body = [])
    {
        $client = $this->getClient($this->baseUrl);

        try {
            return $client->request('POST', $url, [
                'headers' => $headers,
                'form_params' => $body,
            ]);

        } catch (GuzzleException $exception) {
            throw new PaystationException($exception->getMessage());
        }

    }

    public function get()
    {
        //todo:: do something here
    }

    /**
     * @param string $baseUrl
     * @return Client
     */
    private function getClient(string $baseUrl): Client
    {
        return new Client([
            'base_uri' => $baseUrl,
            'timeout' => 10.0,
        ]);
    }

}
