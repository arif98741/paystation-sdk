<?php

namespace Xenon\Paystation\Ipn;

use Xenon\Paystation\Exception\PaystationException;
use Xenon\Paystation\Exception\PaystationIpnException;
use Xenon\Paystation\Paystation;

/**
 * Receives and vets a Merchant IPN.
 *
 * The notification carries no signature and no authentication -- the gateway
 * documents the endpoint as "Auth: None" -- so anyone who learns the url can
 * post to it. Nothing here can change that, which is why the strongest check
 * offered is confirm(): asking the gateway itself, over the merchant's own
 * credentials, whether the transaction the body describes is real.
 *
 * Idempotency is the one item on the documented checklist this class cannot do
 * for you. It needs the merchant's own order state, so it stays in the
 * application: look the invoice up, and if it is already paid, answer
 * IpnResponse::acknowledged() without processing again.
 *
 * @see https://www.paystation.com.bd/documentation (Merchant IPN)
 */
class IpnHandler
{
    /**
     * @var Paystation|null
     */
    private $paystation;

    /**
     * @var string[]
     */
    private $trustedIps = [];

    /**
     * @param Paystation|null $paystation instance whose credentials are used by
     *                                    confirm(); optional, everything else
     *                                    works without it
     */
    public function __construct(?Paystation $paystation = null)
    {
        $this->paystation = $paystation;
    }

    /**
     * Addresses allowed to post to the endpoint, as plain ips or ipv4 CIDR
     * ranges. Empty means the check is skipped.
     *
     * Paystation does not publish its sending addresses, so ask them for the
     * current list before switching this on -- an out of date allow-list
     * rejects real payments.
     *
     * @param string[] $ips
     * @return $this
     */
    public function trustIps(array $ips): self
    {
        $this->trustedIps = array_values(array_filter(array_map('trim', $ips), 'strlen'));

        return $this;
    }

    /**
     * Raw request body, read from the input stream when not supplied.
     */
    public static function rawBody(?string $body = null): string
    {
        if ($body !== null) {
            return $body;
        }

        $input = file_get_contents('php://input');

        return $input === false ? '' : $input;
    }

    /**
     * Parse the notification.
     *
     * @param string|null $rawBody body, or null to read php://input
     * @throws PaystationIpnException when the body is not a usable IPN
     */
    public function capture(?string $rawBody = null): IpnNotification
    {
        return IpnNotification::fromJson(self::rawBody($rawBody));
    }

    /**
     * Run the documented checks that do not need the merchant database.
     *
     * Every failure throws, carrying the status to answer with. The two checks
     * this leaves to the caller are the two that need order state: that the
     * invoice exists and is pending, and that the transaction has not already
     * been processed.
     *
     * @param int|float|string|null $expectedAmount amount the invoice was
     *                                              created for; null skips the
     *                                              amount check
     * @param string|null $sourceIp remote address, checked only when ips are trusted
     * @throws PaystationIpnException
     */
    public function validate(IpnNotification $notification, $expectedAmount = null, ?string $sourceIp = null): void
    {
        if ($this->trustedIps !== []) {
            $this->assertTrustedSource($sourceIp);
        }

        if (!$notification->isSuccess()) {
            throw PaystationIpnException::notSuccessful($notification->trxStatus());
        }

        if ($expectedAmount !== null && !$notification->matchesAmount($expectedAmount)) {
            throw PaystationIpnException::amountMismatch($expectedAmount, $notification->amount());
        }
    }

