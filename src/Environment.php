<?php

namespace Xenon\Paystation;

use Xenon\Paystation\Exception\PaystationException;

/**
 * Paystation keeps two separate environments; sandbox for building and mock
 * payment simulations, live for routing real customer cards.
 *
 * @see https://paystation.com.bd/documentation (Getting Started > Environments)
 */
class Environment
{
    public const SANDBOX = 'sandbox';

    public const LIVE = 'live';

    /**
     * Base url per environment as published in the documentation.
     */
    private const BASE_URLS = [
        self::SANDBOX => 'https://sandbox.paystation.com.bd',
        self::LIVE => 'https://api.paystation.com.bd',
    ];

    /**
     * Spellings people reasonably reach for, mapped to the canonical name.
     */
    private const ALIASES = [
        'sandbox' => self::SANDBOX,
        'test' => self::SANDBOX,
        'testing' => self::SANDBOX,
        'dev' => self::SANDBOX,
        'development' => self::SANDBOX,
        'live' => self::LIVE,
        'production' => self::LIVE,
        'prod' => self::LIVE,
    ];

    /**
     * Resolve any accepted spelling to a canonical environment name.
     *
     * A null or empty value keeps the historical behaviour of this library,
     * which always talked to the live host.
     *
     * @throws PaystationException when the environment is not recognised
     */
    public static function normalize($environment): string
    {
        if ($environment === null || $environment === '') {
            return self::LIVE;
        }

        if (!is_string($environment)) {
            throw new PaystationException('Paystation environment must be a string, ' . gettype($environment) . ' given.');
        }

        $key = strtolower(trim($environment));

        if (!isset(self::ALIASES[$key])) {
            throw new PaystationException(
                "Unknown Paystation environment '$environment'. Use '" . self::SANDBOX . "' or '" . self::LIVE . "'."
            );
        }

        return self::ALIASES[$key];
    }

    /**
     * Base url for the given environment.
     *
     * @throws PaystationException
     */
    public static function baseUrl($environment): string
    {
        return self::BASE_URLS[self::normalize($environment)];
    }

    /**
     * Resolve the environment out of a config array.
     *
     * @throws PaystationException
     */
    public static function fromConfig(array $config): string
    {
        return self::normalize($config['environment'] ?? null);
    }

    /**
     * @throws PaystationException
     */
    public static function isSandbox($environment): bool
    {
        return self::normalize($environment) === self::SANDBOX;
    }

    /**
     * Every environment this library understands.
     *
     * @return string[]
     */
    public static function all(): array
    {
        return array_keys(self::BASE_URLS);
    }
}
