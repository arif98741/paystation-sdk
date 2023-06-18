<?php

namespace Xenon\Paystation\Exception;

use Throwable;

class PaystationPaymentParameterException extends \Exception
{
    /**
     * Report the exception.
     *
     * @return void
     */
    public function __construct($message, $code = 0, Throwable $previous = null)
    {
        // Call the parent constructor
        parent::__construct($message, $code, $previous);
    }
}
