<?php

use Kei\Lwphp\Security\CryptoSignatureService;

test('CryptoSignatureService successfully generates and verifies valid HMAC-SHA256 signatures', function () {
    $key = 'test_secret_key_123';
    $service = new CryptoSignatureService($key);

    $payload = '{"jsonrpc": "2.0", "method": "testMethod", "params": {"id": 1}}';
    $salt = bin2hex(random_bytes(16));

    $signature = $service->generateSignature($payload, $salt);

    expect($service->verifySignature($payload, $salt, $signature))->toBeTrue();
});

test('CryptoSignatureService securely rejects tampered payload signatures', function () {
    $key = 'test_secret_key_123';
    $service = new CryptoSignatureService($key);

    $payload = '{"jsonrpc": "2.0", "method": "testMethod", "params": {"id": 1}}';
    $tamperedPayload = '{"jsonrpc": "2.0", "method": "testMethod", "params": {"id": 2}}';
    $salt = bin2hex(random_bytes(16));

    $signature = $service->generateSignature($payload, $salt);

    // Attempt verification with the wrong payload
    expect($service->verifySignature($tamperedPayload, $salt, $signature))->toBeFalse();

    // Attempt verification with the wrong salt
    expect($service->verifySignature($payload, 'wrong_salt', $signature))->toBeFalse();
});
