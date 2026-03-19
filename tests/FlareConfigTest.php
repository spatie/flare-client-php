<?php

use Spatie\FlareClient\FlareConfig;
use Spatie\FlareClient\Senders\CurlSender;
use Spatie\FlareClient\Senders\DaemonSender;

it('configures the daemon transport through sendUsing', function () {
    $config = FlareConfig::make('fake-api-key')
        ->sendUsing(DaemonSender::class, [
            'daemon_url' => 'http://127.0.0.1:9999',
        ]);

    expect($config->sender)->toBe(DaemonSender::class)
        ->and($config->senderConfig)->toBe([
            'daemon_url' => 'http://127.0.0.1:9999',
        ]);
});

it('replaces prior sender config when switching to the daemon transport', function () {
    $config = FlareConfig::make('fake-api-key')
        ->sendUsing(CurlSender::class, ['timeout' => 7])
        ->sendUsing(DaemonSender::class, [
            'daemon_url' => 'http://127.0.0.1:8787',
        ]);

    expect($config->senderConfig)->toBe([
        'daemon_url' => 'http://127.0.0.1:8787',
    ]);
});

it('preserves custom daemon sender config passed to sendUsing', function () {
    $config = FlareConfig::make('fake-api-key')->sendUsing(DaemonSender::class, [
        'daemon_url' => 'http://127.0.0.1:8788',
        'timeout' => 3,
        'test_timeout' => 12,
        'fallback_sender_config' => ['timeout' => 9],
    ]);

    expect($config->senderConfig)->toBe([
        'daemon_url' => 'http://127.0.0.1:8788',
        'timeout' => 3,
        'test_timeout' => 12,
        'fallback_sender_config' => ['timeout' => 9],
    ]);
});
