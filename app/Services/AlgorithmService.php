<?php

namespace App\Services;

use DateTime;
use Illuminate\Support\Facades\Cache;

class AlgorithmService
{
    public function breadthFirstSearch($instances, $diag_id, $start_node_id, $answer_id, &$dependency_map, $filter_answer = false)
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
                    if (!isset($dependency_map[$diag_id][$answer_id])) {
                        $dependency_map[$diag_id][$answer_id] = [];
                    }
                    if (!isset(array_flip($dependency_map[$diag_id][$answer_id])[$instance_id])) {
                        $dependency_map[$diag_id][$answer_id][] = $instance_id;
                    }
                }

                foreach ($instance['conditions'] as $condition) {
                    if ($condition['node_id'] === $node_id) {
                        if ($filter_answer) {
                            if ($answer_id === $condition['answer_id']) {
                                if (!isset($dependency_map[$diag_id][$answer_id])) {
                                    $dependency_map[$diag_id][$answer_id] = [];
                                }

                                if (!isset(array_flip($dependency_map[$diag_id][$answer_id])[$instance_id])) {
                                    $dependency_map[$diag_id][$answer_id][] = $instance_id;
                                }
                            }
                        } else {
                            if (!isset($dependency_map[$diag_id][$answer_id])) {
                                $dependency_map[$diag_id][$answer_id] = [];
                            }

                            if (!isset(array_flip($dependency_map[$diag_id][$answer_id])[$instance_id])) {
                                $dependency_map[$diag_id][$answer_id][] = $instance_id;
                            }
                        }

                        foreach ($instance['children'] as $child_node_id) {
                            $stack[] = $child_node_id;
                        }
                    }
                }
            }
        }
    }

    public function manageQS($cached_data, $diag, $node, $step, &$consultation_nodes, &$answers_hash_map, &$qs_hash_map, &$dependency_map, $no_condition, $answer_id = null)
    {
        $no_answer = collect($node['answers'])->where('reference', 2)->first()['id'];
        $this->nodes_to_save[$node['id']]  = [
            'value' => '',
            'answer_id' => $no_answer,
            'label' => $node['label']['en'],
        ];

        foreach ($node['conditions'] as $condition) {
            if (!isset($qs_hash_map[$step][$diag['id']][$condition['answer_id']][$node['id']])) {
                $qs_hash_map[$step][$diag['id']][$condition['answer_id']][$node['id']] = [];
            }
            $yes_answer = collect($node['answers'])->where('reference', 1)->first()['id'];
            $qs_hash_map[$step][$diag['id']][$condition['answer_id']][$node['id']] = $yes_answer;
        }

        foreach ($node['instances'] as $instance_id => $instance) {
            $instance_node = $cached_data['full_nodes'][$instance_id];
            $substep = $instance_node['category'] === 'physical_exam' ? 'physical_exam' : 'medical_history';
            $system = $instance_node['category'] !== 'background_calculation' ? $instance_node['system'] ?? 'others' : 'others';

            if (empty($instance['conditions'])) {
                // We don't care about background calculations
                if ($no_condition && $instance_node['type'] !== 'QuestionsSequence' && $instance['final_diagnosis_id'] === null) {
                    if (array_key_exists('system', $instance_node) || $instance_node['category'] === 'unique_triage_question') {
                        $consultation_nodes[$substep][$system][$step][$diag['id']][$instance_id] = '';
                    }
                }
                if ($answer_id && $instance_node['type'] !== 'QuestionsSequence' && $instance['final_diagnosis_id'] === null) {
                    if (!isset($answers_hash_map[$step][$diag['id']][$answer_id])) {
                        $answers_hash_map[$step][$diag['id']][$answer_id] = [];
                    }
                    if (!in_array($instance_id, $answers_hash_map[$step][$diag['id']][$answer_id])) {
                        $answers_hash_map[$step][$diag['id']][$answer_id][] = $instance_id;
                    }
                    if (!isset($dependency_map[$diag['id']][$answer_id])) {
                        $dependency_map[$diag['id']][$answer_id] = [];
                    }

                    if (!isset(array_flip($dependency_map[$diag['id']][$answer_id])[$instance_id])) {
                        $dependency_map[$diag['id']][$answer_id][] = $instance_id;
                    }
                    $this->breadthFirstSearch($diag['instances'], $diag['id'], $instance_id, $answer_id, $dependency_map);
                }
                if ($instance_node['type'] === 'QuestionsSequence') {
                    $this->manageQS($cached_data, $diag, $instance_node, $step, $consultation_nodes, $answers_hash_map, $qs_hash_map, $dependency_map, $no_condition, $answer_id);
                }
            }

            if (!empty($instance['conditions'])) {
                foreach ($instance['conditions'] as $condition) {
                    $condition_node = $cached_data['full_nodes'][$condition['node_id']];
                    if ($instance_node['type'] !== 'QuestionsSequence') {
                        if (!isset($answers_hash_map[$step][$diag['id']][$condition['answer_id']])) {
                            $answers_hash_map[$step][$diag['id']][$condition['answer_id']] = [];
                        }
                        if (!in_array($instance_id, $answers_hash_map[$step][$diag['id']][$condition['answer_id']])) {
                            $answers_hash_map[$step][$diag['id']][$condition['answer_id']][] = $instance_id;
                        }
                        if (!isset($dependency_map[$diag['id']][$answer_id ?? $condition['answer_id']])) {
                            $dependency_map[$diag['id']][$answer_id ?? $condition['answer_id']] = [];
                        }

                        if (!isset(array_flip($dependency_map[$diag['id']][$answer_id ?? $condition['answer_id']])[$instance_id])) {
                            $dependency_map[$diag['id']][$answer_id ?? $condition['answer_id']][] = $instance_id;
                        }
                        $this->breadthFirstSearch($diag['instances'], $diag['id'], $node['id'], $answer_id ?? $condition['answer_id'], $dependency_map, true);
                    } else {
                        $this->manageQS($cached_data, $diag, $instance_node, $step, $consultation_nodes, $answers_hash_map, $qs_hash_map, $dependency_map, false, $condition['answer_id']);
                    }
                    if ($condition_node['type'] !== 'QuestionsSequence') {
                        $this->breadthFirstSearch($diag['instances'], $diag['id'], $node['id'], $answer_id ?? $condition['answer_id'], $dependency_map, true);
                    }
                }
            }

            foreach ($instance['children'] as $child_node_id) {
                $child_node = $cached_data['full_nodes'][$child_node_id];
                if ($child_node_id !== $node['id'] && $child_node['type'] === 'QuestionsSequence') {
                    $this->manageQS($cached_data, $diag, $child_node, $step, $consultation_nodes, $answers_hash_map, $qs_hash_map, $dependency_map, $no_condition);
                } else {
                    $this->breadthFirstSearch($diag['instances'], $diag['id'], $node['id'], $answer_id, $dependency_map, true);
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
        if (array_key_exists('physical_exam', $nodes)) {
            $this->sortSystemsAndNodesPerCC($nodes['physical_exam'], 'physical_exam', $cache_key);
        }
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

    public function sortNodesPerCC(array &$nodes, string $cache_key)
    {
        $cached_data = Cache::get($cache_key);
        $consultation_nodes = $cached_data['consultation_nodes'];

        foreach ($nodes as &$nodes_per_cc) {
            $order = array_flip($consultation_nodes['medical_history']['general']['data']);
            uksort($nodes_per_cc, function ($a, $b) use ($order) {
                if (!isset($order[$a]) || !isset($order[$b])) return;
                return $order[$a] - $order[$b];
            });
        }
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

    public function sortNodes(array $consultation_nodes, array &$nodes, bool $perCC)
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

    public function isDate($date, $format = 'Y-m-d')
    {
        $d = DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date;
    }

    private function generateQuestion($node)
    {
        $answer = $node['answer'] ?? null;
        $value = $node['value'] ?? '';
        $rounded_value = $node['rounded_value'] ?? '';
        $estimable = $node['estimable'] ?? false;
        $estimable_value = $node['estimable_value'] ?? 'measured';
        $validation_message = $node['validation_message'] ?? null;
        $validation_type = $node['validation_type'] ?? null;
        $unavailable_value = $node['unavailable_value'] ?? false;
        $label = '';

        $hash = array_merge(
            $this->_generateCommon($node),
            [
                'answer' => $answer,
                'value' => $value,
                'rounded_value' => $rounded_value,
                'validation_message' => $validation_message,
                'validation_type' => $validation_type,
                'unavailable_value' => $unavailable_value,
                'label' => $label
            ]
        );

        // Set complain category to false by default
        if ($node['category'] === config('medal.categories.complaint_category')) {
            $hash['answer'] = $this->getNoAnswer($node);
        }

        // Add attribute for basic measurement question ex (weight, MUAC, height) to know if it's measured or estimated value answered
        if ($estimable) {
            // Type available [measured, estimated]
            $hash['estimable_value'] = $estimable_value;
        }

        return $hash;
    }

    private function generateQuestionsSequence($node)
    {
        $answer = $node['answer'] ?? null;

        return array_merge(
            $this->_generateCommon($node),
            [
                'answer' => $answer
            ]
        );
    }

    private function _generateCommon($node)
    {
        return [
            'id' => $node['id']
        ];
    }

    public function createMedicalCaseNodes($nodes)
    {
        return $this->generateNewNodes($nodes);
    }

    private function generateNewNodes($nodes)
    {
        $new_nodes = [];

        foreach ($nodes as $node) {
            if (!isset($node['type'])) continue;
            switch ($node['type']) {
                case config('medal.node_types.questions_sequence'):
                    $new_nodes[$node['id']] = $this->generateQuestionsSequence($node);
                    break;
                case config('medal.node_types.question'):
                    $new_nodes[$node['id']] = $this->generateQuestion($node);
                    break;
                case config('medal.node_types.health_care'):
                case config('medal.node_types.final_diagnosis'):
                default:
                    break;
            }
        }

        return $new_nodes;
    }

    public function getYesAnswer($node)
    {
        return collect($node['answers'])->where('reference', 1)->first()['id'];
    }

    public function getNoAnswer($node)
    {
        return collect($node['answers'])->where('reference', 2)->first()['id'];
    }
}
