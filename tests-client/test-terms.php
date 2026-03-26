#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers/csv.php';

use Helpers\Csv;

if (PHP_SAPI !== 'cli' || realpath($_SERVER['SCRIPT_FILENAME'] ?? '') !== __FILE__) {
    die("This script must be run from the command line.");
}

// Verify arguments, or print usage and exit
if ($argc != 3) {
    echo "Usage: php test-terms.php <test-template-external-id> <path_to_csv_file>\n";
    echo "Invalid number of arguments ({$argc}).\n";
    exit(1);
}
if (!file_exists($argv[2]) || !is_readable($argv[2]) || !is_file($argv[2])) {
    echo "Usage: php test-terms.php <test-template-external-id> <path_to_csv_file>\n";
    echo "File {$argv[2]} does not exist or is not readable.\n";
    exit(1);
}

require_once __DIR__ . '/helpers/init.php';  // creates $api and $config globals

try {
    // refresh token to ensure we have a valid one for the upload
    $config->saveToken($api->refreshToken());

    // load input CSV file
    echo "Loading CSV file {$argv[2]} ... ";
    $csv = new Csv([
        'externalId' => function ($value) {
            $value = trim($value);
            if (strlen($value) === 0) {
                throw new InvalidArgumentException("External ID cannot be empty.");
            }
            return $value;
        },
        'location' => 'string',
        'scheduledAt' => 'DateTime',
        'supervisors' => function ($value) {
            $value = trim($value);
            return $value ? preg_split('/\s+/', $value, -1, PREG_SPLIT_NO_EMPTY) : [];
        },
        'note_en' => 'string',
        'note_cs' => 'string'
    ], ['externalId']);
    $csv->load($argv[2]);
    echo $csv->count() . " rows loaded.\n";

    // load all terms of the test template
    $testId = $argv[1];
    echo "Fetching terms of template test {$testId} ... ";
    $terms = [];
    foreach ($api->getTerms($testId) as $term) {
        if (!$term['externalId']) {
            echo "Warning: term with ID {$term['id']} does not have an external ID and will be ignored.\n";
            continue;
        }
        $terms[$term['externalId']] = $term;
    }
    echo count($terms) . " terms fetched.\n";

    // create or update terms according to the CSV data
    $processed = [];
    foreach ($csv->getRows() as $row) {
        // log what we are processing
        echo "Processing term '{$row->externalId}' ";
        if ($row->scheduledAt) {
            echo "scheduled {$row->scheduledAt->format('Y-m-d H:i:s')} ";
        }
        if ($row->location) {
            echo "in '{$row->location}' ";
        }
        echo "... ";

        if (array_key_exists($row->externalId, $processed)) {
            echo "[SKIPPING] (duplicate external ID in CSV)\n";
            continue;
        }
        $processed[$row->externalId] = true;

        // check current status of the term
        $existingTerm = $terms[$row->externalId] ?? null;
        unset($terms[$row->externalId]); // terms that remain should be deleted later

        if ($existingTerm && $existingTerm['startedAt'] !== null) {
            echo "[SKIPPING] (already started, unable to modify)\n";
            continue;
        }
        if ($existingTerm && $existingTerm['archivedAt'] !== null) {
            echo "[SKIPPING] (term was archived, unable to modify)\n";
            continue;
        }

        // add or update the term
        try {
            $api->addTerm(
                $testId,
                $row->externalId,
                $row->location ?? null,
                $row->scheduledAt ?? null,
                $row->supervisors ?? [],
                $row->note_en ?? null,
                $row->note_cs ?? null
            );
        } catch (Throwable $e) {
            echo "[ERROR] " . $e->getMessage() . "\n";
            continue;
        }

        echo ($existingTerm ? "[UPDATED]" : "[ADDED]") . "\n";
    }

    // delete terms that are not present in the CSV
    foreach ($terms as $externalId => $term) {
        echo "Term '{$externalId}' exists, but not in CSV. Removing ... ";
        if ($term['startedAt'] !== null) {
            echo "[SKIPPING] (already started, unable to remove)\n";
            continue;
        }
        if ($term['archivedAt'] !== null) {
            echo "[SKIPPING] (term was archived, unable to remove)\n";
            continue;
        }

        try {
            $api->removeTerm($term['id']);
            echo "[DELETED]\n";
        } catch (Throwable $e) {
            echo "[ERROR] " . $e->getMessage() . "\n";
        }
    }

    echo "Done.\n";
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
