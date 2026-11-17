<?php

require __DIR__ . '/../resources/RegionLookupService.php';

use Pterodactyl\Services\Region\RegionLookupService;

$checks = 0;
$check = function (bool $condition, string $message) use (&$checks): void {
    $checks++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

foreach (['0.0.0.0', '0.1.2.3', '127.0.0.1', '10.0.0.1', '172.16.0.1', '172.31.255.255', '192.168.1.1', '169.254.1.1', '100.64.0.1', '100.127.255.255', '224.0.0.1', '255.255.255.255', '::', '::1', '[::1]', 'fc00::1', 'fd12::1', 'fe80::1%eth0', 'ff02::1', '::ffff:192.168.1.1', '::ffff:c0a8:101', 'localhost', 'LOCALHOST.', 'server.localhost', 'server.local'] as $address) {
    $check(RegionLookupService::isLocalAddress($address), 'Expected local/reserved: ' . $address);
}

foreach (['1.1.1.1', '9.9.9.9', '8.8.8.8', '172.15.0.1', '172.32.0.1', '100.63.255.255', '100.128.0.1', '2001:4860:4860::8888', '2606:4700:4700::1111', '::ffff:8.8.8.8', 'example.com'] as $address) {
    $check(!RegionLookupService::isLocalAddress($address), 'Expected public or hostname: ' . $address);
}

$parser = new ReflectionMethod(RegionLookupService::class, 'parseDnsResponse');
$question = "\7example\3com\0" . pack('nn', 1, 1);
$cname = "\6target\7example\3com\0";
$message = pack('nnnnnn', 42, 33152, 1, 2, 0, 0) . $question
    . "\xc0\x0c" . pack('nnNn', 5, 1, 300, strlen($cname)) . $cname
    . "\xc0\x0c" . pack('nnNn', 1, 1, 300, 4) . inet_pton('1.1.1.1');
$check($parser->invoke(null, $message, 42, 1) === ['1.1.1.1'], 'CNAME must not be used as an IP.');
$ipv6 = pack('nnnnnn', 42, 33152, 1, 1, 0, 0) . "\7example\3com\0" . pack('nn', 28, 1)
    . "\xc0\x0c" . pack('nnNn', 28, 1, 300, 16) . inet_pton('2606:4700:4700::1111');
$check($parser->invoke(null, $ipv6, 42, 28) === ['2606:4700:4700::1111'], 'AAAA records must work.');
foreach (['', substr($message, 0, -1), pack('nnnnnn', 42, 33155, 1, 0, 0, 0) . $question, pack('nnnnnn', 42, 33664, 1, 0, 0, 0) . $question] as $invalid) {
    try {
        $parser->invoke(null, $invalid, 42, 1);
        $check(false, 'Malformed DNS message accepted.');
    } catch (RuntimeException $exception) {
        $check(true, 'Rejected malformed DNS message.');
    }
}
try {
    $parser->invoke(null, $message, 99, 1);
    $check(false, 'Mismatched DNS ID accepted.');
} catch (RuntimeException $exception) {
    $check(true, 'Rejected mismatched ID.');
}

echo "Passed {$checks} checks.\n";