<?php

namespace Xenon\Paystation\Ipn;

use DateTimeImmutable;
use DateTimeZone;
use JsonException;
use Xenon\Paystation\Exception\PaystationIpnException;

/**
 * One Instant Payment Notification, as Paystation posts it to the merchant.
 *
 * Construction only checks that the documented fields are present and of the
 * documented type. Everything that is a judgement about the payment -- is it a
 * success, does the amount match the order -- is a separate call, so parsing a
 * notification never rejects one the gateway considered valid.
 *
 * @see https://www.paystation.com.bd/documentation (Merchant IPN > Request Payload)
 */
class IpnNotification
{
    /**
     * The only trx_status this notification is ever sent with.
     */
    public const STATUS_SUCCESS = 'Success';

    /**
     * Format of order_date_time.
     */
    public const DATE_FORMAT = 'Y-m-d H:i:s';

    /**
     * Fields the documentation marks Required, with the type it gives them.
     */
    private const REQUIRED_FIELDS = [
        'invoice_number' => 'string',
        'trx_status' => 'string',
        'trx_id' => 'string',
        'trx_amount' => 'number',
        'order_date_time' => 'string',
        'payment_method' => 'string',
    ];

    /**
     * Fields the documentation marks Optional.
     */
    private const OPTIONAL_FIELDS = [
        'reference' => 'string',
    ];

    /**
     * @var array
     */
    private $payload;

    /**
     * @param array $payload decoded IPN body
     * @throws PaystationIpnException
     */
    public function __construct(array $payload)
    {
        $this->assertShape($payload);
        $this->payload = $payload;
    }

    /**
     * @param array $payload
     * @return static
     * @throws PaystationIpnException
     */
    public static function fromArray(array $payload): self
    {
        return new self($payload);
    }

    /**
     * Build from the raw request body.
     *
     * @throws PaystationIpnException
     */
    public static function fromJson(string $json): self
    {
        if (trim($json) === '') {
            throw PaystationIpnException::malformedJson('the body is empty');
        }

        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw PaystationIpnException::malformedJson($exception->getMessage());
        }

        if (!is_array($decoded) || (isset($decoded[0]) && array_keys($decoded) === range(0, count($decoded) - 1))) {
            throw PaystationIpnException::notAnObject(is_array($decoded) ? 'a json array' : gettype($decoded));
        }

        return new self($decoded);
    }

    /**
     * @throws PaystationIpnException
     */
    private function assertShape(array $payload): void
    {
        foreach (self::REQUIRED_FIELDS as $field => $type) {
            if (!array_key_exists($field, $payload)) {
                throw PaystationIpnException::missingField($field);
            }

            $value = $payload[$field];

            if ($type === 'number') {
                //trx_amount is documented as a json number; a numeric string is
                //accepted too rather than refused, since refusing would ask the
                //gateway to redeliver a notification it considers well formed
                if (!is_numeric($value)) {
                    throw PaystationIpnException::invalidField($field, 'a number');
                }

                continue;
            }

            if (!is_string($value) && !is_numeric($value)) {
                throw PaystationIpnException::invalidField($field, 'a string');
            }

            if (trim((string)$value) === '') {
                throw PaystationIpnException::invalidField($field, 'a non-empty string');
            }
        }

        foreach (self::OPTIONAL_FIELDS as $field => $type) {
            if (array_key_exists($field, $payload)
                && $payload[$field] !== null
                && !is_string($payload[$field])
                && !is_numeric($payload[$field])) {
                throw PaystationIpnException::invalidField($field, 'a string when present');
            }
        }
    }

    /**
     * Order reference the payment was created with. Match the notification to
     * an order on this.
     */
    public function invoiceNumber(): string
    {
        return (string)$this->payload['invoice_number'];
    }

    public function trxStatus(): string
    {
        return (string)$this->payload['trx_status'];
    }

    /**
     * Gateway transaction id. Keep it for reconciliation and disputes.
     */
    public function trxId(): string
    {
        return (string)$this->payload['trx_id'];
    }

    /**
     * Amount in BDT.
     */
    public function amount(): float
    {
        return (float)$this->payload['trx_amount'];
    }

    public function paymentMethod(): string
    {
        return (string)$this->payload['payment_method'];
    }

    /**
     * Provider reference, when the gateway sent one.
     *
     * @return string|null
     */
    public function reference()
    {
        $reference = $this->payload['reference'] ?? null;

        return ($reference === null || $reference === '') ? null : (string)$reference;
    }

    /**
     * order_date_time exactly as it arrived.
     */
    public function orderDateTimeString(): string
    {
        return (string)$this->payload['order_date_time'];
    }

    /**
     * order_date_time as a date object, or null when it does not parse.
     *
     * Null rather than an exception: an unreadable timestamp is no reason to
     * refuse a notification whose money has already moved.
     *
     * The documentation does not state which timezone the gateway stamps these
     * in. Paystation settles in Bangladesh, so pass new DateTimeZone('Asia/Dhaka')
     * if you need the instant rather than the wall clock reading; with no
     * argument the php default timezone is used.
     *
     * @return DateTimeImmutable|null
     */
    public function orderDateTime(?DateTimeZone $timezone = null)
    {
        $parsed = $timezone === null
            ? DateTimeImmutable::createFromFormat(self::DATE_FORMAT, $this->orderDateTimeString())
            : DateTimeImmutable::createFromFormat(self::DATE_FORMAT, $this->orderDateTimeString(), $timezone);

        return $parsed === false ? null : $parsed;
    }

    /**
     * Whether the gateway reported the transaction as successful.
     *
     * Compared case insensitively and trimmed. The documentation promises the
     * literal 'Success', and this is the value to confirm rather than assume --
     * the checklist asks for it explicitly.
     */
    public function isSuccess(): bool
    {
        return strcasecmp(trim($this->trxStatus()), self::STATUS_SUCCESS) === 0;
    }

    /**
     * Whether the notified amount is the amount that was initiated.
     *
     * Compared numerically with a tolerance, because trx_amount arrives as a
     * json number and string equality on money is a known trap the gateway
     * documentation warns about directly.
     *
     * @param int|float|string $expected amount the order was created for
     * @param float $tolerance largest difference still treated as equal
     */
    public function matchesAmount($expected, float $tolerance = 0.005): bool
    {
        if (!is_numeric($expected)) {
            return false;
        }

        return abs($this->amount() - (float)$expected) <= abs($tolerance);
    }

    /**
     * The payload as it arrived, including any field this class does not know.
     */
    public function toArray(): array
    {
        return $this->payload;
    }

    /**
     * Value of any field, known or not.
     *
     * @return mixed
     */
    public function get(string $field, $default = null)
    {
        return $this->payload[$field] ?? $default;
    }

    /**
     * Fields worth writing to a log line. trx_id is included because the
     * documentation asks merchants to keep it for reconciliation.
     */
    public function loggable(): array
    {
        return [
            'invoice_number' => $this->invoiceNumber(),
            'trx_id' => $this->trxId(),
            'trx_status' => $this->trxStatus(),
            'trx_amount' => $this->amount(),
            'payment_method' => $this->paymentMethod(),
            'order_date_time' => $this->orderDateTimeString(),
            'reference' => $this->reference(),
        ];
    }
}
