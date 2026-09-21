<?php

namespace Xenon\Paystation\Ipn;

use Xenon\Paystation\Exception\PaystationIpnException;

/**
 * The answer the merchant sends back to the gateway.
 *
 * The status code is the whole protocol here: Paystation stops on 2xx and
 * retries on 4xx, 5xx and timeouts. Getting it wrong in either direction is
 * costly -- a 2xx on a notification you failed to record loses the payment
 * silently, and a 5xx on one you did record invites the same IPN again.
 *
 * Nothing is echoed until send() is called, so this is usable from a framework
 * that builds its own response.
 *
 * @see https://www.paystation.com.bd/documentation (Merchant IPN > Response Expectation)
 */
class IpnResponse
{
    /**
     * @var int
     */
    private $status;

    /**
     * @var array
     */
    private $body;

    public function __construct(int $status, array $body = [])
    {
        $this->status = $status;
        $this->body = $body;
    }

    /**
     * Processed, or already processed. The gateway makes no further attempt.
     *
     * Also the right answer to a repeat delivery of something already handled:
     * the documentation asks for a plain 200 in that case rather than an error.
     */
    public static function acknowledged(array $extra = []): self
    {
        return new self(200, array_merge(['status' => 'success'], $extra));
    }

    /**
     * Ask the gateway to deliver again later.
     *
     * For failures on the merchant side -- a database that was down, a lock not
     * acquired -- where the same notification would succeed on a later attempt.
     */
    public static function retry(string $reason = 'temporarily unavailable', int $status = 503): self
    {
        return new self($status, ['status' => 'retry', 'reason' => $reason]);
    }

    /**
     * Refuse the notification without inviting a retry that cannot succeed.
     *
     * Returns 200 by design: the payload is permanently unusable, so asking the
     * gateway to resend it only repeats the failure for the rest of the retry
     * window. Record it and investigate out of band. Pass a 4xx status instead
     * if you would rather have the gateway keep trying.
     */
    public static function rejected(string $reason, int $status = 200): self
    {
        return new self($status, ['status' => 'rejected', 'reason' => $reason]);
    }

    /**
     * Build the answer that fits a verification failure, using the status the
     * exception itself suggests.
     */
    public static function fromException(PaystationIpnException $exception): self
    {
        return new self(
            $exception->suggestedHttpStatus(),
            [
                'status' => $exception->isRetryable() ? 'retry' : 'rejected',
                'reason' => $exception->getMessage(),
            ]
        );
    }

    public function statusCode(): int
    {
        return $this->status;
    }

    public function body(): array
    {
        return $this->body;
    }

    /**
     * Whether this answer tells the gateway the notification is settled.
     */
    public function isAcknowledgement(): bool
    {
        return $this->status >= 200 && $this->status < 300;
    }

    /**
     * @throws \JsonException
     */
    public function json(): string
    {
        return json_encode($this->body, JSON_THROW_ON_ERROR);
    }

    /**
     * Emit the answer directly, for plain php endpoints.
     *
     * Deliberately does not call exit: the caller decides when the script ends,
     * and anything that still needs to run after acknowledging -- the slow work
     * the documentation asks you to defer -- must not be cut off here.
     *
     * @throws \JsonException
     */
    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);
            header('Content-Type: application/json');
        }

        echo $this->json();
    }
}
