#!/usr/bin/env php
<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli' || realpath($_SERVER['SCRIPT_FILENAME'] ?? '') !== __FILE__) {
    die("This script must be run from the command line.");
}

// Verify arguments, or print usage and exit
if ($argc != 2) {
    echo "Usage: php grade-answers.php <input_json_file>\n";
    echo "Invalid number of arguments.\n";
    exit(1);
}
if (!file_exists($argv[1]) || !is_readable($argv[1]) || !is_file($argv[1])) {
    echo "Usage: php grade-answers.php <input_json_file>\n";
    echo "File {$argv[1]} does not exist or is not readable.\n";
    exit(1);
}

require_once __DIR__ . '/helpers/init.php';  // creates $api and $config globals

try {
    // refresh token to ensure we have a valid one for the upload
    $config->saveToken($api->refreshToken());

    // load input JSON file
    echo "Loading JSON file {$argv[1]} ...\n";
    $json = json_decode(file_get_contents($argv[1]), true);
    if (!is_array($json)) {
        throw new RuntimeException("Invalid JSON file: must be an array of answers.");
    }

    foreach ($json as $answer) {
        if (!isset($answer['id'])) {
            throw new RuntimeException("Each answer must have an 'id' field.");
        }

        echo "Grading answer {$answer['id']} ... ";

        // evaluationCs, evaluationEn, correctness
        $privateComment = null;
        $correctness = null;
        if (array_key_exists('evaluationCs', $answer) && $answer['evaluationCs'] !== null) {
            $privateComment = (string)$answer['evaluationCs'];
        }
        if (array_key_exists('evaluationEn', $answer) && $answer['evaluationEn'] !== null) {
            if ($privateComment !== null) {
                $privateComment .= "\n\n-----\n\n";
            } else {
                $privateComment = "";
            }
            $privateComment .= (string)$answer['evaluationEn'];
        }
        if (array_key_exists('correctness', $answer) && $answer['correctness'] !== null) {
            $correctness = (float)$answer['correctness'];
        }

        if ($privateComment === null && $correctness === null) {
            echo "SKIPPING (no comment or correctness provided).\n";
            continue;
        }

        $changed = $api->gradeAnswer($answer['id'], null, $privateComment, null, $correctness);
        echo "OK", (!$changed ? " (nothing changed)" : ""), ".\n";
    }

    echo "Done.\n";
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
