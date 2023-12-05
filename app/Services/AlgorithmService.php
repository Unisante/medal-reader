<?php

namespace App\Services;

class AlgorithmService
{

    public function __construct()
    {
    }

    public function breadthFirstSearch($diag, $start_node_id, $answer_id, &$dependency_map)
    {
        $stack = [$start_node_id];
        $nodes_visited = [];

        while (!empty($stack)) {
            $node_id = array_shift($stack);

            if (isset($nodes_visited[$node_id])) {
                continue;
            }

            $nodes_visited[$node_id] = true;

            foreach ($diag['instances'] as $instance_id => $instance) {

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

        return $dependency_map;
    }

    public function handleNodesToUpdate($node, &$nodes_to_update)
    {
        $formula = $node['formula'];

        preg_replace_callback('/\[(\d+)\]/', function ($matches) use ($node, &$nodes_to_update) {
            if (isset($nodes_to_update[$matches[1]])) {
                $nodes_to_update[$matches[1]][] = $node['id'];
            } else {
                $nodes_to_update[$matches[1]] = [$node['id']];
            }
        }, $formula);

        return $nodes_to_update;
    }
}
