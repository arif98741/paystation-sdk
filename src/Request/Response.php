<?php

namespace Xenon\Paystation\Request;

class Response
{
    public \GuzzleHttp\Psr7\Response $object;

    /**
     * @param \GuzzleHttp\Psr7\Response $object
     */
    public function __construct(\GuzzleHttp\Psr7\Response $object)
    {
        $this->object = $object;
    }

    /**
     * @return \GuzzleHttp\Psr7\Response
     */
    public function getJsonResponse()
    {
        return $this->getContents();
    }

    /**
     * @return \GuzzleHttp\Psr7\Response
     * @throws \JsonException
     */
    public function getObjectResponse()
    {
        return json_decode($this->getContents(), false);
    }


    /**
     * @return \GuzzleHttp\Psr7\Response
     * @throws \JsonException
     */
    public function getArrayResponse()
    {
        return json_decode($this->getContents(), true);
    }

    /**
     * @return string
     */
    private function getContents(): string
    {
        return $this->object->getBody()->getContents();
    }

}
