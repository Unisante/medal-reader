<?php

namespace App\Livewire;

use Cerbero\JsonParser\JsonParser;
use DateTime;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\On;
use Livewire\Component;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

class Algorithm extends Component
{
    public int $id;
    public string $cache_key;
    public int $cache_expiration_time;
    public string $title;
    public string $age_key = 'older';
    public array $registration_nodes;
    public object $complaint_categories_nodes;
    public array $chosen_complaint_categories;
    public array $df_to_display;
    public array $drugs_to_display;
    public array $managements_to_display;
    public array $nodes_to_save;
    public array $nodes;
    public string $current_step = 'registration';
    public string $date_of_birth = '1960-01-01';
    public string $current_cc;
    public array $steps = [
        'registration',
        'first_look_assessment',
        'complaint_categories',
        'basic_measurements',
        'medical_history',
        'physical_exam',
        'health_care_questions',
        'referral',
    ];

    public array $agreed_diagnoses;

    public function mount($id = null)
    {
        $this->id = $id;
        $extract_dir = Config::get('medal.storage.json_extract_dir');
        $json = json_decode(Storage::get("$extract_dir/{$this->id}.json"), true);
        if (!$json) {
            abort(404);
        }
        $this->title = $json['name'];
        $json_version = $json['medal_r_json_version'];

        $this->cache_key = "json_data_{$this->id}_$json_version";
        $this->cache_expiration_time = 86400; // 24 hours

        // todo set that up in redis
        Cache::forget($this->cache_key);
        $cache_found = Cache::has($this->cache_key);

        if (!$cache_found) {
            Cache::put($this->cache_key, [
                'full_nodes' => collect($json['medal_r_json']['nodes'])->keyBy('id')->all(),
                'instances' => $json['medal_r_json']['diagram']['instances'],
                'diagnoses' => $json['medal_r_json']['diagnoses'],
                'final_diagnoses' => $json['medal_r_json']['final_diagnoses'],
                'health_cares' => $json['medal_r_json']['health_cares'],
                'full_order' => $json['medal_r_json']['config']['full_order'],
                'full_order_medical_history' => $json['medal_r_json']['config']['full_order']['medical_history_step'][0]['data'],
                'registration_steps' => array_flip($json['medal_r_json']['config']['full_order']['registration_step'])
                    + array_flip($json['medal_r_json']['config']['full_order']['basic_measurements_step']),
                'complaint_categories_steps' => [
                    ...$json['medal_r_json']['config']['full_order']['complaint_categories_step']['older'],
                    ...$json['medal_r_json']['config']['full_order']['complaint_categories_step']['neonat']
                ],
                'birth_date_formulas' => $json['medal_r_json']['config']['birth_date_formulas'],
                'general_cc_id' => $json['medal_r_json']['config']['basic_questions']['general_cc_id'],
                'yi_general_cc_id' => $json['medal_r_json']['config']['basic_questions']['yi_general_cc_id'],
                'gender_question_id' => $json['medal_r_json']['config']['basic_questions']['gender_question_id'],
                'answers_hash_map' => [],
                'formula_hash_map' => [],
                'df_hash_map' => [],
                'drugs_hash_map' => [],
                'dependency_map' => [],
                'nodes_to_update' => [],
            ], $this->cache_expiration_time);
        }

        $cached_data = Cache::get($this->cache_key);

        $this->complaint_categories_nodes = collect($cached_data['full_nodes'])
            ->filter(function ($node) use ($cached_data) {
                return $node['category'] === 'complaint_category'
                    && $node['id'] !== $cached_data['general_cc_id']
                    && $node['id'] !== $cached_data['yi_general_cc_id'];
            })
            ->map(function ($node) {
                return [
                    'id' => $node['id'],
                    'is_neonat' => $node['is_neonat'],
                    'label' => $node['label']['en'] ?? '',
                    'description' => $node['description']['en'] ?? '',
                ];
            })
            ->values()
            ->groupBy(function ($node) {
                return $node['is_neonat'] ? 'neonat' : 'older';
            });

        $df_hash_map = [];
        $drugs_hash_map = [];
        foreach ($cached_data['final_diagnoses'] as $df) {
            foreach ($df['conditions'] as $condition) {
                $df_hash_map[$df['cc']][$condition['answer_id']][] = $df['id'];
            }

            foreach ($df['drugs'] as $drug) {
                foreach ($drug['conditions'] as $condition) {
                    // if (!in_array($drug['id'], $drugs_hash_map[$condition['answer_id']])) {
                    $drugs_hash_map[$condition['answer_id']][] = $drug['id'];
                    // }
                }
            }
        }


        $formula_hash_map = [];
        $nodes_to_update = [];
        JsonParser::parse(Storage::get("$extract_dir/$id.json"))
            ->pointer('/medal_r_json/nodes')
            ->traverse(function (mixed $value, string|int $key, JsonParser $parser) use ($cached_data, &$formula_hash_map, &$nodes_to_update) {
                foreach ($value as $node) {
                    //todo work with QuestionsSequence
                    if ($node['type'] === 'QuestionsSequence' || $node['display_format'] === 'Reference') {
                        continue;
                    }
                    if (array_key_exists($node['id'], $cached_data['registration_steps'])) {
                        $this->nodes_to_save[$node['id']] = "";
                        $this->registration_nodes[$node['id']] = [
                            'id' => $node['id'],
                            'display_format' => $node['display_format'],
                            'category' => $node['category'],
                            'label' => $node['label']['en'] ?? '',
                            'description' => $node['description']['en'] ?? '',
                            'answers' => array_map(function ($answer) {
                                return [
                                    'id' => $answer['id'],
                                    'label' => $answer['label']['en'] ?? '',
                                    'value' => $answer['value'] ?? '',
                                    'operator' => $answer['operator'] ?? '',
                                ];
                            }, $node['answers'] ?? []),
                        ];
                    }
                    if ($node['display_format'] === "Input" || $node['display_format'] === "Formula") {
                        $this->nodes_to_save[$node['id']] = "";
                    }
                    if ($node['category'] === "background_calculation" || $node['display_format'] === "Formula") {
                        $formula_hash_map[$node['id']] = $node['formula'];
                        $this->handleNodesToUpdate($node, $nodes_to_update);
                    }
                }
            });

        $answers_hash_map = [];
        $dependency_map = [];
        foreach ($cached_data['complaint_categories_steps'] as $step) {
            $diagnosesForStep = collect($cached_data['diagnoses'])->filter(function ($diag) use ($step) {
                return $diag['complaint_category'] === $step;
            });

            foreach ($diagnosesForStep as $diag) {
                foreach ($diag['instances'] as $instance_id => $instance) {

                    if (!array_key_exists('display_format', $cached_data['full_nodes'][$instance_id])) {
                        continue;
                    }

                    if ($instance_id === $cached_data['gender_question_id']) {
                        continue;
                    }

                    $age_key = $cached_data['full_nodes'][$instance_id]['is_neonat'] ? 'neonat' : 'older';

                    if (empty($instance['conditions'])) {
                        $this->nodes[$age_key][$step][$instance_id] = [
                            'id' => $cached_data['full_nodes'][$instance_id]['id'],
                            'category' => $cached_data['full_nodes'][$instance_id]['category'],
                            'display_format' => $cached_data['full_nodes'][$instance_id]['display_format'],
                            'label' => $cached_data['full_nodes'][$instance_id]['label']['en'] ?? '',
                            'description' => $cached_data['full_nodes'][$instance_id]['description']['en'] ?? '',
                            'answers' => array_map(function ($answer) {
                                return [
                                    'id' => $answer['id'],
                                    'label' => $answer['label']['en'] ?? '',
                                    'operator' => $answer['operator'] ?? '',
                                    'value' => $answer['value'] ?? '',
                                ];
                            }, $cached_data['full_nodes'][$instance_id]['answers'] ?? []),
                        ];
                    } else {
                        foreach ($instance['conditions'] as $condition) {
                            $answer_id = $condition['answer_id'];
                            $node_id = $condition['node_id'];

                            $answers_hash_map[$step][$answer_id][] = $instance_id;

                            $this->breadthFirstSearch($diag, $node_id, $answer_id, $dependency_map);
                        }
                    }
                }
            }
        }

        if (!$cache_found) {
            Cache::put($this->cache_key, [
                ...$cached_data,
                'answers_hash_map' => $answers_hash_map,
                'formula_hash_map' => $formula_hash_map,
                'nodes_to_update' => $nodes_to_update,
                'df_hash_map' => $df_hash_map,
                'drugs_hash_map' => $drugs_hash_map,
                'dependency_map' => $dependency_map,
            ], $this->cache_expiration_time);
            $cached_data = Cache::get($this->cache_key);
        }

        // Order nodes
        foreach ($this->nodes as $age_key => $nodes_by_age) {
            foreach ($nodes_by_age as $step_id => $nodes) {
                foreach ($cached_data['full_order_medical_history'] as $node_id) {
                    if (isset($this->nodes[$age_key][$step_id][$node_id])) {
                        $reordered_nodes[$step_id][$node_id] = $this->nodes[$age_key][$step_id][$node_id];
                    }
                }
                foreach ($this->nodes[$age_key][$step_id] as $node_id => $node) {
                    if (!isset($reordered_nodes[$node_id])) {
                        $reordered_nodes[$step_id][$node_id] = $node;
                    }
                }
                $this->nodes[$age_key][$step] = $reordered_nodes[$step_id];
            }
        }


        // dd($this->registration_steps);
        // dd($cached_data);

        // dd($cached_data['full_nodes']);
        // dump($this->nodes_to_save);
        // dump($cached_data['drugs_hash_map']);
        // dump($cached_data['dependency_map']);
        // dump($cached_data['formula_hash_map']);
        dump($cached_data['answers_hash_map']);
        // dump($cached_data['df_hash_map']);
        // dump($cached_data['nodes_to_update']);
        // dump($this->dependency_map);
        // dump($this->df_hash_map);
        // dump($this->formula_hash_map);
        // dd(Cache::get($this->cache_key));
    }

