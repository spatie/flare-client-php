<?php

/**
 * Daemon wrapper for subprocess-based integration tests.
 *
 * Usage: php daemon-wrapper.php
 *
 * Environment variables:
 *   FLARE_API_KEY - Required API key
 *   FLARE_BASE_URL - Base URL for Flare API (default: https://ingress.flareapp.io)
 *   FLARE_DAEMON_LISTEN - Listen address (default: 127.0.0.1:8787)
 *   FLARE_DAEMON_LOG_LEVEL - Log level (default: info)
 *   FLARE_COMPOSER_LOCK - Path to composer.lock for update monitoring
 *   FLARE_DAEMON_TEST_MODE - When set, daemon auto-stops after 5 seconds
 */

$autoloadPaths = [
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/../../vendor/autoload.php',
];

$loaded = false;

foreach ($autoloadPaths as $autoload) {
    if (file_exists($autoload)) {
        require $autoload;
        $loaded = true;

        break;
    }
}

if (! $loaded) {
    fwrite(STDERR, "Could not find vendor/autoload.php\n");

    exit(1);
}

// In test mode, auto-stop after a timeout to prevent hanging tests
$testMode = getenv('FLARE_DAEMON_TEST_MODE');

if ($testMode !== false && $testMode !== '') {
    $timeout = (int) $testMode ?: 5;

    // Register a shutdown timer after the loop starts
    register_shutdown_function(function (): void {
        // Cleanup
    });
}

require __DIR__ . '/../src/daemon.php';
