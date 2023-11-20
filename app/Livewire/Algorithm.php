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
    public array $registration_nodes;
    public object $complaint_categories_nodes;
    public array $chosen_complaint_categories;
    public array $df_to_display;
    public array $managements_to_display;
    public array $nodes_to_save;
    public array $nodes;
    public $date_of_birth = "1960-01-01";
    public $currentStep = 'complaint_category';


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
        $general_cc_id = $json['medal_r_json']['config']['basic_questions']['general_cc_id'];
        $yi_general_cc_id = $json['medal_r_json']['config']['basic_questions']['yi_general_cc_id'];
        $gender_question_id = $json['medal_r_json']['config']['basic_questions']['gender_question_id'];

        // add redis

        $this->cache_key = "json_data_{$this->id}_$json_version";
        $this->cache_expiration_time = 86400; // 24 hours

        // Cache::flush();

        if (!Cache::has($this->cache_key)) {
            Cache::put($this->cache_key, [
                'full_nodes' => collect($json['medal_r_json']['nodes'])->keyBy('id')->all(),
                'instances' => $json['medal_r_json']['diagram']['instances'],
                'diagnoses' => $json['medal_r_json']['diagnoses'],
                'final_diagnoses' => $json['medal_r_json']['final_diagnoses'],
                'managements' => $json['medal_r_json']['health_cares'],
                'full_order' => $json['medal_r_json']['config']['full_order'],
                'full_order_medical_history' => $json['medal_r_json']['config']['full_order']['medical_history_step'][0]['data'],
                'registration_steps' => array_flip($json['medal_r_json']['config']['full_order']['registration_step']) + [42318 => "", 42323 => "", 42331 => ""],
                'complaint_categories_steps' => $json['medal_r_json']['config']['full_order']['complaint_categories_step']['older'],
                'answers_hash_map' => [],
                'formula_hash_map' => [],
                'df_hash_map' => [],
                'dependency_map' => [],
            ], $this->cache_expiration_time);
        }

        $cached_data = Cache::get($this->cache_key);

        $this->complaint_categories_nodes = collect($cached_data['full_nodes'])->filter(function ($node) use ($general_cc_id, $yi_general_cc_id) {
            return $node['category'] === 'complaint_category'
                && $node['id'] !== $general_cc_id
                && $node['id'] !== $yi_general_cc_id;
        })->map(function ($node) {
            return [
                'id' => $node['id'],
                'label' => $node['label']['en'] ?? '',
                'description' => $node['description']['en'] ?? '',
            ];
        })->values();

        $df_hash_map = [];
        foreach ($cached_data['final_diagnoses'] as $df) {
            foreach ($df['conditions'] as $condition) {
                $df_hash_map[$condition['answer_id']] = $df['id'];
            }
        }

        $formula_hash_map = [];
        JsonParser::parse(Storage::get("$extract_dir/$id.json"))
            ->pointer('/medal_r_json/nodes')
            ->traverse(function (mixed $value, string|int $key, JsonParser $parser) use ($cached_data, &$formula_hash_map) {
                foreach ($value as $node) {
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
                                ];
                            }, $node['answers'] ?? []),
                        ];
                    }
                    if ($node['display_format'] === "Input" || $node['display_format'] === "Formula") {
                        $this->nodes_to_save[$node['id']] = "";
                    }
                    if ($node['category'] === "background_calculation" || $node['display_format'] === "Formula") {
                        $formula_hash_map[$node['id']] = $node['formula'];
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

                    if ($instance_id === $gender_question_id) {
                        continue;
                    }

                    if (empty($instance['conditions'])) {
                        $this->nodes[$step][$instance_id] = [
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

                            //todo ask why an answer could appear in multiple diag
                            if (array_key_exists($answer_id, $answers_hash_map)) {
                                if (!is_array($answers_hash_map[$answer_id])) {
                                    $answers_hash_map[$answer_id] = [$answers_hash_map[$answer_id]];
                                }
                                $answers_hash_map[$answer_id][] = $instance_id;
                            } else {
                                $answers_hash_map[$answer_id] = $instance_id;
                            }

                            $this->breadthFirstSearch($diag, $node_id, $answer_id, $dependency_map);
                        }
                    }
                }
            }
        }

        Cache::put($this->cache_key, [
            ...$cached_data,
            'answers_hash_map' => $answers_hash_map,
            'formula_hash_map' => $formula_hash_map,
            'df_hash_map' => $df_hash_map,
            'dependency_map' => $dependency_map,
        ], $this->cache_expiration_time);

        // Order nodes
        if (isset($this->nodes[$step])) {
            foreach ($cached_data['full_order_medical_history'] as $node_id) {
                if (isset($this->nodes[$step][$node_id])) {
                    $reordered_nodes[$node_id] = $this->nodes[$step][$node_id];
                }
            }
            foreach ($this->nodes[$step] as $node_id => $node) {
                if (!isset($reordered_nodes[$node_id])) {
                    $reordered_nodes[$node_id] = $node;
                }
            }
            $this->nodes[$step] = $reordered_nodes;
        }


        // dd($this->registration_steps);
        // dd($this->nodes);

        dump($cached_data['answers_hash_map']);
        dump($cached_data['dependency_map']);
        dump($cached_data['formula_hash_map']);
        dump($cached_data['df_hash_map']);
        // dump($this->dependency_map);
        // dump($this->df_hash_map);
        // dump($this->formula_hash_map);
        // dd(Cache::get($this->cache_key));
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
                if (!empty($instance['conditions'])) {
                    $children = $instance['children'];
                    foreach ($instance['conditions'] as $condition) {
                        $children_node_id = $condition['node_id'];

                        if ($children_node_id === $node_id) {
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

        return $dependency_map;
    }

    #[On('nodeToSave')]
    public function saveNode($node_id, $value, $answer_id, $old_answer_id)
    {
        $cached_data = Cache::get($this->cache_key);
        $formula_hash_map = $cached_data['formula_hash_map'];
        if (array_key_exists($node_id, $this->nodes_to_save)) {
            if (array_key_exists($node_id, $formula_hash_map)) {
                $value = $this->handleFormula($node_id);
            }
            $this->nodes_to_save[$node_id] = intval($value);
        }

        //todo between with value like "25, 30" need to explode the ,
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
        $managements = $cached_data['managements'];
        $full_nodes = $cached_data['full_nodes'];
        $full_order_medical_history = $cached_data['full_order_medical_history'];

        $step = reset($this->chosen_complaint_categories);

        // Modification behavior
        if ($old_value) {
            // Remove every nodes dependency
            if (array_key_exists($old_value, $dependency_map)) {
                foreach ($dependency_map[$old_value] as $key) {
                    unset($this->nodes[$step][$key]);
                }
            }

            // Remove every df and managements dependency
            if (isset($df_hash_map[$old_value])) {
                if (array_key_exists($df_hash_map[$old_value], $this->df_to_display)) {
                    if (isset($final_diagnoses[$df_hash_map[$old_value]]['managements'])) {
                        unset($this->managements_to_display[key($final_diagnoses[$df_hash_map[$old_value]]['managements'])]);
                    }
                    unset($this->df_to_display[$df_hash_map[$old_value]]);
                }
            }
        }

        $next_node_id = $this->getNextQuestionId($value);

        //if next node is background calc -> calc and directly show next <3
        if (array_key_exists($next_node_id, $formula_hash_map)) {
            $this->nodes_to_save[$next_node_id] = intval($value);
            $next_node_id = $this->getNextQuestionId($value);
        }

        //if next node is DF, add it to df_to_display <3
        if (isset($df_hash_map[$value])) {
            $other_conditons_met = true;
            foreach ($final_diagnoses[$df_hash_map[$value]]['conditions'] as $condition) {
                // We already know that this condition is met because it has been calulated
                if ($condition['answer_id'] !== $value) {
                    if (
                        array_key_exists($condition['node_id'], $this->nodes_to_save)
                        && intval($this->nodes_to_save[$condition['node_id']]) != $condition['answer_id']
                    ) {
                        $other_conditons_met = false;
                    }
                }
            }

            if ($other_conditons_met) {
                if (!array_key_exists($final_diagnoses[$df_hash_map[$value]]['id'], $this->df_to_display)) {
                    $this->df_to_display[$df_hash_map[$value]] = [
                        'id' => $final_diagnoses[$df_hash_map[$value]]['id'],
                        'label' => $final_diagnoses[$df_hash_map[$value]]['label']['en'] ?? '',
                        'description' => $final_diagnoses[$df_hash_map[$value]]['description']['en'] ?? '',
                        'level_of_urgency' => $final_diagnoses[$df_hash_map[$value]]['level_of_urgency'],
                    ];
                }
                //todo when multiple managements sets what to do ?
                $management_key = key($final_diagnoses[$df_hash_map[$value]]['managements']);

                // Because sometime df have no managements
                if (isset($managements[$management_key]['id'])) {
                    if (!array_key_exists($managements[$management_key]['id'], $this->managements_to_display)) {
                        $this->managements_to_display[$management_key] = [
                            'id' => $managements[$management_key]['id'],
                            'label' => $managements[$management_key]['label']['en'] ?? '',
                            'description' => $managements[$management_key]['description']['en'] ?? '',
                            'level_of_urgency' => $managements[$management_key]['level_of_urgency'],
                        ];
                    }
                }
            }
        }

        // Reorder DF and managements upon level_of_urgency
        uasort($this->df_to_display, fn ($a, $b) => $b['level_of_urgency'] <=> $a['level_of_urgency']);
        uasort($this->managements_to_display, fn ($a, $b) => $b['level_of_urgency'] <=> $a['level_of_urgency']);

        if ($next_node_id && isset($full_nodes[$next_node_id])) {
            $this->nodes[$step][$next_node_id] = [
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
                if (isset($this->nodes[$step][$node_id])) {
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
                        $reordered_nodes[$node_id] = $this->nodes[$step][$node_id];
                    }
                }
            }
            // Merge nodes that were not in full_order_medical_history but are present in $this->nodes[$step]
            foreach ($this->nodes[$step] as $node_id => $node) {
                if (!isset($reordered_nodes[$node_id])) {
                    $reordered_nodes[$node_id] = $node;
                }
            }
            $this->nodes[$step] = $reordered_nodes;
        }

        //todo if next nodes is DF
    }

    public function updatingChosenComplaintCategories($value)
    {
        if (!array_key_exists($value, $this->nodes)) {
            return;
        }
        $formula_hash_map = Cache::get($this->cache_key)['formula_hash_map'];
        $nodes_to_save = [];

        // We only need to refresh background_calculation nodes
        foreach ($this->registration_nodes as $node) {
            if ($node['category'] === 'background_calculation' || $node['display_format'] === 'Formula')
                $nodes_to_save[$node['id']] = $node;
        }

        $nodes_to_save = $nodes_to_save + array_intersect_key($this->nodes[$value], $formula_hash_map);
        if (!empty($nodes_to_save)) {
            foreach ($nodes_to_save as $node) {
                $this->saveNode($node['id'], null, null, null);
            }
        }
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
                return $interval->format('%a');
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

    public function getNextQuestionId($node_id)
    {
        $answers_hash_map = Cache::get($this->cache_key)['answers_hash_map'];
        //todo quick and dirty fix for now as an answer can have multiple nodes displayed next
        if (is_array($answers_hash_map[$node_id] ?? null)) {
            return reset($answers_hash_map[$node_id]) ?? null;
        }
        return $answers_hash_map[$node_id] ?? null;
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
