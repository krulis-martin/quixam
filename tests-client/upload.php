#!/usr/bin/env php
<?php

declare(strict_types=1);

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

require_once __DIR__ . '/parser/autoload.php';
require_once __DIR__ . '/helpers/init.php';  // creates $api and $config globals

try {
    // refresh token to ensure we have a valid one for the upload
    $config->saveToken($api->refreshToken());

    $quixam = new Quixam\Adapter($api);
    $quixam->loadConfig($argv[1]);
    $quixam->upload();
    echo "\e[32mDone.\e[0m\n";
} catch (Throwable $e) {
    fwrite(STDERR, "\e[1;31mError: \e[0m" . $e->getMessage() . "\n");
    echo "-----\n";
    echo "\e[31mUpload terminated with errors! The test template may be only partially uploaded!\e[0m\n";
    exit(1);
}
