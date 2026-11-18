<?php

namespace DreamFactory\Core\Utility;

use DreamFactory\Core\Exceptions\BadRequestException;
use DreamFactory\Core\System\Components\OutboundHostAllowlist;
use DreamFactory\Core\System\Components\SsrfValidator;

/**
 * Works out which address a request for a remote URL is sent to.
 *
 * The URL is checked with the remote URL settings from df-system. Hosts the operator lists in
 * DF_OUTBOUND_ALLOWED_HOSTS are accepted here exactly as they are there, when the installed df-system has that
 * setting. The address that was checked is returned, so callers can send the request to it.
 */
class OutboundAddress
{
    /**
     * Message used when a URL is not accepted.
     */
    const NOT_ALLOWED = 'The requested URL is not allowed.';

    /**
     * Checks an http or https URL and returns the address to send the request to.
     *
     * @param string $url
     *
     * @throws BadRequestException When the URL is not accepted. The message gives the detailed reason;
     *                             FileUtilities and Curl log it and return a general message instead.
     * @return string The IPv4 address, or the IPv6 address (without brackets) for an IPv6 literal host.
     */
    public static function resolve($url)
    {
        $url = (string)$url;
        SsrfValidator::validateExternalUrl($url);

        $host = static::hostOf($url);
        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            // An IPv6 literal is the address itself and was checked above.
            return $host;
        }

        $ip = filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? $host : gethostbyname($host);
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            throw new BadRequestException(static::NOT_ALLOWED);
        }
        if (SsrfValidator::isPrivateOrReservedIp($ip) && !static::isListed($host, $ip)) {
            throw new BadRequestException(static::NOT_ALLOWED);
        }

        return $ip;
    }

    /**
     * True when the operator lists this destination in DF_OUTBOUND_ALLOWED_HOSTS (config key
     * df.outbound_allowed_hosts), by host name or by address. Always false when the installed df-system does not
     * have that setting.
     *
     * @param string $host The URL host, without IPv6 brackets.
     * @param string $ip   The address the host resolves to.
     *
     * @return bool
     */
    public static function isListed($host, $ip)
    {
        if (!class_exists(OutboundHostAllowlist::class)) {
            return false;
        }

        return OutboundHostAllowlist::fromConfig()->allows((string)$host, (string)$ip);
    }

    /**
     * The host of a URL, without IPv6 brackets.
     *
     * @param string $url
     *
     * @return string
     */
    public static function hostOf($url)
    {
        $host = (string)parse_url((string)$url, PHP_URL_HOST);

        return ltrim(rtrim($host, ']'), '[');
    }
}
