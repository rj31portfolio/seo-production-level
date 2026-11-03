<?php

namespace App\Services\Seo;

use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use RuntimeException;
use Symfony\Component\HttpFoundation\IpUtils;

class PublicUrl
{
    public function normalize(string $url): string
    {
        if (strlen($url) > 2048 || preg_match('/[\x00-\x20\x7f\\\\]/', $url)) {
            throw new RuntimeException('Invalid URL characters or length.');
        }
        $p = parse_url($url);
        if (! $p || ! in_array(strtolower($p['scheme'] ?? ''), ['http', 'https'], true) || empty($p['host']) || isset($p['user']) || isset($p['pass']) || (isset($p['port']) && ! in_array($p['port'], [80, 443], true))) {
            throw new RuntimeException('Only public HTTP/HTTPS URLs on standard ports without credentials are accepted.');
        }

        $uri = (new Uri($url))->withScheme(strtolower($p['scheme']))->withHost(strtolower($p['host']))->withFragment('');

        return (string) ($uri->getPath() === '' ? $uri->withPath('/') : $uri);
    }

    public function resolve(string $url): array
    {
        $url = $this->normalize($url);
        $p = parse_url($url);
        $host = trim($p['host'], '[]');
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $ips = [$host];
        } else {
            if (! preg_match('/^[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?$/i', $host) || ! str_contains($host, '.') || preg_match('/^[0-9.]+$/', $host) || preg_match('/(?:^|\.)(localhost|local|internal|test|invalid)$/i', $host)) {
                throw new RuntimeException('A public domain name is required.');
            }
            $ips = $this->addresses($host);
        }
        if (! $ips) {
            throw new RuntimeException('The public hostname could not be resolved.');
        }
        foreach ($ips as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) && (ord(inet_pton($ip)[0]) & 0xE0) !== 0x20) {
                throw new RuntimeException('IPv6 translation, mapped and non-global destinations are blocked.');
            }
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)
                || IpUtils::checkIp($ip, ['100.64.0.0/10', '192.0.0.0/24', '192.0.2.0/24', '192.88.99.0/24', '198.18.0.0/15', '198.51.100.0/24', '203.0.113.0/24', '224.0.0.0/4', '2001::/23', '2001:db8::/32', '2002::/16'])
            ) {
                throw new RuntimeException('Private, reserved, and local network destinations are blocked.');
            }
        }

        return ['url' => $url, 'host' => $host, 'port' => $p['port'] ?? ($p['scheme'] === 'https' ? 443 : 80), 'ip' => $ips[0]];
    }

    public function addresses(string $host): array
    {
        $v4 = @gethostbynamel($host) ?: [];
        $v6 = array_column(@dns_get_record($host, DNS_AAAA) ?: [], 'ipv6');

        return array_values(array_unique(array_merge($v4, $v6)));
    }

    public function relative(string $base, string $relative): ?string
    {
        try {
            return $this->normalize((string) UriResolver::resolve(new Uri($base), new Uri(trim($relative))));
        } catch (\Throwable) {
            return null;
        }
    }

    public function origin(string $url): string
    {
        $p = parse_url($this->normalize($url));

        return $p['scheme'].'://'.$p['host'].(isset($p['port']) ? ':'.$p['port'] : '');
    }
}
