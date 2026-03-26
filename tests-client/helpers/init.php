<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../client/interface.php';
require_once __DIR__ . '/../client/rest.php';

use Helpers\Config;
use Quixam\RestApiClient;

try {
    $config = new Config(__DIR__ . '/../config.yaml');
    echo "Quixam REST API URL: " . $config->url . "\n";
    $api = new RestApiClient($config->url, $config->getToken());
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
