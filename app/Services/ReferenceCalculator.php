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
        if ($nodes['x']['value'] !== '' && $nodes['y'] !== '') {
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
        dump($nodes['x']['value'] . " + " . $nodes['y']['value'] . " => " . $value);
        dump($x . " + " . $y . " => " . $value);
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
            dump("Integer");
            return (int) $reference_node['value'];
        }

        //todo herer
        if (isset($reference_node['round'])) {
            dump("rounded");
            $reference_node['round'] || ($reference_node['round'] = 1.0);
            $inv = 1.0 / $reference_node['round'];
            return round($reference_node['value'] * $inv) / $inv;
        }

        return (float) $reference_node['value'];
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
    private function findValueInReferenceTable(array $referenceTable, $reference)
    {
        $previousKey = null;
        $value = null;

        $scopedRange = array_keys($referenceTable);
        sort($scopedRange, SORT_NUMERIC);

        if ($reference == $referenceTable[$scopedRange[0]]) {
            return (int)$scopedRange[1];
        }

        if ($reference == $referenceTable[end($scopedRange)]) {
            return (int)$scopedRange[count($scopedRange) - 2];
        }

        if ($reference < $referenceTable[$scopedRange[0]]) {
            return (int)$scopedRange[0];
        }

        if ($reference > $referenceTable[end($scopedRange)]) {
            return (int)end($scopedRange);
        }

        foreach ($scopedRange as $key) {
            if ($referenceTable[$key] === $reference) {
                $currentIndex = array_search($key, $scopedRange);

                if ((int)$key === 0) {
                    return (int)$scopedRange[$currentIndex];
                }

                return (int)$key < 0 ? (int)$scopedRange[$currentIndex + 1] : (int)$scopedRange[$currentIndex - 1];
            }

            if ($referenceTable[$key] > $reference) {
                return (int)$key <= 0 ? (int)$key : (int)$previousKey;
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