    #[On('nodeToSave')]
    public function saveNode($node_id, $value, $answer_id, $old_answer_id)
    {
        $cached_data = Cache::get($this->cache_key);
        $formula_hash_map = $cached_data['formula_hash_map'];
        $drugs_hash_map = $cached_data['drugs_hash_map'];
        $general_cc_id = $cached_data['general_cc_id'];
        $yi_general_cc_id = $cached_data['yi_general_cc_id'];

        if ($this->chosen_complaint_categories) {
            $step = reset($this->chosen_complaint_categories);
        } else {
            $step = $this->age_key === "older"
                ? $general_cc_id
                : $yi_general_cc_id;
        }
        $this->current_cc = $step;

        if (array_key_exists($node_id, $this->nodes_to_save)) {
            if (array_key_exists($node_id, $formula_hash_map)) {
                $value = $this->handleFormula($node_id);
            }
            $this->nodes_to_save[$node_id] = intval($value);

            // If answer will set a drug, we add it to the drugs to display
            if (array_key_exists($value, $drugs_hash_map)) {
                // todo remove that reset and put everything in the same first array
                // maybe with [...$this->drugs_to_display]
                $this->drugs_to_display[] = reset($drugs_hash_map[$value]);
            }
        }

        return $this->displayNextNode($answer_id, $old_answer_id);
    }

