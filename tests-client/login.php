#!/usr/bin/env php
<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli' || realpath($_SERVER['SCRIPT_FILENAME'] ?? '') !== __FILE__) {
    die("This script must be run from the command line.");
}

require_once __DIR__ . '/helpers/init.php';  // creates $api and $config globals

try {
    $token = $api->getAuthToken();
    if ($token) {
        echo "Trying to refresh exiting token...\n";
        try {
            $newToken = $api->refreshToken();
            $config->saveToken($newToken);
            echo "Token refreshed successfully and saved in " . $config->tokenFile . ".\n";
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
