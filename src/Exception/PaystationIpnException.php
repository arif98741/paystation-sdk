<?php

namespace Xenon\Paystation\Exception;

use Throwable;

/**
 * Raised when an incoming IPN cannot be trusted.
 *
 * Every instance carries the HTTP status the merchant should answer the gateway
 * with, because that status decides whether the notification is retried:
 * Paystation retries on 4xx, 5xx and timeouts, and stops on 2xx.
 *
 * @see https://www.paystation.com.bd/documentation (Merchant IPN)
 */
class PaystationIpnException extends PaystationException
{
    /**
     * Status the merchant endpoint should respond with.
     */
    private $suggestedHttpStatus = 400;

    /**
     * Whether answering with that status invites the gateway to try again.
     */
    private $retryable = false;

    /**
     * Field the failure is about, when it is about one.
     *
     * @var string|null
     */
    private $field;

    public function __construct($message, $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    /**
     * The body was not valid json, so nothing can be read out of it.
     *
     * Not retryable: the gateway would only send the same bytes again.
     */
    public static function malformedJson(string $reason): self
    {
        return self::make(
            'Paystation IPN body is not valid json: ' . $reason,
            400,
            false
        );
    }

    /**
     * Valid json, but not the flat object the documentation describes.
     */
    public static function notAnObject(string $type): self
    {
        return self::make(
            'Paystation IPN body must be a flat json object, ' . $type . ' decoded.',
            400,
            false
        );
    }

    public static function missingField(string $field): self
    {
        $exception = self::make(
            "Paystation IPN is missing the required field '$field'.",
            400,
            false
        );
        $exception->field = $field;

        return $exception;
    }

    public static function invalidField(string $field, string $expected): self
    {
        $exception = self::make(
            "Paystation IPN field '$field' is invalid; expected $expected.",
            400,
            false
        );
        $exception->field = $field;

        return $exception;
    }

    /**
     * The request did not come from an address the merchant allow-listed.
     *
     * 403 and not retryable: retrying from the same address would be refused
     * again, and a forged request should not be handed a retry schedule.
     */
    public static function untrustedSource(?string $ip): self
    {
        return self::make(
            'Paystation IPN arrived from untrusted address ' . ($ip === null ? '(unknown)' : $ip) . '.',
            403,
            false
        );
    }

    /**
     * trx_status was not 'Success'. Per the documentation this IPN only fires
     * for successful transactions, so anything else is unexpected.
     */
    public static function notSuccessful(string $status): self
    {
        $exception = self::make(
            "Paystation IPN reports trx_status '$status'; only 'Success' is sent for this notification.",
            422,
            false
        );
        $exception->field = 'trx_status';

        return $exception;
    }

    /**
     * The notified amount is not the amount the merchant initiated.
     */
    public static function amountMismatch($expected, $received): self
    {
        $exception = self::make(
            'Paystation IPN amount ' . var_export($received, true)
            . ' does not match the expected ' . var_export($expected, true) . '.',
            422,
            false
        );
        $exception->field = 'trx_amount';

        return $exception;
    }

    /**
     * The gateway itself did not confirm the transaction when asked.
     *
     * Retryable: a confirmation call can fail for reasons that pass later, such
     * as the transaction not being queryable the instant the IPN is delivered.
     */
    public static function unconfirmed(string $detail): self
    {
        return self::make(
            'Paystation could not confirm the notified transaction: ' . $detail,
            503,
            true
        );
    }

    private static function make(string $message, int $status, bool $retryable): self
    {
        $exception = new self($message);
        $exception->suggestedHttpStatus = $status;
        $exception->retryable = $retryable;

        return $exception;
    }

    /**
     * Status to answer the gateway with.
     */
    public function suggestedHttpStatus(): int
    {
        return $this->suggestedHttpStatus;
    }

    /**
     * Whether the suggested status asks the gateway to deliver again.
     *
     * A permanently bad notification is better answered with a status that
     * stops the retries, so the merchant is not woken by the same broken
     * payload for the rest of the retry window.
     */
    public function isRetryable(): bool
    {
        return $this->retryable;
    }

    /**
     * @return string|null
     */
    public function field()
    {
        return $this->field;
    }
}
