<?php

namespace Pterodactyl\Services\Region;

use GuzzleHttp\Client;
use Illuminate\Contracts\Cache\Repository;
use RuntimeException;
use Throwable;

class RegionLookupService
{
    private const RESOLVERS = [
        'DNS-Google' => 'https://dns.google/dns-query',
        'DNS-Cloudflare' => 'https://cloudflare-dns.com/dns-query',
        'DNS-OpenDNS' => 'https://doh.opendns.com/dns-query',
        'DNS-Quad9' => 'https://dns.quad9.net/dns-query',
    ];

    public function __construct(private Client $client, private Repository $cache)
    {
    }

    public function lookup(string $address, string $resolver = 'DNS-Cloudflare'): array
    {
        $address = self::normalizeAddress($address);
        if (self::isLocalAddress($address)) {
            return self::result('local', 'LocalHost');
        }

        if (!filter_var($address, FILTER_VALIDATE_IP)
            && !filter_var($address, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
            return self::result('invalid', 'Invalid address');
        }

        $resolver = isset(self::RESOLVERS[$resolver]) ? $resolver : 'DNS-Cloudflare';
        $key = 'region:v2:' . hash('sha256', $resolver . ':' . $address);
        if (is_array($cached = $this->cache->get($key))) {
            return $cached;
        }

        try {
            $resolved = filter_var($address, FILTER_VALIDATE_IP) ? $address : $this->resolve($address, $resolver);
            if (self::isLocalAddress($resolved)) {
                $result = self::result('local', 'LocalHost');
                $this->cache->put($key, $result, 300);

                return $result;
            }

            $ipKey = 'region:v2:ip:' . hash('sha256', $resolved);
            if (is_array($cached = $this->cache->get($ipKey))) {
                $this->cache->put($key, $cached, 300);

                return $cached;
            }

            if ($this->cache->get('region:v2:rate-limited')) {
                return self::result('rate_limited', 'Location unavailable');
            }

            $response = $this->client->request('GET', 'https://ipinfo.spacehost.ovh/api/v2/lookup', [
                'query' => ['ip' => $resolved, 'checkHostname' => 'false'],
                'headers' => ['Accept' => 'application/json'],
                'connect_timeout' => 2,
                'timeout' => 5,
                'allow_redirects' => false,
                'http_errors' => false,
            ]);

            if ($response->getStatusCode() === 429) {
                $retry = $response->getHeaderLine('Retry-After');
                $this->cache->put('region:v2:rate-limited', true, ctype_digit($retry) ? max(60, min(86400, (int) $retry)) : 300);
                $result = self::result('rate_limited', 'Location unavailable');
            } elseif ($response->getStatusCode() === 200) {
                $data = json_decode((string) $response->getBody(), true, 32, JSON_THROW_ON_ERROR);
                if (!is_array($data) || !is_string($data['ip'] ?? null)
                    || self::normalizeAddress($data['ip']) !== $resolved || !empty($data['error'])) {
                    throw new RuntimeException('Invalid location response.');
                }

                $country = $data['country'] ?? null;
                $code = $data['country_code'] ?? null;
                if (!is_string($country) || trim($country) === '' || !is_string($code) || !preg_match('/^[A-Za-z]{2}$/D', $code)) {
                    throw new RuntimeException('Location response has no country.');
                }

                $result = [
                    'status' => 'success',
                    'country_name' => mb_substr(trim($country), 0, 100),
                    'country_code' => strtoupper($code),
                    'city' => is_string($data['city'] ?? null) ? mb_substr(trim($data['city']), 0, 100) : null,
                ];
                $this->cache->put($ipKey, $result, 86400);
            } else {
                throw new RuntimeException('Location provider unavailable.');
            }

            $this->cache->put($key, $result, $result['status'] === 'success' ? (filter_var($address, FILTER_VALIDATE_IP) ? 86400 : 300) : 60);

            return $result;
        } catch (Throwable $exception) {
            $result = self::result('unavailable', 'Location unavailable');
            $this->cache->put($key, $result, 60);

            return $result;
        }
    }

    public static function selectLookupAddress(string $address, ?string $nodeHostname): array
    {
        if (self::isLocalAddress($address)) {
            $nodeHostname = is_string($nodeHostname) ? trim($nodeHostname) : '';

            return [
                'address' => $nodeHostname !== '' ? $nodeHostname : $address,
                'local_ip' => true,
                'source' => $nodeHostname !== '' ? 'sftp_node' : 'allocation',
            ];
        }

        return ['address' => $address, 'local_ip' => false, 'source' => 'allocation'];
    }

    private static function result(string $status, string $label): array
    {
        return ['status' => $status, 'country_name' => $label, 'country_code' => '', 'city' => null];
    }

    private function resolve(string $hostname, string $resolver): string
    {
        foreach ([1, 28] as $type) {
            $identifier = random_int(0, 65535);
            $question = '';
            foreach (explode('.', $hostname) as $label) {
                $question .= chr(strlen($label)) . $label;
            }

            $response = $this->client->request('POST', self::RESOLVERS[$resolver], [
                'version' => '2.0',
                'headers' => ['Accept' => 'application/dns-message', 'Content-Type' => 'application/dns-message'],
                'body' => pack('nnnnnn', $identifier, 256, 1, 0, 0, 0) . $question . "\0" . pack('nn', $type, 1),
                'connect_timeout' => 2,
                'timeout' => 3,
                'allow_redirects' => false,
                'http_errors' => false,
            ]);

            if ($response->getStatusCode() !== 200) {
                throw new RuntimeException('DNS provider unavailable.');
            }

            $addresses = self::parseDnsResponse((string) $response->getBody(), $identifier, $type);
            if ($addresses !== []) {
                return $addresses[0];
            }
        }

        throw new RuntimeException('Hostname has no address records.');
    }

    private static function parseDnsResponse(string $message, int $identifier, int $type): array
    {
        if (strlen($message) < 12 || strlen($message) > 65535) {
            throw new RuntimeException('Invalid DNS response length.');
        }

        $header = unpack('nid/nflags/nquestions/nanswers/nauthority/nadditional', $message);
        if ($header['id'] !== $identifier || ($header['flags'] & 32768) === 0 || ($header['flags'] & 512) !== 0
            || ($header['flags'] & 15) !== 0 || $header['questions'] !== 1) {
            throw new RuntimeException('Invalid DNS response header.');
        }

        $offset = 12;
        self::skipDnsName($message, $offset);
        if ($offset + 4 > strlen($message)) {
            throw new RuntimeException('Truncated DNS question.');
        }
        $question = unpack('ntype/nclass', substr($message, $offset, 4));
        if ($question['type'] !== $type || $question['class'] !== 1) {
            throw new RuntimeException('Unexpected DNS question.');
        }
        $offset += 4;
        $addresses = [];
        for ($index = 0; $index < $header['answers']; $index++) {
            self::skipDnsName($message, $offset);
            if ($offset + 10 > strlen($message)) {
                throw new RuntimeException('Truncated DNS answer.');
            }
            $record = unpack('ntype/nclass/Nttl/nlength', substr($message, $offset, 10));
            $offset += 10;
            if ($offset + $record['length'] > strlen($message)) {
                throw new RuntimeException('Truncated DNS record.');
            }
            if ($record['class'] === 1 && $record['type'] === $type
                && $record['length'] === ($type === 1 ? 4 : 16)) {
                $addresses[] = self::normalizeAddress(inet_ntop(substr($message, $offset, $record['length'])));
            }
            $offset += $record['length'];
        }

        return $addresses;
    }

    private static function skipDnsName(string $message, int &$offset): void
    {
        $start = $offset;
        while ($offset < strlen($message)) {
            $length = ord($message[$offset++]);
            if (($length & 192) === 192) {
                if ($offset >= strlen($message) || ((($length & 63) << 8) | ord($message[$offset])) >= strlen($message)) {
                    throw new RuntimeException('Invalid DNS pointer.');
                }
                $offset++;

                return;
            }
            if ($length === 0) {
                return;
            }
            if ($length > 63 || $offset + $length > strlen($message) || $offset + $length - $start > 254) {
                throw new RuntimeException('Invalid DNS label.');
            }
            $offset += $length;
        }

        throw new RuntimeException('Truncated DNS name.');
    }

    public static function normalizeAddress(string $address): string
    {
        $address = strtolower(rtrim(trim($address), '.'));
        if (str_starts_with($address, '[') && str_ends_with($address, ']')) {
            $address = substr($address, 1, -1);
        }

        $address = explode('%', $address, 2)[0];
        if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $packed = inet_pton($address);
            if (substr($packed, 0, 12) === str_repeat("\0", 10) . "\xff\xff") {
                return inet_ntop(substr($packed, 12));
            }

            return inet_ntop($packed);
        }

        return $address;
    }

    public static function isLocalAddress(string $address): bool
    {
        $address = self::normalizeAddress($address);
        if ($address === 'localhost' || str_ends_with($address, '.localhost') || str_ends_with($address, '.local')) {
            return true;
        }

        if (!filter_var($address, FILTER_VALIDATE_IP)) {
            return false;
        }

        if (!filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return true;
        }

        $packed = inet_pton($address);
        if (strlen($packed) === 4) {
            return ord($packed[0]) === 0
                || ord($packed[0]) >= 224
                || (ord($packed[0]) === 100 && (ord($packed[1]) & 192) === 64);
        }

        return (ord($packed[0]) & 224) !== 32;
    }
}