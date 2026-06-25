#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers/csv.php';

use Helpers\Csv;

if (PHP_SAPI !== 'cli' || realpath($_SERVER['SCRIPT_FILENAME'] ?? '') !== __FILE__) {
    die("This script must be run from the command line.");
}

// Verify arguments, or print usage and exit
if ($argc != 4) {
    echo "Usage: php load-answers.php <test-template-external-id> <term-external-id> <output_json_file>\n";
    echo "Invalid number of arguments.\n";
    exit(1);
}

require_once __DIR__ . '/helpers/init.php';  // creates $api and $config globals

try {
    // refresh token to ensure we have a valid one for the upload
    $config->saveToken($api->refreshToken());

    // load all terms of the test template
    $testId = $argv[1];
    echo "Fetching terms of template test {$testId} ... ";
    $terms = $api->getTerms($testId);
    echo count($terms) . " terms fetched.\n";

    // find the term with the given external ID
    $termId = $argv[2];
    $term = null;
    foreach ($terms as $t) {
        if ($t["externalId"] === $termId) {
            $term = $t;
            break;
        }
    }

    if (!$term) {
        throw new RuntimeException("Term with external ID {$argv[2]} not found.");
    }

    // print out term specifics, to be sure
    if (empty($term['finishedAt'])) {
        throw new RuntimeException("Term has not finished yet, unable to download answers.");
    }

    echo "Downloading text answers for term {$termId} ...\n";
    $answers = $api->getTermAnswers($term['id']);

    echo "Saving answers to {$argv[3]} ...\n";
    file_put_contents($argv[3], json_encode($answers, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    echo "Done.\n";
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
