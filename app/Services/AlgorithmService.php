<?php

namespace App\Services;

use DateTime;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;

class AlgorithmService
{

    public function __construct()
    {
    }

    public function getReachableNodes($adjacency_list, $answer_hash_map, $start)
    {
        $visited = [];
        $max_nodes = 0;
        $this->dfs($adjacency_list, $answer_hash_map, $start, 0, $max_nodes, $visited);
        return $max_nodes;
    }

    private function dfs($adjacency_list, $answer_hash_map, $answer_id, $current_count, &$max_nodes, &$visited)
    {
        $visited[$answer_id] = true;
        $current_count++;
        $max_nodes = max($max_nodes, $current_count);

        if (isset($adjacency_list[$answer_id])) {
            foreach ($adjacency_list[$answer_id] as $neighbor) {
                if (!isset($visited[$neighbor])) {
                    $this->dfs($adjacency_list, $answer_hash_map, $neighbor, $current_count, $max_nodes, $visited);
                }
            }
        }
    }

    function createAdjacencyList($answer_hash_map, $dependency_map, &$adjacency_list)
    {
        foreach ($answer_hash_map as $answerId => $nodes) {
            $adjacentNodes = [];
            foreach ($nodes as $node) {
                if (isset($dependency_map[$answerId])) {
                    $adjacentNodes = array_merge($adjacentNodes, $dependency_map[$answerId]);
                }
            }
            // Remove duplicate dependencies and add the adjacent nodes to the adjacency list
            $adjacencyList[$answerId] = array_unique($adjacentNodes);
        }
    }

    public function depthFirstSearch($instances, $node_id, $answer_id, &$height_map, &$visited)
    {
        if (isset($height_map[$answer_id])) {
            return $height_map[$answer_id];
        }

        if (!isset($instances[$node_id])) {
            return 0;
        }

        $max_child_height = 0;

        foreach ($instances[$node_id]['conditions'] as $condition) {
            $next_node_id = $condition['node_id'];
            $child_height = 1 + $this->depthFirstSearch($instances, $next_node_id, $answer_id, $height_map, $visited);
            $max_child_height = max($max_child_height, $child_height);
        }

        $height_map[$answer_id] = $max_child_height;
        $visited[$answer_id] = true;

        return $max_child_height;
    }


    public function calculateMaxChildLength($instances, $node_id, &$max_length)
    {
        if (!isset($instances[$node_id])) {
            return 0;
        }

        $instance = $instances[$node_id];
        $max_length[$node_id] = 0;

        foreach ($instance['conditions'] as $condition) {
            $child_max_length = 0;
            foreach ($instance['children'] as $child_node_id) {
                $child_max_length = max($child_max_length, $this->calculateMaxChildLength($instances, $child_node_id, $max_length));
            }
            $max_length[$node_id] = max($max_length[$node_id], $child_max_length + 1);
        }

        return $max_length[$node_id];
    }

    public function breadthFirstSearch($instances, $start_node_id, $answer_id, &$dependency_map, &$max_length)
    {
        // Calculate maximum lengths for each node
        $this->calculateMaxChildLength($instances, $start_node_id, $max_length);

        // Implement breadth-first search using $max_length
        $stack = [[$start_node_id, 0]];
        $nodes_visited = [];

        while (!empty($stack)) {
            [$node_id, $length] = array_shift($stack);

            if (isset($nodes_visited[$node_id])) {
                continue;
            }

            $nodes_visited[$node_id] = true;

            foreach ($instances as $instance_id => $instance) {
                if ($instance_id === $node_id && $node_id !== $start_node_id) {
                    if (!isset($dependency_map[$answer_id])) {
                        $dependency_map[$answer_id] = [];
                    }
                    if (!isset(array_flip($dependency_map[$answer_id])[$instance_id])) {
                        $dependency_map[$answer_id][] = $instance_id;
                    }
                }

                foreach ($instance['conditions'] as $condition) {
                    if ($condition['node_id'] === $node_id) {
                        $length++;

                        $length = max($max_length[$node_id] ?? 0, $length);
                        if (!isset($max_length[$answer_id]) || $length > $max_length[$answer_id]) {
                            $max_length[$answer_id] = $length;
                        }

                        if (!isset($dependency_map[$answer_id])) {
                            $dependency_map[$answer_id] = [];
                        }

                        if (!isset(array_flip($dependency_map[$answer_id])[$instance_id])) {
                            $dependency_map[$answer_id][] = $instance_id;
                        }

                        foreach ($instance['children'] as $child_node_id) {
                            $stack[] = [$child_node_id, $length];
                        }
                    }
                }
            }
        }
    }


    public function handleNodesToUpdate($node, &$nodes_to_update)
    {
        $formula = $node['formula'];

        preg_replace_callback('/\[(\d+)\]/', function ($matches) use ($node, &$nodes_to_update) {
            if (isset($nodes_to_update[$matches[1]]) && !in_array($node['id'], $nodes_to_update[$matches[1]])) {
                $nodes_to_update[$matches[1]][] = $node['id'];
            } else {
                $nodes_to_update[$matches[1]] = [$node['id']];
            }
        }, $formula);

        return $nodes_to_update;
    }

    public function sortSystemsAndNodesPerCCPerStep(array &$nodes, string $cache_key)
    {
        $this->sortSystemsAndNodesPerCC($nodes['medical_history'], 'medical_history', $cache_key);
        $this->sortSystemsAndNodesPerCC($nodes['physical_exam'], 'physical_exam', $cache_key);
    }

    public function sortSystemsAndNodesPerCC(array &$nodes, $step, string $cache_key)
    {
        $cached_data = Cache::get($cache_key);
        $consultation_nodes = $cached_data['consultation_nodes'];

        $this->sortSystems($consultation_nodes[$step], $nodes);
        $this->sortNodes($consultation_nodes[$step], $nodes, true);
    }

    public function sortSystemsAndNodes(array &$nodes, $step, string $cache_key)
    {
        $cached_data = Cache::get($cache_key);
        $consultation_nodes = $cached_data['consultation_nodes'];

        $this->sortSystems($consultation_nodes[$step], $nodes);
        $this->sortNodes($consultation_nodes[$step], $nodes, false);
    }

    public function sortSystems(array $consultation_nodes, array &$nodes)
    {
        $systems = array_keys($consultation_nodes);
        $desired_systems_order = array_values($systems);
        $title_position_map = array_flip($desired_systems_order);

        uksort($nodes, function ($a, $b) use ($title_position_map) {
            return $title_position_map[$a] - $title_position_map[$b];
        });
    }

    public function sortNodes(array $consultation_nodes, array &$nodes, $perCC)
    {
        foreach ($nodes as $key => &$nodes_per_system) {
            $order = array_flip($consultation_nodes[$key]['data']);
            if ($perCC) {
                foreach ($nodes_per_system as &$nodes_per_cc) {
                    uksort($nodes_per_cc, function ($a, $b) use ($order) {
                        return $order[$a] - $order[$b];
                    });
                }
            } else {
                uksort($nodes_per_system, function ($a, $b) use ($order) {
                    //Because we don't have any orders set for others system
                    if (!isset($order[$a]) || !isset($order[$b])) return;

                    return $order[$a] - $order[$b];
                });
            }
        }
    }

    function isDate($date, $format = 'Y-m-d')
    {
        $d = DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date;
    }
}
