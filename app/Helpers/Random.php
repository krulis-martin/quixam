<?php

declare(strict_types=1);

namespace App\Helpers;

use InvalidArgumentException;

/**
 * Wraps more complex random operations.
 */
class Random
{
    /**
     * Initializes seed for internal random generator.
     * @param int $seed
     */
    public static function setSeed(int $seed): void
    {
        mt_srand($seed);
    }

    /**
     * Shuffle items in the array (inplace), keys are lost.
     * @param array $array
     */
    public static function shuffleArray(array &$array): void
    {
        $array = array_values($array);
        for ($i = 0; $i < count($array) - 1; ++$i) {
            $j = mt_rand($i, count($array) - 1);
            if ($i !== $j) {
                $tmp = $array[$i];
                $array[$i] = $array[$j];
                $array[$j] = $tmp;
            }
        }
    }

    /**
     * Constructs a symmetric index from mutually exclusive list (with verification of keys).
     * @param array $data initial items to select from (used here only to verify keys)
     * @param array $mutuallyExclusive array of pairs [ [ key1, key2 ] ] of data items that are mutually exclusive
     *                                 (i.e., they cannot be both selected in the output)
     * @return array[] symmetric map represented as [ key1 => [key2 => true] ]
     * @throws InvalidArgumentException if the list does not have the right format or keys do not exist
     */
    private static function prepareMutexIndex(array $data, array $mutuallyExclusive): array
    {
        $index = [];
        foreach ($data as $key => $_) {
            $index[$key] = [];
        }

        foreach ($mutuallyExclusive as $indices) {
            if (!is_array($indices) || count($indices) !== 2) {
                throw new InvalidArgumentException("Mutually exclusive list is supposed to be a list of index pairs.");
            }
            [ $key1, $key2 ] = $indices;
            if (!array_key_exists($key1, $data) || !array_key_exists($key2, $data)) {
                throw new InvalidArgumentException("Invalid refrence found in mutually exclusive list [$key1, $key2].");
            }

            $index[$key1][$key2] = true;
            $index[$key2][$key1] = true;
        }

        return $index;
    }

    /**
     * Remove a key from mutually exclusive index.
     * @param array $mutindex a ref. to the index to be updated
     * @param string|int $key to be removed
     */
    private static function removeMutexKey(array &$mutindex, $key): void
    {
        if (!empty($mutindex[$key])) {
            foreach ($mutindex[$key] as $key2) {
                unset($mutindex[$key2][$key]);
            }
            unset($mutindex[$key]);
        }
    }

    /**
     * @param array $keys remaining candidates
     * @param array $mutindex a ref. to the index to be updated if necessary
     * @param int $required how many items from keys still needs to be selected
     * @return array filtered keys
     */
    private static function filterBadCandidates(array $keys, array &$mutindex, int $required): array
    {
        if ($required <= 0) {
            return $keys; // perf. optimization, there is no need to do this anymore
        }

        $count = count($keys);
        $result = [];
        foreach ($keys as $key) {
            if ($count - count($mutindex[$key]) < $required) {
                self::removeMutexKey($mutindex, $key);
                --$count;
            } else {
                $result[] = $key;
            }
        }

        return $result;
    }

    /**
     * Selects given number as a subset from given array. The output array is shuffled at the end.
     * Note: the algorithm is greedy and employs only basic heuristic to avoid dead ends.
     * If the mutex rules are too strict, it might happen it will not find correct subset even if it exists.
     * @param array $data initial items to select from (works both with lists and associative arrays)
     * @param int $count how many items are selected
     * @param array $preselected indices of $data items that must appear in the output
     * @param array $mutuallyExclusive array of pairs [ [ idx1, idx2 ] ] of data items that are mutually exclusive
     *                                 (i.e., they cannot be both selected in the output)
     * @return array of selected items from $data (keys are preserved)
     * @throws InvalidArgumentException if the parameter configuration prevents from selecting the subset
     */
    public static function selectRandomSubset(
        array $data,
        int $count,
        array $preselected = [],
        array $mutuallyExclusive = []
    ): array {
        // sanity checks
        if ($count > count($data)) {
            throw new InvalidArgumentException("Unable to select $count items from a list of " . count($data));
        }
        if ($count < count($preselected)) {
            throw new InvalidArgumentException("Unable to select only $count items, when " . count($preselected)
                . " are already pre-selected.");
        }

        $keys = array_keys($data);
        $mutindex = self::prepareMutexIndex($data, $mutuallyExclusive);
        $resultKeys = [];

        while (count($resultKeys) < $count) {
            if (count($keys) < $count - count($resultKeys)) {
                throw new InvalidArgumentException("Rules for mutual exclusion for random selection are too strict.");
            }

            // add another item to the result
            if ($preselected) {
                // we have still preselected keys
                $key = array_shift($preselected);
                if (!array_key_exists($key, $mutindex)) {
                    throw new InvalidArgumentException("Invalid key found in preselect argument for random selection.");
                }
            } else {
                // no preselected key -> choose randomly
                $key = $keys[ mt_rand(0, count($keys) - 1) ];
            }
            $resultKeys[] = $key;

            // remove mutually exclusive items from the remaining candidates
            $keys = array_filter($keys, function ($k) use ($key, $mutindex) {
                return $key !== $k && empty($mutindex[$key][$k]);
            });

            // update the mutually exclusive index
            self::removeMutexKey($mutindex, $key);

            // remove candidates that would immediately lead to a dead end
            $keys = self::filterBadCandidates($keys, $mutindex, $count - count($resultKeys));
        }

        // assemble the result
        self::shuffleArray($resultKeys); // extra shuffle is necessare due to preselected keys
        $result = [];
        foreach ($resultKeys as $key) {
            $result[$key] = $data[$key];
        }
        return $result;
    }
}
