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
    echo "Usage: php register-students.php <test-template-external-id> <term-external-id> <path_to_csv_file>\n";
    echo "Invalid number of arguments.\n";
    exit(1);
}
if (!file_exists($argv[3]) || !is_readable($argv[3]) || !is_file($argv[3])) {
    echo "Usage: php register-students.php <test-template-external-id> <term-external-id> <path_to_csv_file>\n";
    echo "File {$argv[3]} does not exist or is not readable.\n";
    exit(1);
}

require_once __DIR__ . '/helpers/init.php';  // creates $api and $config globals

try {
    // refresh token to ensure we have a valid one for the upload
    $config->saveToken($api->refreshToken());

    // load input CSV file
    echo "Loading CSV file {$argv[3]} ... ";
    $csv = new Csv([
        'externalId' => function ($v) {
            return trim($v);
        },
        'email' => function ($v) {
            return trim($v);
        },
    ], true);
    $csv->load($argv[3]);
    if ($csv->count() === 0) {
        throw new RuntimeException("No students found in the CSV file.");
    }
    echo $csv->count() . " rows loaded.\n";

    foreach ($csv as $row) {
        if (!$row->externalId && !$row->email) {
            throw new RuntimeException("Each student must have at least an external ID or an email.");
        }
    }

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
    echo "Term '$termId' found with ID={$term['id']}";
    if (!empty($term['scheduledAt'])) {
        $scheduledAt = (new DateTime())->setTimestamp($term['scheduledAt']);
        echo " scheduled at {$scheduledAt->format('Y-m-d H:i')}";
    }
    if (!empty($term['location'])) {
        echo " located in {$term['location']}";
    }
    echo "\n";

    if (!empty($term['archivedAt'])) {
        throw new RuntimeException("Term is already archived, unable to modify.");
    }
    if (!empty($term['finishedAt'])) {
        throw new RuntimeException("Term has already finished, unable to register students.");
    }

    // prepare students data and register them to the term
    echo "Registering students to the term ... ";
    $students = [];
    foreach ($csv as $row) {
        $students[] = [
            'externalId' => $row->externalId ?: null,
            'email' => $row->email ?: null,
        ];
    }
    $api->registerUsers($term['id'], $students);

    echo "Done.\n";
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