    #[On('nodeUpdated')]
    public function displayNextNode($value, $old_value)
    {
        $cached_data = Cache::get($this->cache_key);

        $dependency_map = $cached_data['dependency_map'];
        $formula_hash_map = $cached_data['formula_hash_map'];
        $final_diagnoses = $cached_data['final_diagnoses'];
        $df_hash_map = $cached_data['df_hash_map'];
        $health_cares = $cached_data['health_cares'];
        $general_cc_id = $cached_data['general_cc_id'];
        $yi_general_cc_id = $cached_data['yi_general_cc_id'];

        if ($this->chosen_complaint_categories) {
            $step = reset($this->chosen_complaint_categories);
        } else {
            $step = $this->age_key === "older"
                ? $general_cc_id
                : $yi_general_cc_id;
        }
        $this->current_cc = $step;

        // Modification behavior
        if ($old_value) {

            // Remove every old answer nodes dependency
            if (array_key_exists($old_value, $dependency_map)) {
                foreach ($dependency_map[$old_value] as $key) {
                    unset($this->nodes[$this->age_key][$this->current_cc][$key]);
                }
            }

            // Remove every df and managements dependency
            if (isset($df_hash_map[$this->current_cc][$old_value])) {
                foreach ($df_hash_map[$this->current_cc][$old_value] as $df) {
                    if (array_key_exists($df, $this->df_to_display)) {
                        if (isset($final_diagnoses[$df]['managements'])) {
                            unset($this->managements_to_display[key($final_diagnoses[$df]['managements'])]);
                        }
                        unset($this->df_to_display[$df]);
                    }
                }
            }

            // Need to recalulate bc
        }

        $next_node_id = $this->getNextNodeId($value);

        //if next node is background calc -> calc and directly show next <3
        if ($next_node_id) {
            foreach ($next_node_id as $node) {
                if (array_key_exists($node, $formula_hash_map)) {
                    $bc_value = $this->handleFormula($node);
                    $this->nodes_to_save[$node] = intval($bc_value);
                    $next_node_id = $this->getNextNodeId($value);
                }
            }
        }

        //if next node is DF, add it to df_to_display <3
        if (isset($df_hash_map[$this->current_cc][$value])) {
            $other_conditons_met = true;
            foreach ($df_hash_map[$this->current_cc][$value] as $df) {
                foreach ($final_diagnoses[$df]['conditions'] as $condition) {
                    // We already know that this condition is met because it has been calulated
                    if ($condition['answer_id'] !== $value) {
                        // We only check if the other conditions node has no condition
                        if (in_array($condition['node_id'], $this->nodes[$this->age_key][$this->current_cc]) || array_key_exists($condition['node_id'], $this->registration_nodes)) {
                            // Need also to calculate if node is not in nodes_to_save like radio button
                            if (
                                array_key_exists($condition['node_id'], $this->nodes_to_save)
                                && intval($this->nodes_to_save[$condition['node_id']]) != $condition['answer_id']
                            ) {
                                $other_conditons_met = false;
                            }
                        }
                    }
                }

                if ($other_conditons_met) {
                    if (!array_key_exists($final_diagnoses[$df]['id'], $this->df_to_display)) {
                        foreach ($final_diagnoses[$df]['drugs'] as $drug) {

                            $conditions = $final_diagnoses[$df]['conditions'];

                            if (empty($conditions)) {
                                $drugs[] = [
                                    'id' => $health_cares[$drug['id']]['id'],
                                    'label' => $health_cares[$drug['id']]['label']['en'],
                                    'description' => $health_cares[$drug['id']]['description']['en'],
                                ];
                            } else {
                                if (in_array($drug['id'], $this->drugs_to_display)) {
                                    $drugs[] = [
                                        'id' => $health_cares[$drug['id']]['id'],
                                        'label' => $health_cares[$drug['id']]['label']['en'],
                                        'description' => $health_cares[$drug['id']]['description']['en'],
                                    ];
                                }
                            }
                        }

                        $this->df_to_display[$df] = [
                            'id' => $final_diagnoses[$df]['id'],
                            'label' => $final_diagnoses[$df]['label']['en'] ?? '',
                            'description' => $final_diagnoses[$df]['description']['en'] ?? '',
                            'level_of_urgency' => $final_diagnoses[$df]['level_of_urgency'],
                            'drugs' => $drugs ?? []
                        ];
                    }

                    //todo when multiple managements sets what to do ?
                    $management_key = key($final_diagnoses[$df]['managements']);

                    // Because sometime df has no managements
                    if (isset($health_cares[$management_key]['id'])) {
                        if (!array_key_exists($health_cares[$management_key]['id'], $this->managements_to_display)) {
                            $this->managements_to_display[$management_key] = [
                                'id' => $health_cares[$management_key]['id'],
                                'label' => $health_cares[$management_key]['label']['en'] ?? '',
                                'description' => $health_cares[$management_key]['description']['en'] ?? '',
                                'level_of_urgency' => $health_cares[$management_key]['level_of_urgency'],
                            ];
                        }
                    }
                }
            }
        }


        // Reorder DF and managements upon level_of_urgency
        uasort($this->df_to_display, fn ($a, $b) => $b['level_of_urgency'] <=> $a['level_of_urgency']);
        uasort($this->managements_to_display, fn ($a, $b) => $b['level_of_urgency'] <=> $a['level_of_urgency']);

        if ($next_node_id) {
            foreach ($next_node_id as $node) {
                $this->setNextNodeAndSort($node);
            }
        }
    }

