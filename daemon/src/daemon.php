<?php

require __DIR__.'/../bootstrap.php';

use React\EventLoop\Loop as EventLoop;
use Spatie\FlareDaemon\CheckForUpdates;
use Spatie\FlareDaemon\Clock;
use Spatie\FlareDaemon\Factories\BrowserFactory;
use Spatie\FlareDaemon\Ingest;
use Spatie\FlareDaemon\Loop;
use Spatie\FlareDaemon\OutputWriter;
use Spatie\FlareDaemon\Server;
use Spatie\FlareDaemon\UsageRepository;

// --- Configuration ---

$apiKey = getenv('FLARE_API_KEY');

if ($apiKey === false || $apiKey === '') {
    fwrite(STDERR, "FLARE_API_KEY environment variable is required\n");

    exit(1);
}

$baseUrl = getenv('FLARE_BASE_URL') ?: 'https://ingress.flareapp.io';
$listenAddress = getenv('FLARE_DAEMON_LISTEN') ?: '127.0.0.1:8787';
$logLevel = getenv('FLARE_DAEMON_LOG_LEVEL') ?: 'info';
$composerLockPath = getenv('FLARE_COMPOSER_LOCK') ?: '';

$validLogLevels = ['critical', 'error', 'info', 'verbose'];

if (! in_array($logLevel, $validLogLevels, true)) {
    fwrite(STDERR, "Invalid FLARE_DAEMON_LOG_LEVEL: {$logLevel}. Valid values: " . implode(', ', $validLogLevels) . "\n");

    exit(1);
}

// --- Setup ---

$loop = new Loop(EventLoop::get());
$output = new OutputWriter($loop);
$clock = new Clock();

$output->writeLn("Flare Daemon starting...");
$output->writeLn("Log level: {$logLevel}");

$browserFactory = new BrowserFactory($loop);
$browser = $browserFactory->create();

$ingest = new Ingest($loop, $browser, $output, $apiKey, $baseUrl);

$usageRepository = new UsageRepository($loop, $browser, $output, $ingest, $clock, $apiKey, $baseUrl);

$server = new Server(
    $loop,
    $output,
    function (string $type, string $data) use ($ingest): void {
        $ingest->buffer($type, $data);
    },
    function (string $baseType, string $data, \React\Socket\ConnectionInterface $connection) use ($ingest): void {
        $ingest->bufferTest($baseType, $data, $connection);
    },
    function () use ($ingest, $usageRepository): array {
        $status = $ingest->status();
        $usage = $usageRepository->usage();

        if ($usage !== null) {
            $status['usage'] = [
                'errors_used' => $usage->errorsUsed,
                'errors_limit' => $usage->errorsLimit,
                'traces_used' => $usage->tracesUsed,
                'traces_limit' => $usage->tracesLimit,
                'logs_used' => $usage->logsUsed,
                'logs_limit' => $usage->logsLimit,
            ];
        }

        return $status;
    },
);

// --- Start ---

$ingest->startFlushTimers();
$usageRepository->start();
$server->listen($listenAddress);

if ($composerLockPath !== '') {
    $checkForUpdates = new CheckForUpdates($loop, $output, $ingest, $composerLockPath);
    $checkForUpdates->start();

    $output->writeLn("Watching {$composerLockPath} for changes");
}

// --- Signal handling ---

$shutdown = function () use ($loop, $output, $ingest, $server): void {
    $output->writeLn("Received shutdown signal — flushing buffers...");

    $server->close();

    $ingest->forceDigest()->then(function () use ($loop, $output): void {
        $output->writeLn("All buffers flushed — stopping loop");
        $loop->stop();
    });
};

if (function_exists('pcntl_signal')) {
    $loop->addSignal(SIGTERM, $shutdown);
    $loop->addSignal(SIGINT, $shutdown);
}

$output->writeLn("Flare Daemon ready");

$loop->run();
