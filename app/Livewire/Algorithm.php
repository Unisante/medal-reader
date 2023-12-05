<?php

namespace App\Livewire;

use App\Services\AlgorithmService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Livewire\Component;
use App\Services\FormulationService;
use Cerbero\JsonParser\JsonParser;
use DateTime;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\On;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

class Algorithm extends Component
{
    public int $id;
    public string $cache_key;
    public int $cache_expiration_time;
    public string $title;
    public bool $is_dynamic_study;
    //todo remove definition when in prod
    public string $age_key = 'older';
    public string $current_step = 'registration';
    public string $date_of_birth = '1960-01-01';
    public string $current_cc;
    public object $complaint_categories_nodes;
    public array $chosen_complaint_categories;
    public array $df_to_display;
    public array $drugs_to_display;
    public array $managements_to_display;
    public array $all_managements_to_display;
    public array $nodes_to_save;
    public array $current_nodes;
    public array $nodes;
    public array $diagnoses_status;
    public array $drugs_status;
    public array $drugs_formulation;
    public array $formulations_to_display;

    private $algorithmService;
    // private array $diagnoses_formulation;
    // public array $drugs_formulations;

    public array $steps = [
        'registration' => [],
        'first_look_assessment' => [
            'vital_signs',
            'complaint_categories',
            'basic_measurement',
        ],
        'consultation' => [
            'medical_history',
            'physical_exams',
        ],
        'tests' => [],
        'diagnoses' => [
            'final_diagnoses',
            'treatment_questions',
            'medicines',
            'summary',
            'referral',
        ],
    ];
    public string $current_sub_step = '';

    public function boot(AlgorithmService $algorithmService)
    {
        $this->algorithmService = $algorithmService;
    }

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
        $this->is_dynamic_study = in_array($json['algorithm_id'], config('medal.projects.dynamic'));

        $this->cache_key = "json_data_{$this->id}_$json_version";
        //todo set the update cache behovior on json update and set it indefinitely
        $this->cache_expiration_time = 86400; // 24 hours

        //todo set that up in redis when in prod
        //tdo also remove the cache forget x)
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
                'registration_nodes_id' => [
                    ...$json['medal_r_json']['config']['full_order']['registration_step'],
                    ...$json['medal_r_json']['patient_level_questions'],
                ],
                'first_look_assessment_nodes_id' => [
                    'first_look_nodes_id' => $json['medal_r_json']['config']['full_order']['first_look_assessment_step'],
                    'complaint_categories_nodes_id' => [
                        ...$json['medal_r_json']['config']['full_order']['complaint_categories_step']['older'],
                        ...$json['medal_r_json']['config']['full_order']['complaint_categories_step']['neonat']
                    ],
                    'basic_measurements_nodes_id' => $json['medal_r_json']['config']['full_order']['basic_measurements_step'],
                ],

                'consultation_nodes' => [
                    ...array_combine(
                        array_column($json['medal_r_json']['config']['full_order']['medical_history_step'], 'title'),
                        array_values($json['medal_r_json']['config']['full_order']['medical_history_step'])
                    ),
                    ...['others' => ['title' => 'others', 'data' => []]]
                ],
                'tests_nodes_id' => $json['medal_r_json']['config']['full_order']['test_step'],
                'diagnoses_nodes_id' => [
                    ...$json['medal_r_json']['config']['full_order']['health_care_questions_step'],
                    ...$json['medal_r_json']['config']['full_order']['referral_step'],
                ],

                'complaint_categories_steps' => [
                    ...$json['medal_r_json']['config']['full_order']['complaint_categories_step']['older'],
                    ...$json['medal_r_json']['config']['full_order']['complaint_categories_step']['neonat']
                ],
                'birth_date_formulas' => $json['medal_r_json']['config']['birth_date_formulas'],
                'general_cc_id' => $json['medal_r_json']['config']['basic_questions']['general_cc_id'],
                'yi_general_cc_id' => $json['medal_r_json']['config']['basic_questions']['yi_general_cc_id'],
                'gender_question_id' => $json['medal_r_json']['config']['basic_questions']['gender_question_id'],
                'villages' => array_merge(...$json['medal_r_json']['village_json'] ?? []), // No village for non dynamic study;