    #[On('ccUpdated')]
    public function updateCC($value)
    {
        $this->chosen_complaint_categories[] = $value;
    }

    #[On('dobUpdated')]
    public function updateLinkedNodesOfDob($value)
    {
        if ($value === "") {
            return;
        }

        $this->date_of_birth = $value;

        $cached_data = Cache::get($this->cache_key);
        $birth_date_formulas = $cached_data['birth_date_formulas'];

        foreach ($birth_date_formulas as $node_id) {
            $this->nodes_to_save[$node_id] = null;
            $this->saveNode($node_id, null, null, null);
        }
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

    public function setNextNodeAndSort($next_node_id)
    {
        $cached_data = Cache::get($this->cache_key);
        $full_nodes = $cached_data['full_nodes'];
        $full_order_medical_history = $cached_data['full_order_medical_history'];

        if (isset($full_nodes[$next_node_id])) {
            $this->nodes[$this->age_key][$this->current_cc][$next_node_id] = [
                'id' => $full_nodes[$next_node_id]['id'],
                'category' => $full_nodes[$next_node_id]['category'],
                'display_format' => $full_nodes[$next_node_id]['display_format'],
                'label' => $full_nodes[$next_node_id]['label']['en'] ?? '',
                'description' => $full_nodes[$next_node_id]['description']['en'] ?? '',
                'answers' => array_map(function ($answer) {
                    return [
                        'id' => $answer['id'],
                        'label' => $answer['label']['en'] ?? '',
                        'operator' => $answer['operator'] ?? '',
                        'value' => $answer['value'] ?? '',
                    ];
                }, $full_nodes[$next_node_id]['answers'] ?? []),
            ];

            $reordered_nodes = [];
            $node_exists = false;

            foreach ($full_order_medical_history as $node_id) {
                if (isset($this->nodes[$this->age_key][$this->current_cc][$node_id])) {
                    if (!$node_exists && $node_id === $next_node_id) {
                        $reordered_nodes[$next_node_id] = [
                            'id' => $full_nodes[$next_node_id]['id'],
                            'category' => $full_nodes[$next_node_id]['category'],
                            'display_format' => $full_nodes[$next_node_id]['display_format'],
                            'label' => $full_nodes[$next_node_id]['label']['en'] ?? '',
                            'description' => $full_nodes[$next_node_id]['description']['en'] ?? '',
                            'answers' => array_map(function ($answer) {
                                return [
                                    'id' => $answer['id'],
                                    'label' => $answer['label']['en'] ?? '',
                                    'operator' => $answer['operator'] ?? '',
                                    'value' => $answer['value'] ?? '',
                                ];
                            }, $full_nodes[$next_node_id]['answers'] ?? []),
                        ];
                        $node_exists = true;
                    } else {
                        $reordered_nodes[$node_id] = $this->nodes[$this->age_key][$this->current_cc][$node_id];
                    }
                }
            }

            // Merge nodes that were not in full_order_medical_history but are present in $this->nodes[$this->current_cc]
            foreach ($this->nodes[$this->age_key][$this->current_cc] as $node_id => $node) {
                if (!isset($reordered_nodes[$node_id])) {
                    $reordered_nodes[$node_id] = $node;
                }
            }

            $this->nodes[$this->age_key][$this->current_cc] = $reordered_nodes;
        }
    }

    public function handleNodesToUpdate($node, &$nodes_to_update)
    {
        $formula = $node['formula'];

        $nodes_to_update[$node['id']] = preg_replace_callback('/\[(\d+)\]/', function ($matches) use ($node, &$nodes_to_update) {
            return $nodes_to_update[$matches[1]][] = $node['id'];
        }, $formula);

        return $nodes_to_update;
    }

    public function handleFormula($node_id)
    {
        $formula_hash_map = Cache::get($this->cache_key)['formula_hash_map'];
        $full_nodes = Cache::get($this->cache_key)['full_nodes'];

        $formula = $formula_hash_map[$node_id];

        if ($formula === "ToDay" || $formula === "ToMonth" || $formula === "ToYear") {
            $today = new DateTime('today');
            $dob = new DateTime($this->date_of_birth);
            $interval = $today->diff($dob);

            if ($formula === "ToDay") {
                $days = $interval->format('%a');
                //My eyes are burning....
                //But no other way as the Age in days node id is not saved anywhere
                if ($full_nodes[$node_id]['label']['en'] === 'Age in days') {
                    $this->age_key = $days <= 59 ? 'neonat' : 'older';
                }
                return $days;
            } elseif ($formula === "ToMonth") {
                return $interval->m + ($interval->y * 12);
            } elseif ($formula === "ToYear") {
                return $interval->y;
            }
        }

        $formula = preg_replace_callback('/\[(\d+)\]/', function ($matches) {
            return $this->nodes_to_save[$matches[1]];
        }, $formula);

        try {
            $result = (new ExpressionLanguage())->evaluate($formula);
        } catch (Exception $e) {
            return null;
        }

        foreach ($full_nodes[$node_id]['answers'] as $answer) {

            $value = $answer['value'];
            $values = explode(',', $value);
            $minValue = intval($values[0]);
            $maxValue = intval($values[1] ?? $minValue);

            $answer_id = match ($answer['operator']) {
                'more_or_equal' => $result >= $minValue ? $answer['id'] : null,
                'less' => $result < $minValue ? $answer['id'] : null,
                'between' => ($result >= $minValue && $result < $maxValue) ? $answer['id'] : null,
                default => null,
            };
            if ($answer_id) {
                return $answer_id;
            }
        }

        return null;
    }

    public function getNextNodeId($node_id)
    {
        $cached_data = Cache::get($this->cache_key);
        $answers_hash_map = $cached_data['answers_hash_map'];
        $general_cc_id = $cached_data['general_cc_id'];
        $yi_general_cc_id = $cached_data['yi_general_cc_id'];

        $step = $this->current_cc
            ?? $this->age_key === "older"
            ? $general_cc_id
            : $yi_general_cc_id;

        return $answers_hash_map[$step][$node_id] ?? null;
    }

    public function goToStep(string $step): void
    {
        if ($step === 'complaint_categories') {
            // $formula_hash_map = Cache::get($this->cache_key)['formula_hash_map'];
            // $nodes_to_save = [];

            // $this->current_cc = reset($this->chosen_complaint_categories);

            // // We only need to refresh background_calculation nodes
            // foreach ($this->registration_nodes as $node) {
            //     if ($node['category'] === 'background_calculation' || $node['display_format'] === 'Formula')
            //         $nodes_to_save[$node['id']] = $node;
            // }

            // // todo need to calcule all linked bc after registration step
            // $nodes_to_save = $nodes_to_save + array_intersect_key($this->nodes[$this->age_key][$value], $formula_hash_map);

            // if (!empty($nodes_to_save)) {
            //     foreach ($nodes_to_save as $node_id => $node) {
            //         $this->saveNode($node_id, null, null, null);
            //     }
            // }
        }

        if ($step === 'medical_history') {
            $cached_data = Cache::get($this->cache_key);
            $cc_order = $cached_data['complaint_categories_steps'];

            // Respect the order in the complaint_categories_step key
            usort($this->chosen_complaint_categories, function ($a, $b) use ($cc_order) {
                return array_search($a, $cc_order) <=> array_search($b, $cc_order);
            });

            $this->current_cc = reset($this->chosen_complaint_categories);
        }

        $this->current_step = $step;
    }

    public function goToNextCc(): void
    {
        $current_index = array_search($this->current_cc, $this->chosen_complaint_categories);
        $next_index = ($current_index + 1) % count($this->chosen_complaint_categories);

        if ($current_index === count($this->chosen_complaint_categories) - 1 && $next_index === 0) {
            return;
        }

        $this->current_cc = $this->chosen_complaint_categories[$next_index];
    }

    public function goToPreviousCc(): void
    {
        $current_index = array_search($this->current_cc, $this->chosen_complaint_categories);
        $count = count($this->chosen_complaint_categories);

        $previous_index = ($current_index - 1 + $count) % $count;
        if ($current_index === 0 && $previous_index === $count - 1) {
            return;
        }


        $this->current_cc = $this->chosen_complaint_categories[$previous_index];
    }

    public function submitCC($chosen_cc)
    {
        $this->currentStep = $chosen_cc;

        // return view('livewire.components.step-renderer', [
        //     'nodes' => $this->nodes[$chosen_cc],
        // ]);
    }

    public function render()
    {
        return view('livewire.algorithm');
    }

    //       switch (currentNode.display_format) {
    //     case Config.DISPLAY_FORMAT.radioButton:
    //       if (currentNode.category === Config.CATEGORIES.complaintCategory) {
    //         return <Toggle questionId={questionId} />
    //       }
    //       return <Boolean questionId={questionId} />
    //     case Config.DISPLAY_FORMAT.input:
    //       return <Numeric questionId={questionId} />
    //     case Config.DISPLAY_FORMAT.string:
    //       return <String questionId={questionId} />
    //     case Config.DISPLAY_FORMAT.autocomplete:
    //       return <Autocomplete questionId={questionId} />
    //     case Config.DISPLAY_FORMAT.dropDownList:
    //       return <Select questionId={questionId} />
    //     case Config.DISPLAY_FORMAT.reference:
    //     case Config.DISPLAY_FORMAT.formula:
    //       return <String questionId={questionId} editable={false} />
    //     case Config.DISPLAY_FORMAT.date:
    //       return <DatePicker questionId={questionId} />
    //     default:
    //       return <Text>{translate(currentNode.label)}</Text>
    //   }
    //     DISPLAY_FORMAT: {
    //      radioButton: 'RadioButton',
    //      input: 'Input',
    //      dropDownList: 'DropDownList',
    //      formula: 'Formula',
    //      reference: 'Reference', // reference table
    //      string: 'String',
    //      autocomplete: 'Autocomplete',
    //      date: 'Date',
    //     },
}
