<?php

use Spatie\FlareClient\FlareConfig;
use Spatie\FlareClient\Senders\DaemonSender;

it('can configure a custom sender with sendUsing', function () {
    $config = FlareConfig::make('fake-api-key')
        ->sendUsing(DaemonSender::class, [
            'daemon_url' => 'http://127.0.0.1:8787',
        ]);

    expect($config->sender)->toBe(DaemonSender::class)
        ->and($config->senderConfig)->toBe([
            'daemon_url' => 'http://127.0.0.1:8787',
        ]);
});
