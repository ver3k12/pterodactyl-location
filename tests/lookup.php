<?php

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../resources/RegionLookupService.php';

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Pterodactyl\Services\Region\RegionLookupService;

$checks = 0;
$check = function (bool $condition, string $message) use (&$checks): void {
    $checks++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$history = [];
$mock = new MockHandler();
$stack = HandlerStack::create($mock);
$stack->push(Middleware::history($history));
$cache = new Repository(new ArrayStore());
$service = new RegionLookupService(new Client(['handler' => $stack]), $cache);
$success = fn (string $address = '1.1.1.1') => new Response(200, [], json_encode([
    'ip' => $address, 'country' => 'Australia', 'country_code' => 'au', 'city' => 'Brisbane',
], JSON_THROW_ON_ERROR));

foreach (['0.0.0.0', '127.0.0.1', '192.168.1.1', '::', '::1', '[::1]', '::ffff:10.1.2.3', 'fd12::1', 'localhost', 'host.local'] as $address) {
    $result = $service->lookup($address);
    $check($result['status'] === 'local' && $result['country_name'] === 'LocalHost', 'LocalHost result: ' . $address);
}
$check($history === [], 'Local addresses must never cause network requests.');
foreach (['', 'not an address', 'https://example.com', '1.1.1.1/path', '999.999.999.999:80'] as $invalid) {
    $check($service->lookup($invalid)['status'] === 'invalid', 'Invalid input: ' . $invalid);
}
$check($history === [], 'Invalid addresses must never cause network requests.');

$mock->append($success());
$result = $service->lookup('1.1.1.1');
$check($result['status'] === 'success' && $result['country_code'] === 'AU', 'API V2 response mapping.');
$query = [];
parse_str($history[0]['request']->getUri()->getQuery(), $query);
$check($history[0]['request']->getUri()->getPath() === '/api/v2/lookup' && $query['ip'] === '1.1.1.1' && $query['checkHostname'] === 'false', 'Only explicit V2 lookups are allowed.');
$service->lookup('1.1.1.1');
$service->lookup('1.1.1.1', 'DNS-Quad9');
$check(count($history) === 1, 'Cache must be shared between resolvers for the same public IP.');

foreach ([new Response(503), new Response(200, [], '{broken'), new Response(200, [], '{"ip":"9.9.9.9"}'), new Response(200, [], 'null'), new ConnectException('Timed out', new Request('GET', 'https://ipinfo.spacehost.ovh'))] as $failure) {
    $cache->flush();
    $mock->append($failure);
    $before = count($history);
    $check($service->lookup('1.1.1.1')['status'] === 'unavailable', 'Failure must return a stable unavailable state.');
    $service->lookup('1.1.1.1');
    $check(count($history) === $before + 1, 'Failures must be cached briefly.');
}

$cache->flush();
$mock->append(new Response(429, ['Retry-After' => '60']));
$check($service->lookup('1.1.1.1')['status'] === 'rate_limited', '429 status.');
$before = count($history);
$check($service->lookup('9.9.9.9')['status'] === 'rate_limited', 'Global API cooldown.');
$check(count($history) === $before, 'Cooldown must prevent further API calls.');

$dnsResponse = function ($request, string $address): Response {
    $body = (string) $request->getBody();
    $questionType = unpack('ntype', substr($body, -4, 2))['type'];
    $packed = inet_pton($address);
    $expectedType = strlen($packed) === 4 ? 1 : 28;

    return new Response(200, [], substr($body, 0, 2) . pack('nnnnn', 33152, 1, $questionType === $expectedType ? 1 : 0, 0, 0)
        . substr($body, 12)
        . ($questionType === $expectedType ? "\xc0\x0c" . pack('nnNn', $questionType, 1, 60, strlen($packed)) . $packed : ''));
};
foreach (['DNS-Google' => 'dns.google', 'DNS-Cloudflare' => 'cloudflare-dns.com', 'DNS-OpenDNS' => 'doh.opendns.com', 'DNS-Quad9' => 'dns.quad9.net'] as $provider => $host) {
    $cache->flush();
    $mock->append(fn ($request) => $dnsResponse($request, '1.1.1.1'), $success());
    $before = count($history);
    $check($service->lookup('example.com', $provider)['status'] === 'success', 'DNS provider: ' . $provider);
    $check($history[$before]['request']->getUri()->getHost() === $host, 'Selected resolver endpoint: ' . $provider);
    $check($history[$before]['request']->getProtocolVersion() === '2.0', 'DoH requires HTTP/2: ' . $provider);
}

$cache->flush();
$mock->append(fn ($request) => $dnsResponse($request, '::1'));
$mock->append(fn ($request) => $dnsResponse($request, '::1'));
$before = count($history);
$check($service->lookup('private.example.com')['status'] === 'local', 'AAAA-only local hostname.');
$check(count($history) === $before + 2, 'Local DNS result must not reach geolocation API.');

$cache->flush();
$mock->append(new Response(200, [], 'broken'));
$before = count($history);
$check($service->lookup('broken.example.com')['status'] === 'unavailable', 'Malformed DNS must fail closed.');
$check(count($history) === $before + 1, 'DNS failure must not trigger an implicit IP lookup.');

echo "Passed {$checks} integration checks.\n";