#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers/config.php';
require_once __DIR__ . '/client/rest.php';

use Helpers\Config;
use Quixam\RestApiClient;

if (PHP_SAPI !== 'cli' || realpath($_SERVER['SCRIPT_FILENAME'] ?? '') !== __FILE__) {
    die("This script must be run from the command line.");
}

try {
    $config = new Config(__DIR__ . '/config.yaml');
    echo "Quixam REST API URL: " . $config->url . "\n";
    $token = $config->getToken();
    $api = new RestApiClient($config->url, $token);

    if ($token) {
        echo "Trying to refresh exiting token...\n";
        try {
            $newToken = $api->refreshToken();
            $config->saveToken($newToken);
            echo "Token refreshed successfully.\n";
            exit(0);
        } catch (Throwable $e) {
            echo "Existing token is invalid or expired: " . $e->getMessage() . "\n";
            $api->setAuthToken(null);
        }
    }

    $login = $config->login;
    if (!$login) {
        echo "Login: ";
        $login = trim(fgets(STDIN));
        if (!$login) {
            throw new InvalidArgumentException("Login cannot be empty.");
        }
    }

    echo "Password for '$login': ";
    $password = trim(fgets(STDIN));
    if (!$password) {
        throw new InvalidArgumentException("Password cannot be empty.");
    }

    $token = $api->loginAndGetToken($login, $password);
    $config->saveToken($token);
    echo "Login successful. Token saved in " . $config->tokenFile . ".\n";
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
