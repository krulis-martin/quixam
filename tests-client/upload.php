#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/parser/autoload.php';
require_once __DIR__ . '/helpers/init.php';  // creates $api and $config globals

if (empty($argv[1])) {
    echo "Usage: upload.php <path-to-test-config.yaml>\n";
    exit(0);
}

if (!file_exists($argv[1]) || !is_readable($argv[1]) || !is_file($argv[1])) {
    echo "File {$argv[1]} does not exist or is not readable.\n";
    exit(0);
}

try {
    // refresh token to ensure we have a valid one for the upload
    $config->saveToken($api->refreshToken());

    $quixam = new Quixam\Adapter($api);
    $quixam->loadConfig($argv[1]);
    $quixam->upload();
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