    /**
     * Whether the address is one of the trusted ones. True when none are set.
     */
    public function isTrustedSource(?string $ip): bool
    {
        if ($this->trustedIps === []) {
            return true;
        }

        if ($ip === null || $ip === '') {
            return false;
        }

        foreach ($this->trustedIps as $trusted) {
            if ($this->ipMatches($ip, $trusted)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @throws PaystationIpnException
     */
    public function assertTrustedSource(?string $ip): void
    {
        if (!$this->isTrustedSource($ip)) {
            throw PaystationIpnException::untrustedSource($ip);
        }
    }

    /**
     * Ask the gateway to confirm the notified transaction.
     *
     * This is the check that does not depend on trusting the request: it calls
     * retrive-transaction with the merchant's own token, so a forged body can
     * only pass if the transaction it describes genuinely exists, succeeded, and
     * carries the same amount.
     *
     * Slower than the other checks -- one round trip -- and the documentation
     * asks for a fast acknowledgement, so prefer it where a wrong answer is
     * expensive, or run it just after acknowledging.
     *
     * @return array the verification payload the gateway returned
     * @throws PaystationIpnException when the gateway does not confirm
     * @throws PaystationException when no Paystation instance was supplied
     */
    public function confirm(IpnNotification $notification): array
    {
        if ($this->paystation === null) {
            throw new PaystationException(
                'IpnHandler::confirm() needs a Paystation instance. Construct the handler with '
                . 'new IpnHandler(new Paystation([...])) to enable gateway confirmation.'
            );
        }

        try {
            $raw = $this->paystation->verifyPayment($notification->invoiceNumber(), $notification->trxId());
        } catch (\Throwable $exception) {
            throw PaystationIpnException::unconfirmed(
                'the verification call failed: ' . $exception->getMessage()
            );
        }

        $payload = $this->decodeVerification($raw);

        if ((int)($payload['status_code'] ?? 0) !== 200 || ($payload['status'] ?? null) !== 'success') {
            throw PaystationIpnException::unconfirmed(
                'the gateway reported ' . json_encode([
                    'status' => $payload['status'] ?? null,
                    'status_code' => $payload['status_code'] ?? null,
                    'message' => $payload['message'] ?? null,
                ])
            );
        }

        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];

        if (strcasecmp(trim((string)($data['trx_status'] ?? '')), IpnNotification::STATUS_SUCCESS) !== 0) {
            throw PaystationIpnException::unconfirmed(
                "the transaction is recorded as '" . (string)($data['trx_status'] ?? '') . "', not Success"
            );
        }

        if (isset($data['trx_id']) && (string)$data['trx_id'] !== $notification->trxId()) {
            throw PaystationIpnException::unconfirmed('the confirmed trx_id is not the notified one');
        }

        if (isset($data['payment_amount']) && !$notification->matchesAmount($data['payment_amount'])) {
            throw PaystationIpnException::unconfirmed(
                'the confirmed amount ' . var_export($data['payment_amount'], true)
                . ' is not the notified ' . var_export($notification->amount(), true)
            );
        }

        return $payload;
    }

    /**
     * verifyPayment() hands back the raw response body, so accept a string as
     * well as an already decoded array.
     *
     * @param mixed $raw
     * @throws PaystationIpnException
     */
    private function decodeVerification($raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }

        if (is_object($raw)) {
            return json_decode(json_encode($raw), true) ?: [];
        }

        $decoded = json_decode((string)$raw, true);

        if (!is_array($decoded)) {
            throw PaystationIpnException::unconfirmed('the verification response was not readable json');
        }

        return $decoded;
    }

    /**
     * Exact match, or containment in an ipv4 CIDR range.
     */
    private function ipMatches(string $ip, string $trusted): bool
    {
        if (strpos($trusted, '/') === false) {
            return $ip === $trusted;
        }

        [$subnet, $bits] = explode('/', $trusted, 2);

        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);

        if ($ipLong === false || $subnetLong === false || !is_numeric($bits)) {
            return false;
        }

        $bits = (int)$bits;

        if ($bits < 0 || $bits > 32) {
            return false;
        }

        if ($bits === 0) {
            return true;
        }

        $mask = -1 << (32 - $bits);

        return ($ipLong & $mask) === ($subnetLong & $mask);
    }
}
