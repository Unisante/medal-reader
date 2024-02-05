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

    public function depthFirstSearch($instances, $node_id, &$depth_map, $depth = 0)
    {
        if (!isset($depth_map[$node_id]) || $depth > $depth_map[$node_id]) {
            $depth_map[$node_id] = $depth;

            foreach ($instances as $instance) {
                if ($instance['id'] === $node_id) {
                    foreach ($instance['conditions'] as $condition) {
                        $next_node_id = $condition['node_id'];
                        $this->depthFirstSearch($instances, $next_node_id, $depth_map, $depth + 1);
                    }
                }
            }
        }
    }

    public function breadthFirstSearch($instances, $start_node_id, $answer_id, &$dependency_map)
    {
        $stack = [$start_node_id];
        $nodes_visited = [];

        while (!empty($stack)) {
            $node_id = array_shift($stack);

            if (isset($nodes_visited[$node_id])) {
                continue;
            }

            $nodes_visited[$node_id] = true;

            foreach ($instances as $instance_id => $instance) {
                if ($instance_id === $node_id && $node_id !== $start_node_id) {
                    if (!isset($dependency_map[$answer_id])) {
                        $dependency_map[$answer_id] = [];
                    }
                    $dependency_map[$answer_id][] = $instance_id;
                }

                $children = $instance['children'];
                foreach ($instance['conditions'] as $condition) {
                    if ($condition['node_id'] === $node_id) {

                        if (!isset($dependency_map[$answer_id])) {
                            $dependency_map[$answer_id] = [];
                        }

                        if (!in_array($instance_id, $dependency_map[$answer_id])) {
                            $dependency_map[$answer_id][] = $instance_id;
                        }

                        foreach ($children as $child_node_id) {
                            $stack[] = $child_node_id;
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

    public function sortSystemsAndNodesPerCC(array &$nodes, string $cache_key)
    {
        $cached_data = Cache::get($cache_key);
        $consultation_nodes = $cached_data['consultation_nodes'];

        $this->sortSystems($consultation_nodes, $nodes);
        $this->sortNodes($consultation_nodes, $nodes, true);
    }

    public function sortSystemsAndNodes(array &$nodes, string $cache_key)
    {
        $cached_data = Cache::get($cache_key);
        $consultation_nodes = $cached_data['consultation_nodes'];

        $this->sortSystems($consultation_nodes, $nodes);
        $this->sortNodes($consultation_nodes, $nodes, false);
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
