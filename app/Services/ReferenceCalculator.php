<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

class ReferenceCalculator
{
    /**
     * Calculate reference score.
     *
     * @param int $node_id
     * @param array $nodes
     * @param string $gender
     * @return mixed
     */
    public function calculateReference(int $node_id, array $nodes, string $gender)
    {
        $value = null;

        // Parse value in correct format
        if ($nodes['x']['value'] !== '' && $nodes['y']['value'] !== '') {
            $x = $this->parseValue($nodes['current'], $nodes['x']);
            $y = $this->parseValue($nodes['current'], $nodes['y']);
            $z = isset($nodes['z']) ? $this->parseValue($nodes['current'], $nodes['z']) : null;
            $reference = $this->getReferenceTable($nodes['current'], $gender);
            if ($reference !== null && $z === null) {
                $value = $this->processReferenceTable($reference, $x, $y);
            } elseif ($reference !== null && $z !== null) {
                $value = $this->processReferenceTable3D($reference, $x, $y, $z);
            }
        }
        return $value;
    }

    /**
     * Parse value based on its format.
     *
     * @param array $current_node
     * @param array $reference_node
     * @return mixed
     */
    private function parseValue(array $current_node, array $reference_node)
    {
        if ($reference_node['value_format'] === "Integer") {
            return (int) $reference_node['value'];
        }

        if (isset($reference_node['round'])) {
            $inv = 1.0 / $reference_node['round'];
            $result = round($reference_node['value'] * $inv) / $inv;
            return (fmod($result, 1) === 0.0) ? (int) $result : $result;
        }
        return (fmod($reference_node['value'], 1) === 0.0) ? (int) $reference_node['value'] : $reference_node['value'];
    }

    /**
     * Process 3D reference table.
     *
     * @param array $referenceTable
     * @param int|float $referenceX
     * @param int|float $referenceY
     * @param int|float $referenceZ
     * @return mixed
     */
    private function processReferenceTable3D(array $referenceTable, $referenceX, $referenceY, $referenceZ)
    {
        if (array_key_exists($referenceX, $referenceTable)) {
            return $this->processReferenceTable($referenceTable[$referenceX], $referenceY, $referenceZ);
        }

        $scopedRange = array_keys($referenceTable);
        sort($scopedRange, SORT_NUMERIC);

        if ($scopedRange[0] > $referenceX) {
            return $this->processReferenceTable($referenceTable[$scopedRange[0]], $referenceY, $referenceZ);
        }

        return $this->processReferenceTable($referenceTable[end($scopedRange)], $referenceY, $referenceZ);
    }

    /**
     * Process 2D reference table.
     *
     * @param array $referenceTable
     * @param int|float $referenceX
     * @param int|float $referenceY
     * @return mixed
     */
    private function processReferenceTable(array $referenceTable, $referenceX, $referenceY)
    {
        if (array_key_exists($referenceX, $referenceTable)) {
            return $this->findValueInReferenceTable($referenceTable[$referenceX], $referenceY);
        }

        $scopedRange = array_keys($referenceTable);
        sort($scopedRange, SORT_NUMERIC);

        if ($scopedRange[0] > $referenceX) {
            return $this->findValueInReferenceTable($referenceTable[$scopedRange[0]], $referenceY);
        }

        return $this->findValueInReferenceTable($referenceTable[end($scopedRange)], $referenceY);
    }

    /**
     * Find value in a given reference table.
     *
     * @param array $referenceTable
     * @param int|float $reference
     * @return int|null
     */
    function findValueInReferenceTable(array $referenceTable, $reference)
    {
        $previousKey = null;
        $value = null;

        // Sort the keys of the reference table numerically
        $scopedRange = array_keys($referenceTable);
        sort($scopedRange, SORT_NUMERIC);

        // If reference is the same value as the first element in the sorted range
        if ($reference == $referenceTable[$scopedRange[0]]) {
            return (int) $scopedRange[1];
        }

        // If reference is the same value as the last element in the sorted range
        if ($reference == $referenceTable[end($scopedRange)]) {
            return (int) $scopedRange[count($scopedRange) - 2];
        }

        // If reference is smaller than the smallest value in the reference table
        if ($reference < $referenceTable[$scopedRange[0]]) {
            return (int) $scopedRange[0];
        }

        // If reference is larger than the largest value in the reference table
        if ($reference > $referenceTable[end($scopedRange)]) {
            return (int) end($scopedRange);
        }

        // Loop through the sorted keys to find the appropriate value
        foreach ($scopedRange as $key) {
            if ($referenceTable[$key] == $reference) {
                $currentIndex = array_search($key, $scopedRange);

                if ((int) $key === 0) {
                    $value = (int) $scopedRange[$currentIndex];
                    break;
                }

                $value = ((int) $key < 0) ? (int) $scopedRange[$currentIndex + 1] : (int) $scopedRange[$currentIndex - 1];
                break;
            }

            if ($referenceTable[$key] > $reference) {
                $value = ((int) $key <= 0) ? (int) $key : (int) $previousKey;
                break;
            }

            $previousKey = $key;
        }

        return $value;
    }

    /**
     * Return a reference table based on patient gender.
     *
     * @param array $currentNode
     * @param string $mcNodes
     * @return array|null
     */
    private function getReferenceTable(array $currentNode, string $gender)
    {
        if ($gender === 'male') {
            return config($currentNode['reference_table_male']);
        }

        if ($gender === 'female') {
            return config($currentNode['reference_table_female']);
        }
        return null;
    }
}
