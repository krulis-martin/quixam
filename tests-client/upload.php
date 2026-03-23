#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers/config.php';
require_once __DIR__ . '/parser/autoload.php';
require_once __DIR__ . '/client/interface.php';
require_once __DIR__ . '/client/rest.php';

use Helpers\Config;
use Quixam\RestApiClient;

if (PHP_SAPI !== 'cli' || realpath($_SERVER['SCRIPT_FILENAME'] ?? '') !== __FILE__) {
    die("This script must be run from the command line.");
}

if (empty($argv[1])) {
    echo "Usage: upload.php <path-to-test-config.yaml>\n";
    exit(0);
}

if (!file_exists($argv[1]) || !is_readable($argv[1]) || !is_file($argv[1])) {
    echo "File {$argv[1]} does not exist or is not readable.\n";
    exit(0);
}

try {
    // init
    $config = new Config(__DIR__ . '/config.yaml');
    echo "Quixam REST API URL: " . $config->url . "\n";
    $token = $config->getToken();
    $api = new RestApiClient($config->url, $token);
    $newToken = $api->refreshToken();
    $config->saveToken($newToken);

    $quixam = new Quixam\Adapter($api);
    $quixam->loadConfig($argv[1]);
    $quixam->upload();
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