                // All logics that will be calulated
                'answers_hash_map' => [],
                'formula_hash_map' => [],
                'df_hash_map' => [],
                'drugs_hash_map' => [],
                'conditioned_nodes_hash_map' => [],
                'managements_hash_map' => [],
                'dependency_map' => [],
                'nodes_to_update' => [],
                'nodes_per_step' => [],
                'no_condition_nodes' => [],
            ], $this->cache_expiration_time);
        }

        $cached_data = Cache::get($this->cache_key);

        $df_hash_map = [];
        $drugs_hash_map = [];
        $managements_hash_map = [];

        foreach ($cached_data['final_diagnoses'] as $df) {
            foreach ($df['conditions'] as $condition) {
                $df_hash_map[$df['cc']][$condition['answer_id']][] = $df['id'];
            }
            foreach ($df['conditions'] as $condition) {
                $df_hash_map[$df['cc']][$condition['answer_id']][] = $df['id'];
            }

            foreach ($df['drugs'] as $drug) {
                foreach ($drug['conditions'] as $condition) {
                    if (!array_key_exists($condition['answer_id'], $drugs_hash_map)) {
                        $drugs_hash_map[$condition['answer_id']][] = $drug['id'];
                    } else {
                        if (!in_array($drug['id'], $drugs_hash_map[$condition['answer_id']])) {
                            $drugs_hash_map[$condition['answer_id']][] = $drug['id'];
                        }
                    }
                }
            }
            foreach ($df['managements'] as $management) {
                foreach ($management['conditions'] as $condition) {
                    if (!array_key_exists($condition['answer_id'], $managements_hash_map)) {
                        $managements_hash_map[$condition['answer_id']][] = $management['id'];
                    } else {
                        if (!in_array($management['id'], $managements_hash_map[$condition['answer_id']])) {
                            $managements_hash_map[$condition['answer_id']][] = $management['id'];
                        }
                    }
                }
            }
        }

        foreach ($cached_data['registration_nodes_id'] as $node_id) {
            $this->nodes_to_save[$node_id] = "";
            if (is_int($node_id)) {
                $registration_nodes[$node_id] = $node_id;
            }
        }



        // First Look Assessment nodes
        foreach ($cached_data['first_look_assessment_nodes_id'] as $substep_name => $substep) {
            foreach ($substep as $node_id) {
                if ($node_id !== $cached_data['general_cc_id'] && $node_id !== $cached_data['yi_general_cc_id']) {
                    $this->nodes_to_save[$node_id] = "";
                    $node = $cached_data['full_nodes'][$node_id];
                    $age_key = $node['is_neonat'] ? 'neonat' : 'older';
                    $node_to_add = $node_id;
                    if ($node['category'] === "basic_measurement" || $node['category'] === "unique_triage_question" || $node['category'] === "background_calculation") {
                        $first_look_assessment_nodes[$substep_name][$node_id] = $node_to_add;
                    } else {
                        $first_look_assessment_nodes[$substep_name][$age_key][$node_id] = $node_to_add;
                    }
                }
            }
        }

        foreach ($cached_data['tests_nodes_id'] as $node_id) {
            $tests_nodes[$node_id] = $node_id;
        }

        foreach ($cached_data['diagnoses_nodes_id'] as $node_id) {
            $diagnoses_nodes[$node_id] = $node_id;
        }

        $formula_hash_map = [];
        $nodes_to_update = [];
        $conditioned_nodes_hash_map = [];
        JsonParser::parse(Storage::get("$extract_dir/$id.json"))
            ->pointer('/medal_r_json/nodes')
            ->traverse(function (mixed $value, string|int $key, JsonParser $parser) use ($cached_data, &$formula_hash_map, &$nodes_to_update, &$conditioned_nodes_hash_map) {
                foreach ($value as $node) {
                    //todo work with QuestionsSequence
                    if ($node['type'] === 'QuestionsSequence' || $node['display_format'] === 'Reference') {
                        continue;
                    }
                    if ($node['display_format'] === "Input" || $node['display_format'] === "Formula") {
                        $this->nodes_to_save[$node['id']] = "";
                    }
                    if ($node['category'] === "background_calculation" || $node['display_format'] === "Formula") {
                        $formula_hash_map[$node['id']] = $node['formula'];
                        $this->algorithmService->handleNodesToUpdate($node, $nodes_to_update);
                    }
                    if (!empty($node['conditioned_by_cc'])) {
                        foreach ($node['conditioned_by_cc'] as $cc_id) {
                            $conditioned_nodes_hash_map[$cc_id][] = $node['id'];
                        }
                    }
                }
            });

        $answers_hash_map = [];
        $dependency_map = [];
        $consultation_nodes = [];
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

                    $node = $cached_data['full_nodes'][$instance_id];
                    $age_key = $node['is_neonat'] ? 'neonat' : 'older';
                    if (empty($instance['conditions'])) {

                        // We don't care about background calculations
                        if (!array_key_exists('system', $cached_data['full_nodes'][$instance_id])) {
                            continue;
                        }

                        if ($cached_data['full_nodes'][$instance_id]['category'] === 'physical_exam') {
                            continue;
                        }

                        $system = $node['category'] !== 'background_calculation' ? $node['system'] : 'others';
                        $consultation_nodes[$age_key][$system][$step][$instance_id] = $node['id'];
                    } else {
                        foreach ($instance['conditions'] as $condition) {
                            $answer_id = $condition['answer_id'];
                            $node_id = $condition['node_id'];

                            $answers_hash_map[$step][$answer_id][] = $instance_id;

                            $this->algorithmService->breadthFirstSearch($diag, $node_id, $answer_id, $dependency_map);
                        }
                    }
                }
            }
        }

        $consultation_nodes = $this->sortSystemsAndNodes($consultation_nodes);

        $nodes_per_step = [
            'registration' => $registration_nodes,
            'first_look_assessment' => $first_look_assessment_nodes,
            'consultation' => $consultation_nodes,
            'tests' => $tests_nodes ?? [], // No tests for non dynamic study
            'diagnoses' => $diagnoses_nodes ?? [], // No diagnoses for non dynamic study
        ];

        // We already know that every nodes inside $nodes_per_step are the one without condition
        $no_condition_nodes = array_flip(array_unique(Arr::flatten($nodes_per_step)));

        if (!$cache_found) {
            Cache::put($this->cache_key, [
                ...$cached_data,
                'answers_hash_map' => $answers_hash_map,
                'formula_hash_map' => $formula_hash_map,
                'nodes_to_update' => $nodes_to_update,
                'df_hash_map' => $df_hash_map,
                'drugs_hash_map' => $drugs_hash_map,
                'conditioned_nodes_hash_map' => $conditioned_nodes_hash_map,
                'managements_hash_map' => $managements_hash_map,
                'dependency_map' => $dependency_map,
                'nodes_per_step' => $nodes_per_step,
                'no_condition_nodes' => $no_condition_nodes,
            ], $this->cache_expiration_time);
            $cached_data = Cache::get($this->cache_key);
        }

        $this->current_nodes = $registration_nodes;

        //todo remove these when in prod
        $this->current_cc = $this->age_key === "older"
            ? $cached_data['general_cc_id']
            : $cached_data['yi_general_cc_id'];


        $this->chosen_complaint_categories[] = "{$cached_data['general_cc_id']}";


        // dd($this->registration_nodes_id);
        // dd($cached_data);
        // dump($conditioned_nodes_hash_map);
        // dd($cached_data['full_nodes']);
        // dump($this->nodes_to_save);
        // dump($cached_data['full_order']);
        dump($cached_data['nodes_per_step']);
        // dump($cached_data['nodes_per_step']);
        // dump(array_unique(Arr::flatten($cached_data['nodes_per_step'])));
        // dump($cached_data['formula_hash_map']);
        dump($cached_data['answers_hash_map']);
        dump($cached_data['consultation_nodes']);
        // dump($cached_data['nodes_to_update']);
        // dump($cached_data['managements_hash_map']);
    }

    #[On('nodeToSave')]
    public function saveNode($node_id, $value, $answer_id, $old_answer_id)
    {
        $cached_data = Cache::get($this->cache_key);
        $formula_hash_map = $cached_data['formula_hash_map'];
        $drugs_hash_map = $cached_data['drugs_hash_map'];
        $managements_hash_map = $cached_data['managements_hash_map'];
        $nodes_to_update = $cached_data['nodes_to_update'];

        if (array_key_exists($node_id, $this->nodes_to_save)) {
            if (array_key_exists($node_id, $formula_hash_map)) {
                $value = $this->handleFormula($node_id);
            }
            $this->nodes_to_save[$node_id] = intval($value);

            // If answer will set a drug, we add it to the drugs to display
            if (array_key_exists($value, $drugs_hash_map)) {
                $this->drugs_to_display = [
                    ...$this->drugs_to_display,
                    ...$drugs_hash_map[$value]
                ];
            }

            // If answer will set a management, we add it to the managements to display
            if (array_key_exists($value, $managements_hash_map)) {
                $this->all_managements_to_display = [
                    ...$this->all_managements_to_display,
                    ...$managements_hash_map[$value]
                ];
            }

            // If node is linked to some bc, we calculate them directly
            if (array_key_exists($node_id, $nodes_to_update)) {
                foreach ($nodes_to_update[$node_id] as $node_id) {
                    $this->saveNode($node_id, null, null, null);
                }
            }
        }

        return $this->displayNextNode($node_id, $answer_id, $old_answer_id);
    }

    #[On('nodeUpdated')]
    public function displayNextNode($node_id, $value, $old_value)
    {
        $cached_data = Cache::get($this->cache_key);
        $dependency_map = $cached_data['dependency_map'];
        $formula_hash_map = $cached_data['formula_hash_map'];
        $final_diagnoses = $cached_data['final_diagnoses'];
        $df_hash_map = $cached_data['df_hash_map'];
        $health_cares = $cached_data['health_cares'];
        $nodes_per_step = $cached_data['nodes_per_step'];

        // Modification behavior
        if ($old_value) {

            // Remove every old answer nodes dependency
            if (array_key_exists($old_value, $dependency_map)) {
                foreach ($dependency_map[$old_value] as $key) {
                    unset($this->current_nodes[$this->current_cc][$key]);
                }
            }

            // Remove every df and managements dependency
            if (isset($df_hash_map[$this->current_cc][$old_value])) {
                foreach ($df_hash_map[$this->current_cc][$old_value] as $df) {
                    if (array_key_exists($df, $this->df_to_display)) {
                        if (isset($final_diagnoses[$df]['managements'])) {
                            unset($this->all_managements_to_display[key($final_diagnoses[$df]['managements'])]);
                        }
                        unset($this->df_to_display[$df]);
                    }
                }
            }
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
            $other_conditions_met = true;
            foreach ($df_hash_map[$this->current_cc][$value] as $df) {
                foreach ($final_diagnoses[$df]['conditions'] as $condition) {
                    // We already know that this condition is met because it has been calulated
                    // And we skip the same question if it's the condition
                    if ($condition['answer_id'] !== $value && $condition['node_id'] !== $node_id) {
                        //todo fix current nodes management. Should be the same for every step
                        // We only check if the other conditions node has no condition
                        // We need to find a way to do so as now the current_nodes is being changed depending on the step
                        // getTopConditions in react-native reader

                        if (in_array($condition['node_id'], $this->current_nodes)) {
                            // Need also to calculate if node is not in nodes_to_save like radio button
                            if (
                                array_key_exists($condition['node_id'], $this->nodes_to_save)
                                && intval($this->nodes_to_save[$condition['node_id']]) != $condition['answer_id']
                            ) {
                                $other_conditions_met = false;
                            }
                        }
                    }
                }

                if ($other_conditions_met) {
                    if (!array_key_exists($final_diagnoses[$df]['id'], $this->df_to_display)) {
                        $drugs = [];
                        foreach ($final_diagnoses[$df]['drugs'] as $drug_id => $drug) {

                            $conditions = $final_diagnoses[$df]['drugs'][$drug['id']]['conditions'];

                            if (array_key_exists($drug_id, $drugs)) continue;
                            if (empty($conditions)) {
                                $drugs[$drug_id] = $drug_id;
                            } else {
                                if (in_array($drug['id'], $this->drugs_to_display)) {
                                    $drugs[$drug_id] = $drug_id;
                                }
                            }
                        }

                        $this->df_to_display[$df] = [
                            'id' => $final_diagnoses[$df]['id'],
                            'label' => $final_diagnoses[$df]['label']['en'] ?? '',
                            'description' => $final_diagnoses[$df]['description']['en'] ?? '',
                            'level_of_urgency' => $final_diagnoses[$df]['level_of_urgency'],
                            'drugs' => $drugs
                        ];

                        foreach ($final_diagnoses[$df]['managements'] as $management_key => $management) {
                            $conditions = $final_diagnoses[$df]['managements'][$management_key]['conditions'];
                            if (empty($conditions)) {
                                $this->managements_to_display[$management_key] = $final_diagnoses[$df]['id'];
                            } else {
                                if (in_array($management_key, $this->all_managements_to_display)) {
                                    $this->managements_to_display[$management_key] = $final_diagnoses[$df]['id'];
                                }
                            }
                        }
                    }
                }
            }
        }


        // Reorder DF and managements upon level_of_urgency
        uasort($this->df_to_display, fn ($a, $b) => $b['level_of_urgency'] <=> $a['level_of_urgency']);
        uksort($this->managements_to_display, function ($a, $b) use ($health_cares) {
            return $health_cares[$b]['level_of_urgency'] <=> $health_cares[$a]['level_of_urgency'];
        });

        if ($next_node_id) {
            foreach ($next_node_id as $node) {
                $this->setNextNodeAndSort($node);
            }
        }
    }

    public function handleFormula($node_id)
    {
        $cached_data = Cache::get($this->cache_key);
        $formula_hash_map = $cached_data['formula_hash_map'];
        $full_nodes = $cached_data['full_nodes'];
        $general_cc_id = $cached_data['general_cc_id'];
        $yi_general_cc_id = $cached_data['yi_general_cc_id'];

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
                    if ($days <= 59) {
                        $this->age_key = 'neonat';
                        $this->current_cc = $yi_general_cc_id;
                        //todo in case of change need to remove the other
                        // Ouch btw having to set it as a string
                        $this->chosen_complaint_categories[] = "$yi_general_cc_id";
                    } else {
                        $this->age_key = 'older';
                        $this->current_cc = $general_cc_id;
                        $this->chosen_complaint_categories[] = "$general_cc_id";
                    }
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



    public function sortSystemsAndNodes(array $nodes): array
    {
        $cached_data = Cache::get($this->cache_key);
        $consultation_nodes = $cached_data['consultation_nodes'];
        $systems = array_keys($consultation_nodes);
        $desired_systems_order = array_values($systems);
        $title_position_map = array_flip($desired_systems_order);

        foreach ($nodes as &$system) {

            uksort($system, function ($a, $b) use ($title_position_map) {
                return $title_position_map[$a] - $title_position_map[$b];
            });

            foreach ($system as $key => &$cc_nodes) {
                $order = array_flip($consultation_nodes[$key]['data']);
                foreach ($cc_nodes as &$nodes_per_cc) {
                    uksort($nodes_per_cc, function ($a, $b) use ($order) {
                        return $order[$a] - $order[$b];
                    });
                }
            }
        }

        return $nodes;
    }

    public function sortNodes(array $nodes): array
    {
        $cached_data = Cache::get($this->cache_key);
        $consultation_nodes = $cached_data['consultation_nodes'];

        foreach ($nodes as $system_name => &$nodes_per_system) {
            $order = array_flip($consultation_nodes[$system_name]['data']);
            uksort($nodes_per_system, function ($a, $b) use ($order) {
                if (!isset($order[$a]) || !isset($order[$b])) return;
                return $order[$a] - $order[$b];
            });
        }

        return $nodes;
    }

    public function setNextNodeAndSort($next_node_id)
    {
        $cached_data = Cache::get($this->cache_key);
        $full_nodes = $cached_data['full_nodes'];
        $full_order_medical_history = $cached_data['full_order_medical_history'];

        if (isset($full_nodes[$next_node_id])) {

            $node = $full_nodes[$next_node_id];
            $system = isset($node['system']) ? $node['system'] : 'others';

            $this->current_nodes[$system][$next_node_id] = $node['id'];
            Log::info(json_encode($this->current_nodes));
            $this->current_nodes = $this->sortNodes($this->current_nodes);
            Log::info(json_encode($this->current_nodes));
        }
    }



    public function getNextNodeId($node_id)
    {
        $cached_data = Cache::get($this->cache_key);
        $answers_hash_map = $cached_data['answers_hash_map'];

        return $answers_hash_map[$this->current_cc][$node_id] ?? null;
    }

    public function goToStep(string $step): void
    {
        $cached_data = Cache::get($this->cache_key);
        $nodes_per_step = $cached_data['nodes_per_step'];
        $conditioned_nodes_hash_map = $cached_data['conditioned_nodes_hash_map'];

        if ($step === 'consultation') {
            $cc_order = $cached_data['complaint_categories_steps'];

            // Respect the order in the complaint_categories_step key
            usort($this->chosen_complaint_categories, function ($a, $b) use ($cc_order) {
                return array_search($a, $cc_order) <=> array_search($b, $cc_order);
            });

            //todo fix current nodes management. Should be the same for every step
            $this->current_cc = reset($this->chosen_complaint_categories);
            $current_nodes_per_step = $nodes_per_step[$step][$this->age_key];

            foreach ($current_nodes_per_step as $system_name => $system_data) {
                foreach ($system_data as $cc_id => $nodes) {

                    if (in_array($cc_id, $this->chosen_complaint_categories)) {
                        $current_nodes[$system_name] = $system_data[$cc_id];
                        continue;
                    }

                    // We only add nodes that are not excluded by CC
                    if (isset($conditioned_nodes_hash_map[$cc_id])) {
                        $current_nodes[$system_name] = array_diff(
                            $current_nodes[$system_name] ?? [],
                            $conditioned_nodes_hash_map[$cc_id]
                        );
                    }
                }
            }

            $this->current_nodes = $current_nodes;
        } else {
            // For registration step we do not know the $age_key yet
            $this->current_nodes = $cached_data['nodes_per_step'][$step];
        }
        $this->current_step = $step;
        if (!empty($this->steps[$this->current_step])) {
            $this->current_sub_step = $this->steps[$this->current_step][0];
        }
    }

    public function goToSubStep(string $step, string $substep): void
    {
        $cached_data = Cache::get($this->cache_key);
        // $final_diagnoses = $cached_data['final_diagnoses'];
        $health_cares = $cached_data['health_cares'];
        // change the step accordingly
        $this->goToStep($step);
        // declare the current sub step
        $this->current_sub_step = $substep;
        if (($substep === 'medicines') && isset($this->diagnoses_status) && count(array_filter($this->diagnoses_status))) {
            $agreed_diagnoses = array_filter($this->diagnoses_status);
            $common_agreed_diag_key = array_intersect_key($agreed_diagnoses, $this->df_to_display);
            // dd($common_agreed_diag_key);
            foreach ($common_agreed_diag_key as $diag_id => $value) {
                // $final_diagnoses[$diag_key]['drugs'];
                foreach ($this->df_to_display[$diag_id]['drugs'] as $drug_id => $drug) {
                    //the first formulation
                    // $this->diagnoses_formulation[$drug_id]=$diag_id;
                    if (empty($this->drugs_formulation[$drug_id])) {
                        if ((count($health_cares[$drug_id]['formulations']) > 1)) {
                            $formulation = $health_cares[$drug_id]['formulations'][0];
                            $this->drugs_formulation[$drug_id] = $formulation['id'];
                        }
                    }
                }
            }
        }
        // summary
        if (($substep === 'summary') && isset($this->drugs_status) && count(array_filter($this->drugs_status))) {
            // drug ids in drug_status and formulations in drugs_formulation
            $common_agreed_diag_key = array_intersect_key($this->df_to_display, array_filter($this->diagnoses_status));
            // dd($common_agreed_diag_key['drugs']);
            $common_agreed_drugs = array_intersect_key($this->drugs_formulation, array_filter($this->drugs_status));
            $formulations = new FormulationService($common_agreed_drugs, $common_agreed_diag_key, $this->cache_key);
            $this->formulations_to_display = $formulations->getFormulations();
            // give this to the service
            // dd($this->formulations_to_display);

        }
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

    public function render()
    {
        return view('livewire.algorithm');
    }
}
