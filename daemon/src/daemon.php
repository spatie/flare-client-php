#!/usr/bin/env php
<?php

declare(strict_types=1);

use React\EventLoop\Loop;
use React\Http\Browser;
use Spatie\FlareDaemon\CheckForUpdates;
use Spatie\FlareDaemon\Ingest;
use Spatie\FlareDaemon\QuotaState;
use Spatie\FlareDaemon\Server;
use Spatie\FlareDaemon\Support\Output;
use Spatie\FlareDaemon\Upstream;

require dirname(__DIR__).'/vendor/autoload.php';

$verbose = in_array('-v', $argv ?? [], true) || in_array('--verbose', $argv ?? [], true);
$output = new Output(verbose: $verbose);

$listenAddress = getenv('FLARE_DAEMON_LISTEN') ?: '127.0.0.1:8787';
$upstreamBaseUrl = getenv('FLARE_DAEMON_UPSTREAM') ?: 'https://ingress.flareapp.io';
$bufferBytes = (int) (getenv('FLARE_DAEMON_BUFFER_BYTES') ?: 262144);
$flushAfterSeconds = (float) (getenv('FLARE_DAEMON_FLUSH_AFTER_SECONDS') ?: 10);
$upstreamTimeout = (float) (getenv('FLARE_DAEMON_UPSTREAM_TIMEOUT_SECONDS') ?: 10);
$version = getenv('FLARE_DAEMON_VERSION') ?: 'dev';

$browser = (new Browser())
    ->withRejectErrorResponse(false)
    ->withTimeout($upstreamTimeout);

$loop = Loop::get();
$quotaState = new QuotaState();
$upstream = new Upstream($browser, $upstreamBaseUrl, "FlareDaemon/{$version}");
$ingest = new Ingest($loop, $upstream, $output, $quotaState, $bufferBytes, $flushAfterSeconds);
$server = new Server($loop, $ingest, $output, $listenAddress);

try {
    $server->listen();
} catch (RuntimeException $e) {
    $ingest->shutdown(fn () => $loop->stop());
    exit(1);
}

$stop = function (string $reason) use ($output, $server, $ingest): void {
    $output->info('daemon stopping', ['reason' => $reason]);
    $server->stop();
    $ingest->shutdown(fn () => Loop::stop());
};

if (defined('SIGINT')) {
    Loop::addSignal(SIGINT, fn () => $stop('SIGINT'));
}

if (defined('SIGTERM')) {
    Loop::addSignal(SIGTERM, fn () => $stop('SIGTERM'));
}

$composerLock = getenv('FLARE_COMPOSER_LOCK');

if (is_string($composerLock) && $composerLock !== '') {
    (new CheckForUpdates(
        loop: $loop,
        composerLockPath: $composerLock,
        output: $output,
        onChange: fn () => $stop('composer.lock changed'),
    ))->start();
}

$startContext = [
    'listen_address' => $listenAddress,
    'upstream' => $upstreamBaseUrl,
];

if (! $verbose) {
    $startContext['hint'] = 'use --verbose for detailed request logging';
}

$output->info('daemon started', $startContext);

Loop::run();

$output->info('daemon stopped');
