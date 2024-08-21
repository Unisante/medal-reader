<?php

namespace App\Livewire;

use App\Services\AlgorithmService;
use App\Services\FHIRService;
use App\Services\FormulationService;
use App\Services\JsonExportService;
use App\Services\ReferenceCalculator;
use Cerbero\JsonParser\JsonParser;
use DateTime;
use DCarbone\PHPFHIRGenerated\R4\FHIRResource\FHIRDomainResource\FHIRPatient;
use DCarbone\PHPFHIRGenerated\R4\PHPFHIRResponseParser;
use DivisionByZeroError;
use Exception;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Attributes\Validate;
use Livewire\Component;
use RecursiveArrayIterator;
use RecursiveIteratorIterator;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

class Refacto extends Component
{
    public int $id;
    public array $algorithm;
    public $patient_id;
    public array $data;
    public string $cache_key;
    public string $title;
    public string $algorithm_type;
    public bool $debug_mode = false;
    //todo remove definition when in prod
    public string $age_key = 'older';
    public string $current_step = 'registration';
    public int $saved_step = 1;
    public int $current_cc;
    public object $complaint_categories_nodes;
    public array $chosen_complaint_categories;
    public array $df_to_display;
    public array $diagnoses_per_cc;
    public array $drugs_to_display;
    public array $managements_to_display;
    public array $all_managements_to_display;
    public array $nodes_to_save;
    public int $age_in_days;
    #[Validate([
        // 'current_nodes.registration.birth_date' => 'required:date',
        'current_nodes.registration.*' => 'required',
        // 'current_nodes.consultation.*.*' => 'required',
    ], message: [
        'required' => 'This field is required',
        'date' => 'The date of birth is required to continue',
    ])]
    public array $current_nodes;
    public array $medical_case;
    public array $nodes;
    public array $diagnoses_status;
    public array $drugs_status;
    public array $drugs_formulation;
    public array $formulations_to_display;

    private AlgorithmService $algorithmService;
    private ReferenceCalculator $referenceCalculator;
    private FHIRService $fhirService;
    private JsonExportService $jsonExportService;
    public array $treatment_questions;
    // private array $diagnoses_formulation;
    // public array $drugs_formulations;
    public array $steps =  [
        'dynamic' => [
            'registration' => [],
            'first_look_assessment' => [
                'vital_signs',
                'complaint_categories',
                'basic_measurement',
            ],
            'consultation' => [
                'medical_history',
                'physical_exam',
            ],
            'tests' => [],
            'diagnoses' => [
                'final_diagnoses',
                'treatment_questions',
                'medicines',
                'summary',
                'referral',
            ],
        ],
        'prevention' => [
            'registration' => [],
            'first_look_assessment' => [],
            'consultation' => [],
            'diagnoses' => [],
        ],
        'training' => [
            'consultation' => [],
            'diagnoses' => [],
        ],
    ];

    public array $completion_per_step = [
        'registration' => [
            'start' => 0,
            'end' => 0,
        ],
        'first_look_assessment' => [
            'start' => 0,
            'end' => 0,
        ],
        'consultation' => [
            'start' => 0,
            'end' => 0,
        ],
        'tests' => [
            'start' => 0,
            'end' => 0,
        ],
        'diagnoses' => [
            'start' => 0,
            'end' => 0,
        ],
    ];

    public array $completion_per_substep = [];

    public string $current_sub_step = '';

    public function boot(
        AlgorithmService $algorithmService,
        ReferenceCalculator $referenceCalculator,
        FHIRService $fhirService,
        JsonExportService $jsonExportService,
    ) {
        $this->algorithmService = $algorithmService;
        $this->referenceCalculator = $referenceCalculator;
        $this->fhirService = $fhirService;
        $this->jsonExportService = $jsonExportService;
    }

    public function mount($id = null, $patient_id = null, $data = [])
    {
        $this->id = $id;
        $this->patient_id = $patient_id;
        $this->data = $data;

        $extract_dir = Config::get('medal.storage.json_extract_dir');
        $json = json_decode(Storage::get("$extract_dir/{$this->id}.json"), true);
        if (!$json) {
            abort(404);
        }
        $this->title = $json['name'];
        $json_version = $json['medal_r_json_version'];
        $project_name = $json['algorithm_name'] ?? $json['medal_r_json']['algorithm_name'];
        $matching_projects = array_filter(config('medal.projects'), function ($project) use ($project_name) {
            return Str::contains($project_name, $project);
        });

        $this->algorithm_type = $matching_projects ? key($matching_projects) : 'training';
        $this->cache_key = "json_data_{$this->id}_$json_version";

        //todo set that up in redis when in prod
        if (config('app.debug')) {
            Cache::forget($this->cache_key);
        }

        $this->algorithm['item'] = $json['medal_r_json'];

        $cache_found = Cache::has($this->cache_key);
        if (!$cache_found) {
            Cache::put($this->cache_key, [
                'full_nodes' => collect($json['medal_r_json']['nodes'])->keyBy('id')->all(),
                'instances' => $json['medal_r_json']['diagram']['instances'],
                'diagnoses' => $json['medal_r_json']['diagnoses'],
                'final_diagnoses' => $json['medal_r_json']['final_diagnoses'],
                'health_cares' => $json['medal_r_json']['health_cares'],
                'full_order' => $json['medal_r_json']['config']['full_order'],
                'full_order_medical_history' => $json['medal_r_json']['config']['full_order']['medical_history_step'][0]['data'] ?? [],
                'registration_nodes_id' => [
                    ...$json['medal_r_json']['config']['full_order']['registration_step'],
                    // ...$json['medal_r_json']['patient_level_questions'],
                ],
                'first_look_assessment_nodes_id' => [
                    'first_look_nodes_id' => $json['medal_r_json']['config']['full_order']['first_look_assessment_step'] ?? [],
                    'complaint_categories_nodes_id' => [
                        ...$json['medal_r_json']['config']['full_order']['complaint_categories_step']['older'],
                        ...$json['medal_r_json']['config']['full_order']['complaint_categories_step']['neonat']
                    ],
                    'basic_measurements_nodes_id' => $json['medal_r_json']['config']['full_order']['basic_measurements_step'],
                ],

                'consultation_nodes' => [
                    'medical_history' => [
                        ...array_combine(
                            array_column($json['medal_r_json']['config']['full_order']['medical_history_step'] ?? [], 'title'),
                            array_values($json['medal_r_json']['config']['full_order']['medical_history_step'] ?? [])
                        ),
                        ...['others' => ['title' => 'others', 'data' => []]]
                    ],
                    'physical_exam' => [
                        ...array_combine(
                            array_column($json['medal_r_json']['config']['full_order']['physical_exam_step'] ?? [], 'title'),
                            array_values($json['medal_r_json']['config']['full_order']['physical_exam_step'] ?? [])
                        ),
                        ...['others' => ['title' => 'others', 'data' => []]]
                    ]
                ],
                'tests_nodes_id' => $json['medal_r_json']['config']['full_order']['test_step'] ?? [],
                'diagnoses_nodes_id' => [
                    ...$json['medal_r_json']['config']['full_order']['health_care_questions_step'] ?? [],
                    ...$json['medal_r_json']['config']['full_order']['referral_step'] ?? [],
                ],

                'complaint_categories_steps' => [
                    ...$json['medal_r_json']['config']['full_order']['complaint_categories_step']['older'],
                    ...$json['medal_r_json']['config']['full_order']['complaint_categories_step']['neonat']
                ],
                'birth_date_formulas' => $json['medal_r_json']['config']['birth_date_formulas'],
                'general_cc_id' => $json['medal_r_json']['config']['basic_questions']['general_cc_id'],
                'yi_general_cc_id' => $json['medal_r_json']['config']['basic_questions']['yi_general_cc_id'],
                'gender_question_id' => $json['medal_r_json']['config']['basic_questions']['gender_question_id'],
                'weight_question_id' => $json['medal_r_json']['config']['basic_questions']['weight_question_id'],
                'village_question_id' => $json['medal_r_json']['config']['optional_basic_questions']['village_question_id'],
                'villages' => array_merge(...$json['medal_r_json']['village_json'] ?? []), // No village for non dynamic study;

                // All logics that will be calulated
                'answers_hash_map' => [],
                'formula_hash_map' => [],
                'qs_hash_map' => [],
                'df_hash_map' => [],
                'excluding_df_hash_map' => [],
                'cut_off_hash_map' => [],
                'df_dd_mapping' => [],
                'drugs_hash_map' => [],
                'conditioned_nodes_hash_map' => [],
                'reference_hash_map' => [],
                'managements_hash_map' => [],
                'dependency_map' => [],
                'max_path_length' => [],
                'nodes_to_update' => [],
                'nodes_per_step' => [],
                'no_condition_nodes' => [],
                'need_emergency' => [],
                'female_gender_answer_id' => '',
                'male_gender_answer_id' => '',
                'registration_total' => '',
                'first_look_assessment_total' => '',
            ]);
        }

        $cached_data = Cache::get($this->cache_key);

        $df_hash_map = [];
        $excluding_df_hash_map = [];
        $drugs_hash_map = [];
        $managements_hash_map = [];
        $cut_off_hash_map = [];

        foreach ($cached_data['final_diagnoses'] as $df) {
            $excluding_df_hash_map[$df['id']] = $df['excluding_final_diagnoses'];
            foreach ($df['conditions'] as $condition) {
                $df_hash_map[$df['cc']][$df['diagnosis_id']][$condition['answer_id']][] = $df['id'];
                if (isset($condition['cut_off_start']) || isset($condition['cut_off_end'])) {
                    $cut_off_hash_map['df'][$df['id']][$condition['answer_id']] = [
                        'cut_off_start' => $condition['cut_off_start'],
                        'cut_off_end' => $condition['cut_off_end'],
                    ];
                }
            }

            foreach ($df['drugs'] as $drug) {
                foreach ($drug['conditions'] as $condition) {
                    if (!isset($drugs_hash_map[$df['cc']][$df['diagnosis_id']][$condition['answer_id']])) {
                        $drugs_hash_map[$df['cc']][$df['diagnosis_id']][$condition['answer_id']][] = $drug['id'];
                    } else {
                        if (!in_array($drug['id'], $drugs_hash_map[$df['cc']][$df['diagnosis_id']][$condition['answer_id']])) {
                            $drugs_hash_map[$df['cc']][$df['diagnosis_id']][$condition['answer_id']][] = $drug['id'];
                        }
                    }
                }
            }

            foreach ($df['managements'] as $management) {
                foreach ($management['conditions'] as $condition) {
                    if (!isset($managements_hash_map[$df['cc']][$df['diagnosis_id']][$condition['answer_id']])) {
                        $managements_hash_map[$df['cc']][$df['diagnosis_id']][$condition['answer_id']][] = $management['id'];
                    } else {
                        if (!in_array($management['id'], $managements_hash_map[$df['cc']][$df['diagnosis_id']][$condition['answer_id']])) {
                            $managements_hash_map[$df['cc']][$df['diagnosis_id']][$condition['answer_id']][] = $management['id'];
                        }
                    }
                }
            }
        }

        foreach ($cached_data['registration_nodes_id'] as $node_id) {
            $registration_nodes[$node_id] = '';
        }


        // First Look Assessment nodes
        if ($this->algorithm_type === 'dynamic' || $this->algorithm_type === 'prevention') {
            foreach ($cached_data['first_look_assessment_nodes_id'] as $substep_name => $substep) {
                foreach ($substep as $node_id) {
                    if ($node_id !== $cached_data['general_cc_id'] && $node_id !== $cached_data['yi_general_cc_id']) {
                        $this->nodes_to_save[$node_id] = [
                            'value' => '',
                            'answer_id' => '',
                            'label' => '',
                        ];
                        $node = $cached_data['full_nodes'][$node_id];
                        $age_key = $node['is_neonat'] ? 'neonat' : 'older';
                        if (
                            $node['category'] === "basic_measurement"
                            || $node['category'] === "unique_triage_question"
                            || $node['category'] === "background_calculation"
                        ) {
                            $first_look_assessment_nodes[$substep_name][$node_id] = '';
                        } else {
                            $first_look_assessment_nodes[$substep_name][$age_key][$node_id] = '';
                        }
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
        $reference_hash_map = [];
        $nodes_to_update = [];
        $conditioned_nodes_hash_map = [];
        $need_emergency = [];
        JsonParser::parse(Storage::get("$extract_dir/$id.json"))
            ->pointer('/medal_r_json/nodes')
            ->traverse(function (mixed $value, string|int $key, JsonParser $parser) use (&$formula_hash_map, &$nodes_to_update, &$conditioned_nodes_hash_map, &$need_emergency, &$reference_hash_map) {
                foreach ($value as $node) {

                    if ($node['type'] === 'QuestionsSequence') {
                        continue;
                    }

                    if ($node['display_format'] === 'Reference') {
                        // $reference_hash_map[$node['id']] = true;
                        $reference_hash_map[$node['reference_table_x_id']][] = $node['id'];
                        $reference_hash_map[$node['reference_table_y_id']][] = $node['id'];
                        if ($node['reference_table_z_id']) {
                            $reference_hash_map[$node['reference_table_z_id']][] = $node['id'];
                        }
                    }
                    if ($node['emergency_status'] === 'emergency') {
                        $need_emergency[$node['emergency_answer_id']] = $node['id'];
                    }
                    if ($node['display_format'] === "Input" || $node['display_format'] === "Formula" || $node['display_format'] === 'Reference') {
                        $this->nodes_to_save[$node['id']]  = [
                            'value' => '',
                            'answer_id' => '',
                            'label' => '',
                        ];
                    }
                    if ($node['category'] === "background_calculation" || $node['display_format'] === "Formula") {
                        // todo find another way to remove Reference from $formula_hash_map it's needed to filter from the total count of the percentage
                        // but for now it's added in $formula_hash_map and in nodes_to_save
                        $formula_hash_map[$node['id']] = $node['formula'] ?? '';
                        if (array_key_exists('formula', $node)) {
                            $this->algorithmService->handleNodesToUpdate($node, $nodes_to_update);
                        }
                    }
                    if (!empty($node['conditioned_by_cc'])) {
                        foreach ($node['conditioned_by_cc'] as $cc_id) {
                            $conditioned_nodes_hash_map[$cc_id][] = $node['id'];
                        }
                    }
                }
            });

        $answers_hash_map = [];
        $max_path_length = [];
        $consultation_nodes = [];
        $female_gender_answer_id = '';
        $male_gender_answer_id = '';
        $qs_hash_map = [];

        foreach ($cached_data['complaint_categories_steps'] as $step) {
            $diagnosesForStep = collect($cached_data['diagnoses'])->filter(function ($diag) use ($step, $female_gender_answer_id, $male_gender_answer_id, &$qs_hash_map) {
                return $diag['complaint_category'] === $step;
            });

            foreach ($diagnosesForStep as $diag) {

                if (isset($diag['cut_off_start']) || isset($diag['cut_off_end'])) {
                    $cut_off_hash_map['dd'][$step][$diag['id']] = [
                        'label' => $diag['label']['en'],
                        'cut_off_start' => $diag['cut_off_start'],
                        'cut_off_end' => $diag['cut_off_end'],
                    ];
                }

                foreach ($diag['instances'] as $instance_id => $instance) {

                    if (!array_key_exists('display_format', $cached_data['full_nodes'][$instance_id]) && $cached_data['full_nodes'][$instance_id]['type'] !== 'QuestionsSequence') {
                        continue;
                    }

                    if ($instance_id === $cached_data['gender_question_id']) {
                        $female_gender_answer_id = collect($cached_data['full_nodes'][$instance_id]['answers'])->where('value', 'female')->first()['id'];
                        $male_gender_answer_id = collect($cached_data['full_nodes'][$instance_id]['answers'])->where('value', 'male')->first()['id'];
                    }

                    $instance_node = $cached_data['full_nodes'][$instance_id];

                    if (empty($instance['conditions'])) {

                        if (!isset($dependency_map[$diag['id']])) {
                            $dependency_map[$diag['id']] = [];
                        }

                        if ($instance_node['type'] === 'QuestionsSequence' && $instance['final_diagnosis_id'] === null) {
                            $this->manageQS($cached_data, $diag, $instance_node, $step, $consultation_nodes, $answers_hash_map, $qs_hash_map, $dependency_map, true);
                        }

                        // We don't care about background calculations
                        if (!array_key_exists('system', $instance_node) && $instance_node['category'] !== 'unique_triage_question') {
                            continue;
                        }

                        $substep = $instance_node['category'] === 'physical_exam' ? 'physical_exam' : 'medical_history';

                        $system = $instance_node['category'] !== 'background_calculation' ? $instance_node['system'] ?? 'others' : 'others';
                        if ($instance['final_diagnosis_id'] === null) {
                            $consultation_nodes[$substep][$system][$step][$diag['id']][$instance_id] = '';
                        }
                    }

                    if (!empty($instance['conditions'])) {
                        foreach ($instance['conditions'] as $condition) {
                            $answer_id = $condition['answer_id'];
                            $node_id = $condition['node_id'];

                            // if ($instance_node['type'] !== 'QuestionsSequence' && $instance['final_diagnosis_id'] === null) {
                            if ($instance['final_diagnosis_id'] === null) {
                                if (!isset($answers_hash_map[$step][$diag['id']][$answer_id])) {
                                    $answers_hash_map[$step][$diag['id']][$answer_id] = [];
                                }

                                if (!in_array($instance_id, $answers_hash_map[$step][$diag['id']][$answer_id])) {
                                    $answers_hash_map[$step][$diag['id']][$answer_id][] = $instance_id;
                                }
                            }

                            if (isset($condition['cut_off_start']) || isset($condition['cut_off_end'])) {
                                $cut_off_hash_map['nodes'][$step][$instance_id][$answer_id] = [
                                    'cut_off_start' => $condition['cut_off_start'],
                                    'cut_off_end' => $condition['cut_off_end'],
                                ];
                            }

                            if ($instance_node['type'] === 'QuestionsSequence' && $instance['final_diagnosis_id'] === null) {
                                $this->manageQS($cached_data, $diag, $instance_node, $step, $consultation_nodes, $answers_hash_map, $qs_hash_map, $dependency_map, false, $answer_id);
                            }

                            $node = $cached_data['full_nodes'][$node_id];
                            if ($node['type'] !== 'QuestionsSequence') {
                                $this->algorithmService->breadthFirstSearch($diag['instances'], $diag['id'], $node_id, $answer_id, $dependency_map, true);
                            } else {
                                if ($instance['final_diagnosis_id'] === null) {
                                    $this->manageQS($cached_data, $diag, $node, $step, $consultation_nodes, $answers_hash_map, $qs_hash_map, $dependency_map, false, $answer_id);
                                }
                            }

                            foreach ($instance['children'] as $child_node_id) {
                                $child_node = $cached_data['full_nodes'][$child_node_id] ?? null;
                                if ($child_node) {
                                    if ($child_node['type'] !== 'QuestionsSequence') {
                                        $this->algorithmService->breadthFirstSearch($diag['instances'], $diag['id'], $child_node_id, $answer_id, $dependency_map);
                                    } else {
                                        if ($instance['final_diagnosis_id'] === null) {
                                            $this->manageQS($cached_data, $diag, $child_node, $step, $consultation_nodes, $answers_hash_map, $qs_hash_map, $dependency_map, empty($instance['conditions']));
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        if (empty($consultation_nodes)) {
            flash()->addError('Aucune question à afficher. Algorithme vide ou uniquement des questions de type démographique configurées');
            return redirect()->route("home.index");
        }

        foreach ($dependency_map as $diag_id => $answers) {
            foreach ($answers as $answer_id => $nodes) {
                $max_path_length[$diag_id][$answer_id] = count($nodes);
            }
        }

        // dd($consultation_nodes);
        // $this->algorithmService->sortSystemsAndNodesPerCCPerStep($consultation_nodes, $this->cache_key);

        $nodes_per_step = [
            'registration' => $registration_nodes,
            'first_look_assessment' => $first_look_assessment_nodes ?? [],
            'consultation' => $consultation_nodes,
            'tests' => $tests_nodes ?? [], // No tests for non dynamic study
            'diagnoses' => $diagnoses_nodes ?? [], // No diagnoses for non dynamic study
        ];

        // We know that every nodes inside $nodes_per_step are the one without condition
        $nodes_per_step_flatten = new RecursiveIteratorIterator(
            new RecursiveArrayIterator(
                [
                    ...array_filter(
                        $nodes_per_step,
                        fn($key) => $key !== 'consultation' && $key !== 'first_look_assessment',
                        ARRAY_FILTER_USE_KEY
                    ),
                    $first_look_assessment_nodes['basic_measurements_nodes_id']
                ]
            )
        );

        foreach ($nodes_per_step_flatten as $key => $value) {
            $no_condition_nodes[$key] = '';
        }

        foreach ($consultation_nodes as $nodes_per_system) {
            foreach ($nodes_per_system as $nodes_per_cc) {
                foreach ($nodes_per_cc as $cc_id => $nodes_per_dd) {
                    foreach ($nodes_per_dd as $dd_id => $nodes) {
                        foreach ($nodes as $node => $value) {
                            $no_condition_nodes[$cc_id][$node] = '';
                        }
                    }
                }
            }
        }

        //todo actually stop calulating again if cache found. Create function in service and
        //cache get or cache create and get
        if (!$cache_found) {
            Cache::put($this->cache_key, [
                ...$cached_data,
                'answers_hash_map' => $answers_hash_map,
                'formula_hash_map' => $formula_hash_map,
                'qs_hash_map' => $qs_hash_map,
                'nodes_to_update' => $nodes_to_update,
                'df_hash_map' => $df_hash_map,
                'excluding_df_hash_map' => $excluding_df_hash_map,
                'cut_off_hash_map' => $cut_off_hash_map,
                'drugs_hash_map' => $drugs_hash_map,
                'conditioned_nodes_hash_map' => $conditioned_nodes_hash_map,
                'reference_hash_map' => $reference_hash_map,
                'managements_hash_map' => $managements_hash_map,
                'dependency_map' => $dependency_map ?? [],
                'max_path_length' => $max_path_length,
                'nodes_per_step' => $nodes_per_step,
                'no_condition_nodes' => $no_condition_nodes,
                'need_emergency' => $need_emergency,
                'female_gender_answer_id' => $female_gender_answer_id,
                'male_gender_answer_id' => $male_gender_answer_id,
                'registration_total' => count($cached_data['registration_nodes_id']),
                'first_look_assessment_total' =>  count($cached_data['first_look_assessment_nodes_id']),
            ]);
            $cached_data = Cache::get($this->cache_key);
        }

        //todo remove these when in prod
        //START TO REMOVE
        $this->current_cc = $this->age_key === "older"
            ? $cached_data['general_cc_id']
            : $cached_data['yi_general_cc_id'];
        if ($this->algorithm_type !== 'prevention') {
            $this->chosen_complaint_categories[$cached_data['general_cc_id']] = true;
        }

        if ($this->algorithm_type === 'dynamic') {
            $this->current_nodes['first_look_assessment']['basic_measurements_nodes_id'] =
                $nodes_per_step['first_look_assessment']['basic_measurements_nodes_id'];

            $this->current_nodes['first_look_assessment']['complaint_categories_nodes_id'] =
                $cached_data['nodes_per_step']['first_look_assessment']['complaint_categories_nodes_id'][$this->age_key];
        }

        // $this->current_nodes['registration']['birth_date'] = '2022-01-01';
        $this->chosen_complaint_categories = [];
        $this->df_to_display = [];
        $this->diagnoses_per_cc = [];
        $this->drugs_to_display = [];
        $this->all_managements_to_display = [];
        // $this->updateLinkedNodesOfDob('1950-10-05');

        if ($this->algorithm_type === 'prevention') {
            unset($this->current_nodes['registration']['first_name']);
            unset($this->current_nodes['registration']['last_name']);
            $this->current_nodes['registration'] +=
                $nodes_per_step['first_look_assessment']['basic_measurements_nodes_id'];

            $this->current_nodes['first_look_assessment']['complaint_categories_nodes_id'] =
                $cached_data['nodes_per_step']['first_look_assessment']['complaint_categories_nodes_id'][$this->age_key];

            $this->current_nodes['registration'][42321] = 43854;
            $this->current_nodes['registration'][42318] = 80;
            $this->current_nodes['registration'][42323] = 170;

            // $this->current_nodes['registration']['birth_date'] = '2001-04-08';
            // $this->chosen_complaint_categories = [];
            // $this->df_to_display = [];
            // $this->diagnoses_per_cc = [];
            // $this->updateLinkedNodesOfDob('1950-10-05');
        }
        //END TO REMOVE

        // If we are in training mode then we go directly to consultation step
        if ($this->algorithm_type === 'training') {
            $this->chosen_complaint_categories[$cached_data['general_cc_id']] = true;
            foreach ($cached_data['diagnoses'] as $dd_id => $dd) {
                $this->diagnoses_per_cc[$cached_data['general_cc_id']][$dd_id] = $dd['label']['en'];
            }
            $this->saved_step = 2;
            $this->goToStep('consultation');
        }

        //For FHIR serveur wip
        if ($this->patient_id) {
            $response = $this->fhirService->getPatientFromRemoteFHIRServer($patient_id);
            $parser = new PHPFHIRResponseParser();
            if ($response->successful()) {
                /** @var FHIRBundle $patients_bundle */
                $patients_bundle = $parser->parse($response->json());
                /** @var FHIRPatient $patient_resource */
                $patient_resource = $patients_bundle->getEntry()[0]->getResource();
                $name = $patient_resource->getName()[0];
                $givenName = $name->getGiven()[0]->__toString();
                $familyName = $name->getFamily();
                $familyExtension = $familyName->getExtension()[0] ?? '';
                $gender = $patient_resource->getGender()->getValue()->getValue();
                $date_of_birth = $patient_resource->getBirthDate()->getValue()->__toString();
                /** @var FHIRAddress $address */
                $address = $patient_resource->getAddress()[0];
                $city = $address->getCity()->__toString();
                $this->current_nodes['registration']['first_name'] = $givenName;
                $this->current_nodes['registration']['last_name'] = "$familyName $familyExtension";
                $this->current_nodes['registration']['birth_date'] = $date_of_birth;
                $this->current_nodes['registration'][$cached_data['gender_question_id']] = $gender === 'female' ?
                    $cached_data['female_gender_answer_id'] :
                    $cached_data['male_gender_answer_id'];
                $this->current_nodes['registration'][$cached_data['village_question_id']] = $city;
                $this->updateLinkedNodesOfDob($date_of_birth);
                $this->calculateCompletionPercentage($cached_data);
            }
        }

        //For ERPNext wip
        if ($this->data) {
            $this->drugs_to_display = $this->drugs_to_display ?? [];
            $this->df_to_display = $this->df_to_display ?? [];
            $this->all_managements_to_display = $this->all_managements_to_display ?? [];
            $this->managements_to_display = $this->managements_to_display ?? [];

            $givenName = $this->data['first_name'];
            $familyName = $this->data['last_name'];
            $gender = $this->data['sex'];
            $date_of_birth = $this->data['dob'];
            $address = $this->data['__onload']['addr_list'][0] ?? '';
            $city = $address['city'] ?? '';
            $this->current_nodes['registration']['first_name'] = $givenName;
            $this->current_nodes['registration']['last_name'] = $familyName;
            $this->current_nodes['registration']['birth_date'] = $date_of_birth;
            $this->current_nodes['registration'][$cached_data['gender_question_id']] = $gender === 'Female' ?
                $cached_data['female_gender_answer_id'] :
                $cached_data['male_gender_answer_id'];
            $this->current_nodes['registration'][$cached_data['village_question_id']] = $city;
            $this->updateLinkedNodesOfDob($date_of_birth);
            $this->calculateCompletionPercentage($cached_data);
        }

        $this->medical_case = [
            'nodes' => [],
            'diagnosis' => [],
            'drugs' => [],
        ];

        $this->medical_case['nodes'] = $this->generateNewNodes($json['medal_r_json']['nodes']);
        $nodes = $this->manageRegistrationStep();
        // dump($registration_nodes);
        $this->current_nodes['registration'] = $nodes;

        // dd($this->registration_nodes_id);
        // dd($cached_data);
        // dd($cached_data['full_nodes']);
        // dd($this->current_nodes);
        // dump($cached_data['full_order']);
        // dump(json_encode($cached_data['nodes_per_step']));
        // dump($cached_data['nodes_per_step']);
        // dump($cached_data['formula_hash_map']);
        // dump($cached_data['dependency_map']);
        // dump($cached_data['dependency_map'][8708][5306]);
        // dump(json_encode($cached_data['dependency_map']));
        // dump($cached_data['qs_hash_map']);
        // dump($cached_data['answers_hash_map']);
        // dump(json_encode($cached_data['answers_hash_map']));
        // dump($cached_data['qs_hash_map']);
        // dump(json_encode($cached_data['qs_hash_map']));
        // dump($cached_data['no_condition_nodes']);
        // dump($consultation_nodes);
        // dump($cached_data['drugs_hash_map']);
        // dump($cached_data['df_hash_map']);
        // dump($cached_data['managements_hash_map']);
        // dump($cached_data['excluding_df_hash_map']);
        // dump($cached_data['cut_off_hash_map']);
        // dump($cached_data['df_dd_mapping']);
        // dump(json_encode($cached_data['consultation_nodes']));
        // dump($cached_data['nodes_to_update']);
        // dump($cached_data['max_path_length']);
        // dump($cached_data['reference_hash_map']);
    }

    private function manageQS($cached_data, $diag, $node, $step, &$consultation_nodes, &$answers_hash_map, &$qs_hash_map, &$dependency_map, $no_condition, $answer_id = null)
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
                    $this->algorithmService->breadthFirstSearch($diag['instances'], $diag['id'], $instance_id, $answer_id, $dependency_map);
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
                        $this->algorithmService->breadthFirstSearch($diag['instances'], $diag['id'], $node['id'], $answer_id ?? $condition['answer_id'], $dependency_map, true);
                    } else {
                        $this->manageQS($cached_data, $diag, $instance_node, $step, $consultation_nodes, $answers_hash_map, $qs_hash_map, $dependency_map, false, $condition['answer_id']);
                    }
                    if ($condition_node['type'] !== 'QuestionsSequence') {
                        $this->algorithmService->breadthFirstSearch($diag['instances'], $diag['id'], $node['id'], $answer_id ?? $condition['answer_id'], $dependency_map, true);
                    }
                }
            }

            foreach ($instance['children'] as $child_node_id) {
                $child_node = $cached_data['full_nodes'][$child_node_id];
                if ($child_node_id !== $node['id'] && $child_node['type'] === 'QuestionsSequence') {
                    $this->manageQS($cached_data, $diag, $child_node, $step, $consultation_nodes, $answers_hash_map, $qs_hash_map, $dependency_map, $no_condition);
                } else {
                    $this->algorithmService->breadthFirstSearch($diag['instances'], $diag['id'], $node['id'], $answer_id, $dependency_map, true);
                }
            }
        }
    }

    public function calculateCompletionPercentage($cached_data, $other_cc = null)
    {
        if ($this->current_step === 'diagnoses' || $this->current_step === 'tests') {
            return;
        }

        $max_path_length = $cached_data['max_path_length'];
        $formula_hash_map = $cached_data['formula_hash_map'];
        $total = 0;
        $current_nodes = [];
        // todo $current_nodes can contains background_calculation

        if ($this->current_step === 'registration') {
            $current_nodes = array_diff_key(
                $this->current_nodes[$this->current_step],
                $cached_data['formula_hash_map']
            );
            $total = count($current_nodes);
        }

        if ($this->current_step === 'first_look_assessment') {
            if ($this->algorithm_type === 'dynamic') {
                $current_nodes = array_diff_key(
                    $this->current_nodes[$this->current_step]['basic_measurements_nodes_id'],
                    $formula_hash_map
                );
                $total = count($current_nodes);

                //Todo remove that if as $current_nodes cannot be array_filter after
                if ($total === 0) {
                    $current_nodes = 1;
                    $total = 1;
                }
            }
            if ($this->algorithm_type === 'prevention') {
                $current_nodes = array_filter($this->chosen_complaint_categories);
                $total = 1;
            }
        }

        if ($this->current_step === 'consultation') {
            if ($this->algorithm_type !== 'dynamic') {
                // $current_nodes = array_filter($this->current_nodes[$this->current_step][$other_cc ?? $this->current_cc], function ($node) use ($formula_hash_map) {
                //     return !array_key_exists($node, $formula_hash_map);
                // }, ARRAY_FILTER_USE_KEY);
                $current_nodes = $this->current_nodes['consultation'][$other_cc ?? $this->current_cc];
            } else {
                $current_nodes = $this->current_nodes['consultation'];
                foreach ($current_nodes as $steps) {
                    foreach ($steps as $systems) {
                        foreach ($systems as $node_id => $answer_id) {
                            $flattened_array[$node_id] = $answer_id;
                        }
                    }
                }
                $current_nodes = $flattened_array;
            }

            foreach ($current_nodes as $node_id => $current_answer_id) {
                if (empty($current_answer_id)) {
                    $answers = array_keys($cached_data['full_nodes'][$node_id]['answers']);
                    $potential_total = $total;
                    $potential_totals = [];
                    foreach ($answers as $answer_id) {
                        if ($this->algorithm_type !== 'dynamic') {
                            foreach ($this->diagnoses_per_cc[$other_cc ?? $this->current_cc] as $dd_id => $l) {
                                $length = $max_path_length[$dd_id][$answer_id] ?? 0;
                                $potential_totals[] = $length;
                            }
                        }
                        if ($this->algorithm_type === 'dynamic') {
                            foreach ($this->diagnoses_per_cc[$other_cc ?? $this->current_cc] ?? [] as $dd_id => $l) {
                                $length = $max_path_length[$dd_id][$answer_id] ?? 0;
                                $potential_totals[] = $length;
                            }
                        }
                    }
                    $potential_total = max(!empty($potential_totals) ? $potential_totals : [0]);
                    $total += $potential_total;
                } else {
                    $total++;
                }
            }
        }

        //We manage the case where after answering a node present in multiple tree
        //The tree is now full empty
        if ($this->current_step !== 'first_look_assessment' && $this->algorithm_type === 'prevention' && empty($current_nodes)) {
            $current_answers = [1];
            $empty_nodes = 0;
            $total = 1;
        } else {
            $current_answers = array_filter($current_nodes);
            $empty_nodes = count($current_nodes) - count(array_filter($current_nodes));
        }

        $total = $this->current_step === 'consultation' ? $total + $empty_nodes : $total;
        $completion_percentage = count($current_answers) / $total * 100;

        if (isset($this->completion_per_substep[$other_cc ?? $this->current_cc]) && $this->algorithm_type === 'prevention' && $this->current_step !== 'registration' && $this->current_step !== 'first_look_assessment') {
            //substep management
            $start_percentage_substep = $this->completion_per_substep[$other_cc ?? $this->current_cc]['end'];
            $this->completion_per_substep[$other_cc ?? $this->current_cc]['start'] = $start_percentage_substep;
            $end_percentage_substep = intval(min(100, round($completion_percentage)));
            $this->completion_per_substep[$other_cc ?? $this->current_cc]['end'] = $end_percentage_substep;
            //step management
            $cc_done = count(array_filter($this->completion_per_substep, function ($item) {
                return $item['end'] >= 100;
            }));
            $total = count(array_filter($this->chosen_complaint_categories));
            $completion_percentage = $cc_done / $total * 100;
            $start_percentage = $this->completion_per_step[$this->current_step]['end'];
            $this->completion_per_step[$this->current_step]['start'] = $start_percentage;
            $end_percentage = intval(min(100, round($completion_percentage)));
            $this->completion_per_step[$this->current_step]['end'] = $end_percentage;
        } else {
            $start_percentage = $this->completion_per_step[$this->current_step]['end'];
            $this->completion_per_step[$this->current_step]['start'] = $start_percentage;
            $end_percentage = intval(min(100, round($completion_percentage)));
            $this->completion_per_step[$this->current_step]['end'] = $end_percentage;
        }
    }

    public function updatedCurrentNodes($value, $key)
    {
        $cached_data = Cache::get($this->cache_key);
        $need_emergency = $cached_data['need_emergency'];

        // We skip the life threatening checkbox but before
        // If the answer trigger the emergency modal
        if (Str::of($key)->contains('first_look_nodes_id')) {
            if ($value) {
                $this->dispatch('openEmergencyModal');
            }
            return;
        };

        if ($this->algorithmService->isDate($value)) {
            if ($value !== "") {
                //todo optimize this function. As it take 1,3s for 35 nodes
                $this->updateLinkedNodesOfDob($value);
                $this->calculateCompletionPercentage($cached_data);
            }
            return;
        }

        // If the answer trigger the emergency modal
        if ($this->algorithm_type === 'dynamic') {
            if (array_key_exists($value, $need_emergency)) {
                $this->dispatch('openEmergencyModal');
            }
        }

        $this->calculateCompletionPercentage($cached_data);
    }

    public function updatingCurrentNodes($value, $key)
    {
        if ($this->algorithmService->isDate($value)) return;
        $node_id = intval(Str::of($key)->explode('.')->last());
        Arr::set($this->current_nodes, $key, $value);

        // $this->saveNode($key, $node_id, $value, $value, $old_answer_id);
    }

    public function updatedChosenComplaintCategories()
    {
        $cached_data = Cache::get($this->cache_key);
        $cc_order = array_flip($cached_data['complaint_categories_steps']);

        if ($this->algorithm_type === 'prevention') {
            uksort($this->chosen_complaint_categories, function ($a, $b) use ($cc_order) {
                return $cc_order[$a] <=> $cc_order[$b];
            });
        }

        $this->calculateCompletionPercentage($cached_data);
    }

    public function updatingChosenComplaintCategories($key, int $modified_cc_id)
    {
        $cached_data = Cache::get($this->cache_key);
        $cut_off_hash_map = $cached_data['cut_off_hash_map'];
        $nodes_per_step = $cached_data['nodes_per_step'];
        $conditioned_nodes_hash_map = $cached_data['conditioned_nodes_hash_map'];
        $dependency_map = $cached_data['dependency_map'];
        $df_hash_map = $cached_data['df_hash_map'];
        $final_diagnoses = $cached_data['final_diagnoses'];
        $old_value = $this->chosen_complaint_categories[$modified_cc_id] ?? null;
        $current_nodes_per_step = $nodes_per_step['consultation']['medical_history']['general'] ?? $nodes_per_step['consultation']['medical_history'];

        // We only do this modification behavior if the consultation step has already been calculated
        if ($this->saved_step > 2) {
            if ($this->algorithm_type !== 'dynamic') {
                //Update the cc to no
                if ($old_value) {
                    foreach ($this->current_nodes['consultation'][$modified_cc_id] as $node_to_unset_id => $answer_to_unset_id) {
                        if (array_key_exists($node_to_unset_id, $this->nodes_to_save)) {
                            $answer_to_unset_id = $this->nodes_to_save[$node_to_unset_id]['answer_id'];

                            if (isset($df_hash_map[$answer_to_unset_id])) {
                                foreach ($df_hash_map[$answer_to_unset_id] as $df) {
                                    if (array_key_exists($df, $this->df_to_display)) {
                                        if (isset($final_diagnoses[$df]['managements'])) {
                                            unset($this->all_managements_to_display[key($final_diagnoses[$df]['managements'])]);
                                        }
                                        unset($this->df_to_display[$df]);
                                    }
                                }
                            }
                        }
                        if (array_key_exists($node_to_unset_id, $this->nodes_to_save)) {
                            $this->nodes_to_save[$node_to_unset_id] = [
                                'value' => '',
                                'answer_id' => '',
                                'label' => '',
                            ];
                        }
                    }
                    unset($this->current_nodes['consultation'][$modified_cc_id]);
                    unset($this->completion_per_substep[$modified_cc_id]);
                    //Update the cc to yes
                } else {
                    foreach ($this->current_nodes['consultation'] as $cc_id => $nodes) {
                        foreach ($nodes as $node_id => $v) {
                            $already_displayed_nodes[$node_id] = '';
                        }
                    }
                    //Add the default no conditions nodes
                    foreach ($current_nodes_per_step as $system_name => $system_data) {
                        if ($modified_cc_id === $system_name) {
                            foreach ($system_data as $node_id => $value) {
                                // if node is already displayed in another tree, no need to display it
                                if (!isset($already_displayed_nodes[$node_id])) {
                                    // Respect Cut Off
                                    if (isset($cut_off_hash_map['nodes'][$modified_cc_id][$node_id])) {
                                        foreach ($cut_off_hash_map['nodes'][$modified_cc_id][$node_id] as $answer_id => $condition) {
                                            if (intval($value) === $answer_id || in_array($answer_id, array_column($this->nodes_to_save, 'answer_id'))) {
                                                if ($condition['cut_off_start'] <= $this->age_in_days && $condition['cut_off_end'] > $this->age_in_days) {
                                                    $this->current_nodes['consultation'][$modified_cc_id][$node_id] = '';
                                                }
                                            }
                                        }
                                    } else {
                                        $this->current_nodes['consultation'][$modified_cc_id][$node_id] = '';
                                    }
                                }
                            }
                        }
                    }

                    //We save all registration node again in case of modification or dob not answered before that trigger
                    foreach ($this->current_nodes['registration'] as $registration_node_id => $a) {
                        if ($registration_node_id !== 'birth_date') {
                            if (array_key_exists($registration_node_id, $this->nodes_to_save)) {
                                if (!empty($this->nodes_to_save[$registration_node_id]['answer_id'])) {
                                    $this->displayNextNode($cached_data, $registration_node_id, $this->nodes_to_save[$registration_node_id]['answer_id'], null);
                                }
                            } else {
                                if (!empty($a)) {
                                    $this->displayNextNode($cached_data, $registration_node_id, $a, null);
                                }
                            }
                        }
                    }

                    foreach ($cut_off_hash_map['nodes'] as $cc_id => $nodes) {
                        foreach ($nodes as $node => $answers) {
                            foreach ($answers as $answer_id => $condition) {
                                if (isset($this->current_nodes['consultation'][$cc_id][$node]) || isset($this->current_nodes['registration'][$node])) {
                                    if (isset($this->age_in_days)) {
                                        if ($condition['cut_off_start'] <= $this->age_in_days && $condition['cut_off_end'] > $this->age_in_days) {
                                        } else {
                                            if (isset($this->current_nodes['consultation'][$cc_id][$node])) {
                                                unset($this->current_nodes['consultation'][$cc_id][$node]);
                                                if (array_key_exists($node, $this->nodes_to_save)) {
                                                    $this->nodes_to_save[$node] = [
                                                        'value' => '',
                                                        'answer_id' => '',
                                                        'label' => '',
                                                    ];
                                                }
                                            }
                                            // Remove every linked nodes to old answer
                                            if (isset($this->current_nodes['registration'][$node])) {
                                                foreach ($this->diagnoses_per_cc as $diag_cc_id => $dd_per_cc) {
                                                    foreach ($dd_per_cc as $dd_id => $label) {
                                                        $answer_id = isset($this->nodes_to_save[$node]) ? $this->nodes_to_save[$node]['answer_id'] : $this->current_nodes['registration'][$node];
                                                        if (isset($dependency_map[$dd_id]) && array_key_exists($answer_id, $dependency_map[$dd_id])) {
                                                            foreach ($dependency_map[$dd_id][$answer_id] as $node_id_to_unset) {
                                                                if (isset($this->current_nodes['consultation'][$diag_cc_id][$node_id_to_unset])) {
                                                                    unset($this->current_nodes['consultation'][$diag_cc_id][$node_id_to_unset]);
                                                                    if (array_key_exists($node_id_to_unset, $this->nodes_to_save)) {
                                                                        $this->nodes_to_save[$node_id_to_unset] = [
                                                                            'value' => '',
                                                                            'answer_id' => '',
                                                                            'label' => '',
                                                                        ];
                                                                    }
                                                                }
                                                            }
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                        // if ($need_to_update && array_key_exists($cc_id, array_filter($this->chosen_complaint_categories))) {
                        //     $this->calculateCompletionPercentage($cc_id);
                        // }

                        foreach ($this->chosen_complaint_categories as $cc_id => $nodes) {
                            foreach ($this->current_nodes['consultation'][$cc_id] as $n => $a) {
                                if (isset($present_node[$n])) {
                                    unset($this->current_nodes['consultation'][$cc_id][$n]);
                                }
                                $present_node[$n] = '';
                            }
                        }
                    }

                    $this->completion_per_substep[$modified_cc_id] = [
                        'start' => 0,
                        'end' => 0,
                    ];
                }
            } else {
                // Meaning the complaint category has been changed to no
                if ($old_value) {

                    foreach ($this->current_nodes['consultation']['medical_history'] as $system_name => $system_data) {
                        foreach ($current_nodes_per_step as $system_name => $system_data) {
                            foreach ($system_data as $cc_id => $nodes) {

                                // We remove nodes that were linked to that CC
                                if (isset($conditioned_nodes_hash_map[$modified_cc_id])) {
                                    $this->current_nodes['consultation']['medical_history'][$system_name] = array_diff(
                                        $this->current_nodes['consultation']['medical_history'][$system_name] ?? [],
                                        $conditioned_nodes_hash_map[$modified_cc_id]
                                    );
                                }


                                // We remove nodes that are excluded by CC
                                if (isset($conditioned_nodes_hash_map[$modified_cc_id])) {
                                    $this->current_nodes['consultation']['medical_history'][$system_name] = array_diff(
                                        $this->current_nodes['consultation']['medical_history'][$system_name] ?? [],
                                        $conditioned_nodes_hash_map[$modified_cc_id]
                                    );
                                }
                            }
                        }
                    }
                } else {

                    //The complaint category has been changed to yes
                    foreach ($this->current_nodes['consultation']['medical_history'] as $system_name => $system_data) {
                        foreach ($current_nodes_per_step as $system_name => $system_data) {
                            foreach ($system_data as $cc_id => $nodes) {

                                // We add the nodes linked to that newly chosen cc
                                if ($modified_cc_id === $cc_id) {
                                    if ($this->algorithm_type === 'dynamic') {
                                        $this->current_nodes['consultation']['medical_history'][$system_name] = array_unique(
                                            [
                                                ...$this->current_nodes['consultation']['medical_history'][$system_name],
                                                ...$system_data[$modified_cc_id],
                                            ]
                                        );
                                    } else {
                                        $this->current_nodes[$modified_cc_id] = $system_data[$modified_cc_id];
                                    }
                                }
                                // We add nodes that were exculded by old no answer
                            }
                        }
                    }
                }
            }
        }
    }

    #[On('nodeToSave')]
    public function saveNode($key, $node_id, $value, $answer_id, $old_answer_id, $cached_data = [])
    {
        Log::info('start--------------');
        Log::info($node_id);
        $cached_data = empty($cached_data) ? Cache::get($this->cache_key) : $cached_data;
        $formula_hash_map = $cached_data['formula_hash_map'];
        $drugs_hash_map = $cached_data['drugs_hash_map'];
        $managements_hash_map = $cached_data['managements_hash_map'];
        $nodes_to_update = $cached_data['nodes_to_update'];
        $dependency_map = $cached_data['dependency_map'];
        $no_condition_nodes = $cached_data['no_condition_nodes'];
        $formula_hash_map = $cached_data['formula_hash_map'];
        $final_diagnoses = $cached_data['final_diagnoses'];
        $qs_hash_map = $cached_data['qs_hash_map'];
        $df_hash_map = $cached_data['df_hash_map'];

        Arr::set($this->current_nodes, $key, $value);
        $this->mc_nodes[$node_id] = $value;

        if (array_key_exists($node_id, $this->nodes_to_save)) {

            $this->nodes_to_save[$node_id]['value'] = $value;

            if (array_key_exists($node_id, $formula_hash_map)) {
                $pretty_answer = $this->handleFormula($cached_data, $node_id);
                Arr::set($this->current_nodes, $key, $pretty_answer);
            }

            //This function will save in nodes_to_save the answer
            //We do not display this pretty answer for now
            $old_answer_id = $this->nodes_to_save[$node_id]['answer_id'] ?? null;
            $pretty_answer_not_used = $this->handleAnswers($cached_data, $node_id, $this->nodes_to_save[$node_id]['value']);
        }

        // Modification behavior
        if ($old_answer_id && $answer_id !== $old_answer_id) {
            // Remove linked diag and management
            if (isset($df_hash_map[$old_answer_id])) {
                foreach ($df_hash_map[$old_answer_id] as $df) {
                    if (array_key_exists($df, $this->df_to_display)) {
                        if (isset($final_diagnoses[$df]['managements'])) {
                            unset($this->all_managements_to_display[key($final_diagnoses[$df]['managements'])]);
                        }
                        unset($this->df_to_display[$df]);
                    }
                }
            }

            $old_values = [$old_answer_id];
            foreach ($this->diagnoses_per_cc as $cc_id => $dd_per_cc) {
                if (array_key_exists($cc_id, array_filter($this->chosen_complaint_categories))) {
                    foreach ($dd_per_cc as $dd_id => $label) {
                        // Remove every linked nodes to old answers
                        if (isset($qs_hash_map[$cc_id][$dd_id][$old_answer_id])) {
                            $old_values = [
                                ...$old_values,
                                ...$qs_hash_map[$cc_id][$dd_id][$old_answer_id]
                            ];
                        }
                        foreach ($old_values as $old_value) {
                            if (isset($dependency_map[$dd_id]) && array_key_exists($old_value, $dependency_map[$dd_id])) {
                                foreach ($dependency_map[$dd_id][$old_value] as $node_id_to_unset) {
                                    $medical_history_nodes = $this->current_nodes['consultation']['medical_history'] ?? $this->current_nodes['consultation'] ?? [];
                                    $node_exists_in_other_system = false;
                                    foreach ($medical_history_nodes as $system_name => $nodes_per_system) {
                                        if (isset($medical_history_nodes[$system_name][$node_id_to_unset])) {
                                            // Remove every df and managements dependency of linked nodes
                                            Log::info($old_answer_id);
                                            Log::info($answer_id);
                                            Log::info($node_id);
                                            Log::info($key);
                                            Log::info($value);
                                            if (array_key_exists($medical_history_nodes[$system_name][$node_id_to_unset], $df_hash_map[$dd_id])) {
                                                foreach ($df_hash_map[$dd_id][$medical_history_nodes[$system_name][$node_id_to_unset]] as $df) {
                                                    if (array_key_exists($df, $this->df_to_display)) {
                                                        if (isset($final_diagnoses[$df]['managements'])) {
                                                            unset($this->all_managements_to_display[key($final_diagnoses[$df]['managements'])]);
                                                        }
                                                        unset($this->df_to_display[$df]);
                                                    }
                                                }
                                            }
                                            if ($this->algorithm_type !== 'prevention') {
                                                $to_unset = true;
                                                foreach (array_keys(array_filter($this->chosen_complaint_categories)) as $chosen_cc_id) {
                                                    if (isset($no_condition_nodes[$chosen_cc_id][$node_id_to_unset]) || isset($no_condition_nodes[$node_id_to_unset])) {
                                                        $to_unset = false;
                                                    }
                                                }
                                                if ($to_unset) {
                                                    if (isset($this->current_nodes['consultation']['medical_history'][$system_name][$node_id_to_unset])) {
                                                        unset($this->current_nodes['consultation']['medical_history'][$system_name][$node_id_to_unset]);
                                                    }
                                                    if (isset($this->current_nodes['consultation']['physical_exam'][$system_name][$node_id_to_unset])) {
                                                        unset($this->current_nodes['consultation']['physical_exam'][$system_name][$node_id_to_unset]);
                                                    }
                                                    if (array_key_exists($node_id_to_unset, $this->nodes_to_save)) {
                                                        if ($node_id_to_unset === 8447) dump('here');
                                                        $this->nodes_to_save[$node_id_to_unset] = [
                                                            'value' => '',
                                                            'answer_id' => '',
                                                            'label' => '',
                                                        ];
                                                    }
                                                }
                                            }
                                            if ($this->algorithm_type === 'training') {
                                                if (!isset($no_condition_nodes[$node_id_to_unset])) {
                                                    unset($this->current_nodes['consultation'][$system_name][$node_id_to_unset]);
                                                }
                                            }
                                            if ($system_name !== $cc_id) {
                                                $node_exists_in_other_system = true;
                                            }
                                        }
                                    }
                                    //don't unset node is also in other tree
                                    if ($this->algorithm_type === 'prevention' && !$node_exists_in_other_system && !isset($no_condition_nodes[$node_id_to_unset])) {
                                        unset($this->current_nodes['consultation'][$cc_id][$node_id_to_unset]);
                                        if (array_key_exists($node_id_to_unset, $this->nodes_to_save)) {
                                            if ($node_id_to_unset === 8447) dump('here');
                                            $this->nodes_to_save[$node_id_to_unset] = [
                                                'value' => '',
                                                'answer_id' => '',
                                                'label' => '',
                                            ];
                                        }
                                    }
                                }
                            }
                        }
                    }
                }

                // If it's empty it means there is no recommendation for that age range after an update so we clear
                if (empty($dd_per_cc)) {
                    if (isset($this->current_nodes['consultation'][$cc_id])) {
                        unset($this->current_nodes['consultation'][$cc_id]);
                    }
                }
            }
        }

        $this->displayNextNode($cached_data, $node_id, $this->nodes_to_save[$node_id]['answer_id'] ?? $answer_id, $old_answer_id);
    }

    #[On('nodeUpdated')]
    public function displayNextNode($cached_data, $node_id, $value, $old_value)
    {
        $dependency_map = $cached_data['dependency_map'];
        $answers_hash_map = $cached_data['answers_hash_map'];
        $no_condition_nodes = $cached_data['no_condition_nodes'];
        $reference_hash_map = $cached_data['reference_hash_map'];
        $formula_hash_map = $cached_data['formula_hash_map'];
        $final_diagnoses = $cached_data['final_diagnoses'];
        $gender_question_id = $cached_data['gender_question_id'];
        $female_gender_answer_id = $cached_data['female_gender_answer_id'];
        $qs_hash_map = $cached_data['qs_hash_map'];
        $df_hash_map = $cached_data['df_hash_map'];
        $health_cares = $cached_data['health_cares'];
        $managements_hash_map = $cached_data['managements_hash_map'];
        $drugs_hash_map = $cached_data['drugs_hash_map'];
        $nodes_to_update = $cached_data['nodes_to_update'];

        // If answer will set a drug, we add it to the drugs to display
        // if (array_key_exists($value, $drugs_hash_map)) {
        //     foreach ($drugs_hash_map[$value] as $drug_id) {
        //         if (!array_key_exists($drug_id, $this->drugs_to_display)) {
        //             $this->drugs_to_display[$drug_id] = false;
        //         }
        //     }
        // }

        // // If answer will set a drug, we add it to the drugs to display
        // if (array_key_exists($this->nodes_to_save[$node_id]['answer_id'], $drugs_hash_map)) {
        //     foreach ($drugs_hash_map[$this->nodes_to_save[$node_id]['answer_id']] as $drug_id) {
        //         if (!array_key_exists($drug_id, $this->drugs_to_display)) {
        //             $this->drugs_to_display[$drug_id] = false;
        //         }
        //     }
        // }

        // // If answer will set a management, we add it to the managements to display
        // if (isset($this->all_managements_to_display)) {
        //     if (array_key_exists($this->nodes_to_save[$node_id]['answer_id'], $managements_hash_map)) {
        //         $this->all_managements_to_display = [
        //             ...$this->all_managements_to_display,
        //             ...$managements_hash_map[$this->nodes_to_save[$node_id]['answer_id']]
        //         ];
        //     }
        // }

        if (array_key_exists($node_id, $this->nodes_to_save)) {
            // If node is linked to some bc, we calculate them directly
            //But only if the bcs is already displayed
            if (array_key_exists($node_id, $nodes_to_update)) {
                foreach ($nodes_to_update[$node_id] as $node_to_update_id) {

                    $answer_before = $this->nodes_to_save[$node_to_update_id]['answer_id'];
                    $pretty_answer = $this->handleFormula($cached_data, $node_to_update_id);
                    if ($this->current_step === 'registration') {
                        //todo Fix here as it's not displaying in all cases
                        // if (array_key_exists($node_to_update_id, $this->current_nodes['registration'])) {
                        $this->current_nodes['registration'][$node_to_update_id] = $pretty_answer;
                        // }
                    }
                    if ($this->current_step === 'first_look_assessment') {
                        if (array_key_exists($node_to_update_id, $this->current_nodes['first_look_assessment']['basic_measurements_nodes_id']) || $this->algorithm_type === 'dynamic') {
                            $this->current_nodes['first_look_assessment']['basic_measurements_nodes_id'][$node_to_update_id] = $pretty_answer;
                        }
                    }
                    if ($this->current_step === 'consultation') {
                        if ($this->algorithm_type === 'dynamic') {
                            if (
                                !array_key_exists($node_to_update_id, $this->current_nodes['registration'])
                                && !array_key_exists($node_to_update_id, $this->current_nodes['first_look_assessment']['basic_measurements_nodes_id'])
                            ) {
                                $this->current_nodes['consultation']['medical_history']['others'][$node_to_update_id] = $pretty_answer;
                            }

                            if (array_key_exists($node_to_update_id, $this->current_nodes['consultation']['medical_history'][$this->current_cc])) {
                                $this->current_nodes['consultation']['medical_history'][$this->current_cc][$node_to_update_id] = $pretty_answer;
                            }
                        } else {
                            if (array_key_exists($node_to_update_id, $this->current_nodes['consultation'][$this->current_cc])) {
                                $this->current_nodes['consultation'][$this->current_cc][$node_to_update_id] = $pretty_answer;
                            }
                        }
                    }

                    // If answer will set a drug, we add it to the drugs to display
                    if (array_key_exists($this->nodes_to_save[$node_to_update_id]['answer_id'], $drugs_hash_map)) {
                        foreach ($drugs_hash_map[$this->nodes_to_save[$node_to_update_id]['answer_id']] as $drug_id) {
                            if (!array_key_exists($drug_id, $this->drugs_to_display)) {
                                $this->drugs_to_display[$drug_id] = false;
                            }
                        }
                    }

                    // If answer will set a management, we add it to the managements to display
                    if (isset($this->all_managements_to_display)) {
                        if (array_key_exists($this->nodes_to_save[$node_to_update_id]['answer_id'], $managements_hash_map)) {
                            $this->all_managements_to_display = [
                                ...$this->all_managements_to_display,
                                ...$managements_hash_map[$this->nodes_to_save[$node_to_update_id]['answer_id']]
                            ];
                        }
                    }
                    // Get the next nodes from that calculated bc and display it
                    // $this->displayNextNode($cached_data, $node_to_update_id, $this->nodes_to_save[$node_to_update_id]['answer_id'] ?? $answer_id, $answer_before);
                }
            }
        }

        //Reference table management
        if (isset($reference_hash_map[$node_id])) {
            foreach ($reference_hash_map[$node_id] as $ref_node_id) {
                $full_nodes = $cached_data['full_nodes'];

                $nodes['current'] = $full_nodes[$ref_node_id];
                // Get X and Y
                $reference_table_x_id = $nodes['current']['reference_table_x_id'];
                $reference_table_y_id = $nodes['current']['reference_table_y_id'];
                if (
                    $this->nodes_to_save[$reference_table_x_id]['value']
                    && $this->nodes_to_save[$reference_table_y_id]['value']
                ) {
                    $nodes['x'] = $full_nodes[$reference_table_x_id];
                    $nodes['y'] = $full_nodes[$reference_table_y_id];
                    $nodes['x']['value'] = $this->nodes_to_save[$reference_table_x_id]['value'];
                    $nodes['y']['value'] = $this->nodes_to_save[$reference_table_y_id]['value'];

                    // Get Z
                    if ($nodes['current']['reference_table_z_id'] !== null) {
                        $reference_table_z_id = $nodes['current']['reference_table_z_id'];
                        $nodes['z'] = $full_nodes[$reference_table_z_id];
                        $nodes['z']['value'] = $this->nodes_to_save[$reference_table_z_id]['value'];
                    }

                    $gender = $this->current_nodes['registration'][$gender_question_id] === $female_gender_answer_id ? 'female' : 'male';
                    $reference = $this->referenceCalculator->calculateReference($ref_node_id, $nodes, $gender, $this->cache_key);
                    if (!is_null($reference)) {
                        $old_answer = $this->nodes_to_save[$ref_node_id]['answer_id'];
                        $this->handleAnswers($cached_data, $ref_node_id, $reference);
                        // $this->displayNextNode($cached_data, $ref_node_id, $this->nodes_to_save[$ref_node_id]['answer_id'], $old_answer);
                    }
                }
            }
        }

        if ($value) {
            $next_nodes_per_cc = $this->getNextNodesId($cached_data, $value);
            dump($next_nodes_per_cc);
        } else {
            dump($value);
        }

        //if next node is background calc -> calc and directly show next <3
        if (isset($next_nodes_per_cc)) {
            foreach ($next_nodes_per_cc as $cc_id => $nodes_per_cc) {
                foreach ($nodes_per_cc as $next_node_dd_id => $next_nodes_id) {
                    foreach ($next_nodes_id as $node) {



                        //Reference table management
                        if (isset($reference_hash_map[$node])) {
                            foreach ($reference_hash_map[$node] as $ref_node_id) {
                                $full_nodes = $cached_data['full_nodes'];

                                $nodes['current'] = $full_nodes[$ref_node_id];
                                // Get X and Y
                                $reference_table_x_id = $nodes['current']['reference_table_x_id'];
                                $reference_table_y_id = $nodes['current']['reference_table_y_id'];
                                if (
                                    $this->nodes_to_save[$reference_table_x_id]['value']
                                    && $this->nodes_to_save[$reference_table_y_id]['value']
                                ) {
                                    $nodes['x'] = $full_nodes[$reference_table_x_id];
                                    $nodes['y'] = $full_nodes[$reference_table_y_id];
                                    $nodes['x']['value'] = $this->nodes_to_save[$reference_table_x_id]['value'];
                                    $nodes['y']['value'] = $this->nodes_to_save[$reference_table_y_id]['value'];

                                    // Get Z
                                    if ($nodes['current']['reference_table_z_id'] !== null) {
                                        $reference_table_z_id = $nodes['current']['reference_table_z_id'];
                                        $nodes['z'] = $full_nodes[$reference_table_z_id];
                                        $nodes['z']['value'] = $this->nodes_to_save[$reference_table_z_id]['value'];
                                    }

                                    $gender = $this->current_nodes['registration'][$gender_question_id] === $female_gender_answer_id ? 'female' : 'male';
                                    $reference = $this->referenceCalculator->calculateReference($ref_node_id, $nodes, $gender, $this->cache_key);
                                    if (!is_null($reference)) {
                                        $this->handleAnswers($cached_data, $ref_node_id, $reference);
                                        $this->displayNextNode($cached_data, $ref_node_id, $this->nodes_to_save[$ref_node_id]['answer_id'], null);
                                    }
                                }
                            }
                        }

                        if (array_key_exists($node, $formula_hash_map)) {
                            $old_answer_id = $this->nodes_to_save[$node]['answer_id'] ?? null;
                            $pretty_answer = $this->handleFormula($cached_data, $node);

                            if ($this->current_step === 'registration' && $node !== $node_id) {
                                $this->displayNextNode($cached_data, $node, $this->nodes_to_save[$node]['answer_id'], $old_answer_id);
                            }

                            if ($this->current_step === 'first_look_assessment') {
                                $this->current_nodes['first_look_assessment']['basic_measurements_nodes_id'][$node] = $pretty_answer;
                            }

                            if ($this->current_step === 'consultation') {
                                if ($this->algorithm_type === 'dynamic') {
                                    if (
                                        !array_key_exists($node, $this->current_nodes['registration'])
                                        && !array_key_exists($node, $this->current_nodes['first_look_assessment']['basic_measurements_nodes_id'])
                                    ) {
                                        $this->current_nodes['consultation']['medical_history']['others'][$node] = $pretty_answer;
                                    }
                                } else {
                                    $found = false;
                                    foreach ($this->current_nodes[$this->current_step] as $current_nodes_per_cc) {
                                        if (array_key_exists($node, $current_nodes_per_cc)) {
                                            $found = true;
                                        }
                                    }
                                    if (!$found) {
                                        if (!array_key_exists($cc_id, $this->current_nodes['consultation'])) {
                                            $this->current_nodes['consultation'][$cc_id] = [];
                                        }
                                        $this->current_nodes['consultation'][$cc_id] = $this->appendOrInsertAtPos($this->current_nodes['consultation'][$cc_id], [$node => $pretty_answer], $node_id);
                                    }
                                }
                                $this->displayNextNode($cached_data, $node, $this->nodes_to_save[$node]['answer_id'], null);
                            }
                        }
                        //In case the sex is asked again in some tree
                        //fail because answer is not saved yet
                        if (array_key_exists($node, $this->current_nodes['registration']) && $node !== $node_id) {
                            $this->displayNextNode($cached_data, $node, $this->current_nodes['registration'][$node], null, $value);
                        }
                    }
                }
                if (array_key_exists($cc_id, array_filter($this->chosen_complaint_categories))) {
                    $this->calculateCompletionPercentage($cached_data, $cc_id);
                }
            }
        }

        foreach ($this->diagnoses_per_cc as $cc_id => $dd_per_cc) {
            if (array_key_exists($cc_id, array_filter($this->chosen_complaint_categories))) {
                foreach ($dd_per_cc as $dd_id => $label) {
                    //if next node is DF, add it to df_to_display <3
                    if (isset($df_hash_map[$dd_id][$value])) {
                        $respect_cut_off = true;
                        foreach ($df_hash_map[$dd_id][$value] as $df) {
                            foreach ($final_diagnoses[$df]['conditions'] as $condition) {

                                if (array_key_exists($condition['node_id'], $no_condition_nodes)) continue;

                                //Respect cut off
                                if (array_key_exists('cut_off_start', $condition)) {
                                    if (isset($this->age_in_days)) {
                                        if ($this->age_in_days < $condition['cut_off_start'] || $this->age_in_days >= $condition['cut_off_end']) {
                                            $respect_cut_off = false;
                                        }
                                    }
                                }
                            }
                            if ($respect_cut_off) {
                                if ((isset($answers_hash_map[$cc_id][$dd_id][$value]) && in_array($node_id, $answers_hash_map[$cc_id][$dd_id][$value]))
                                    || !in_array($node_id, Arr::flatten($dependency_map[$dd_id]))
                                ) {
                                    if (!array_key_exists($final_diagnoses[$df]['id'], $this->df_to_display)) {
                                        $drugs = [];
                                        foreach ($final_diagnoses[$df]['drugs'] as $drug_id => $drug) {

                                            $conditions = $final_diagnoses[$df]['drugs'][$drug['id']]['conditions'];

                                            if (array_key_exists($drug_id, $drugs)) continue;
                                            if (empty($conditions)) {
                                                $drugs[$drug_id] = $drug_id;
                                            } else {
                                                if (array_key_exists($drug['id'], $this->drugs_to_display)) {
                                                    $drugs[$drug_id] = $drug_id;
                                                }
                                            }
                                        }

                                        $this->df_to_display[$df] = $drugs;
                                        Log::info("$dd_id $node_id added $df");
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
                    }
                }
            }
        }


        // Reorder DF and managements upon level_of_urgency
        if (isset($this->df_to_display)) {
            uksort($this->df_to_display, function ($a, $b) use ($final_diagnoses) {
                return $final_diagnoses[$b]['level_of_urgency'] <=> $final_diagnoses[$a]['level_of_urgency'];
            });
        }

        if (isset($this->managements_to_display)) {
            uksort($this->managements_to_display, function ($a, $b) use ($health_cares) {
                return $health_cares[$b]['level_of_urgency'] <=> $health_cares[$a]['level_of_urgency'];
            });
        }

        if (isset($next_nodes_per_cc)) {
            foreach ($next_nodes_per_cc as $cc_id => $nodes_per_cc) {
                foreach ($nodes_per_cc as $next_node_dd_id => $next_nodes_id) {
                    foreach ($next_nodes_id as $node) {
                        $this->setNextNode($cached_data, $node, $node_id, $cc_id, $next_node_dd_id, $value);
                    }
                    if ($this->algorithm_type === 'prevention') {
                        if (array_key_exists($cc_id, array_filter($this->chosen_complaint_categories))) {
                            $this->calculateCompletionPercentage($cached_data, $cc_id);
                        }
                    }
                }
            }
        }
    }

    public function handleFormula($cached_data, $node_id)
    {
        $formula_hash_map = $cached_data['formula_hash_map'];
        $full_nodes = $cached_data['full_nodes'];
        $cut_off_hash_map = $cached_data['cut_off_hash_map'];
        $general_cc_id = $cached_data['general_cc_id'];
        $yi_general_cc_id = $cached_data['yi_general_cc_id'];

        $formula = $formula_hash_map[$node_id];

        //In this situation we just have to get the days/months/years
        if ($formula === "ToDay" || $formula === "ToMonth" || $formula === "ToYear") {
            $today = new DateTime('today');
            $dob = new DateTime($this->current_nodes['registration']['birth_date']);

            $interval = $today->diff($dob);

            if ($formula === "ToDay") {
                $days = $interval->format('%a');
                //My eyes are burning....
                //But no other way as the Age in days node id is not saved anywhere
                if ($full_nodes[$node_id]['label']['en'] === 'Age in days') {
                    $this->age_in_days = $days;
                    foreach ($cut_off_hash_map['dd'] as $complaint_category_id => $dds) {
                        foreach ($dds as $dd_id => $dd) {
                            if ($dd['cut_off_start'] <= $days && $dd['cut_off_end'] > $days) {
                                $this->diagnoses_per_cc[$complaint_category_id][$dd_id] = $dd['label'];
                            } else {
                                if (isset($this->diagnoses_per_cc[$complaint_category_id][$dd_id])) {
                                    unset($this->diagnoses_per_cc[$complaint_category_id][$dd_id]);
                                }
                            }
                        }
                    }

                    if ($days <= 59) {
                        if ($this->algorithm_type === 'dynamic') {
                            $this->age_key = 'neonat';
                            $this->current_cc = $yi_general_cc_id;
                            $this->chosen_complaint_categories[$yi_general_cc_id] = true;
                            if (array_key_exists($general_cc_id, $this->chosen_complaint_categories)) {
                                unset($this->chosen_complaint_categories[$general_cc_id]);
                            }
                            // Set the first_look_assessment nodes that are depending on the age key
                            $this->current_nodes['first_look_assessment']['complaint_categories_nodes_id'] =
                                $cached_data['nodes_per_step']['first_look_assessment']['complaint_categories_nodes_id'][$this->age_key];
                        }
                    } else {
                        $this->age_key = 'older';
                        if ($this->algorithm_type === 'dynamic') {
                            $this->current_cc = $general_cc_id;
                            $this->chosen_complaint_categories[$general_cc_id] = true;
                            if (array_key_exists($yi_general_cc_id, $this->chosen_complaint_categories)) {
                                unset($this->chosen_complaint_categories[$yi_general_cc_id]);
                            }
                            // Set the first_look_assessment nodes that are depending on the age key
                            $this->current_nodes['first_look_assessment']['complaint_categories_nodes_id'] =
                                $cached_data['nodes_per_step']['first_look_assessment']['complaint_categories_nodes_id'][$this->age_key];
                        }
                    }
                }
                $result = $days;
            } elseif ($formula === "ToMonth") {
                $result = $interval->m + ($interval->y * 12);
            } elseif ($formula === "ToYear") {
                $result = $interval->y;
            }
        } else {
            //In this situation we have a formula to calculate
            $formula = preg_replace_callback('/\[(\d+)\]/', function ($matches) {
                return $this->nodes_to_save[$matches[1]]['value'];
            }, $formula);

            try {
                $result = (new ExpressionLanguage())->evaluate($formula);
            } catch (DivisionByZeroError $e) {
                return null;
            } catch (Exception $e) {
                return null;
            }
        }

        return $this->handleAnswers($cached_data, $node_id, $result);
    }

    public function handleAnswers($cached_data, $node_id, $value)
    {
        $answers = $cached_data['full_nodes'][$node_id]['answers'];
        foreach ($answers as $answer) {
            $result = floatval($value);
            $answer_value = $answer['value'];
            $answer_values = explode(',', $answer_value);
            $minValue = floatval($answer_values[0]);
            $maxValue = floatval($answer_values[1] ?? $minValue);

            $answer_found = match ($answer['operator']) {
                'more_or_equal' => $result >= $minValue ? true : false,
                'less' => $result < $minValue ? true : false,
                'between' => ($result >= $minValue && $result < $maxValue) ? true : false,
                default => null,
            };

            if ($answer_found) {
                $label = "{$answer['id']} : {$answer['label']['en']} ($result is {$answer['operator']} {$answer['value']})";
                $this->nodes_to_save[$node_id]['answer_id'] = $answer['id'];
                $this->nodes_to_save[$node_id]['label'] = $label;
                $this->nodes_to_save[$node_id]['value'] = $value;
                $this->medical_case['nodes'][$node_id]['answer'] = $answer['id'];
                $this->medical_case['nodes'][$node_id]['value'] = $value;
                $this->medical_case['nodes'][$node_id]['label'] = $label;
                return $answer['id'];
            }
        }

        return null;
    }

    public function updateLinkedNodesOfDob($birth_date)
    {
        $cached_data = Cache::get($this->cache_key);
        $nodes = $cached_data['full_nodes'];
        $birth_date_formulas = $cached_data['birth_date_formulas'];
        $cut_off_hash_map = $cached_data['cut_off_hash_map'];
        $dependency_map = $cached_data['dependency_map'];

        foreach ($birth_date_formulas as $node_id) {
            $value = null;

            // If the user reset the date of birth
            if ($birth_date !== null) {
                $value = $this->handleFormula($cached_data, $node_id);
            }

            $new_value = $this->handleNumeric($this->medical_case['nodes'][$node_id], $nodes[$node_id], $value);
            $new_nodes[$node_id] = array_replace($this->medical_case['nodes'][$node_id], $new_value);
            $this->medical_case['nodes'][$node_id] = $new_nodes[$node_id];
            dump($new_nodes);
            // Update related questions based on the new value
            $new_nodes = $this->updateRelatedQuestion(
                $cached_data,
                $node_id,
                $new_nodes,
                $this->medical_case['nodes']
            );
        }

        $this->medical_case['nodes'] = array_merge($this->medical_case['nodes'], $new_nodes);
        return;
    }

    public function setNextNode($cached_data, $next_node_id, $node_id, $cc_id, $dd_id, $answer_id)
    {
        $full_nodes = $cached_data['full_nodes'];
        $nodes_per_step = $cached_data['nodes_per_step'];
        $dependency_map = $cached_data['dependency_map'];
        $answers_hash_map = $cached_data['answers_hash_map'];
        $qs_hash_map = $cached_data['qs_hash_map'];
        $cut_off_hash_map = $cached_data['cut_off_hash_map'];
        $no_condition_nodes = $cached_data['no_condition_nodes'];
        $current_nodes_per_step = $nodes_per_step['consultation']['medical_history']['general'] ?? $nodes_per_step['consultation']['medical_history'];

        if (isset($full_nodes[$next_node_id])) {
            $node = $full_nodes[$next_node_id];

            if ($this->algorithm_type === 'dynamic') {
                $needed_answer_to_show_that_node = [];
                if (isset($answers_hash_map[$cc_id][$dd_id])) {
                    foreach ($answers_hash_map[$cc_id][$dd_id] as $answers_hash_map_answer_id => $answers_hash_map_nodes) {
                        //double check !isset($no_condition_nodes[$cc_id][$node_id])
                        // if (!isset($no_condition_nodes[$cc_id][$node_id]) && in_array($node_id, $answers_hash_map_nodes)) {
                        if (in_array($node_id, $answers_hash_map_nodes)) {
                            if (isset($cut_off_hash_map['nodes'][$cc_id][$node_id][$answers_hash_map_answer_id])) {
                                $condition = $cut_off_hash_map['nodes'][$cc_id][$node_id][$answers_hash_map_answer_id];
                                if (isset($this->age_in_days)) {
                                    if ($condition['cut_off_start'] <= $this->age_in_days && $condition['cut_off_end'] > $this->age_in_days) {
                                        $needed_answer_to_show_that_node[] = $answers_hash_map_answer_id;
                                    }
                                }
                            } else {
                                $needed_answer_to_show_that_node[] = $answers_hash_map_answer_id;
                            }
                        }
                    }
                }

                $current_nodes = new RecursiveIteratorIterator(
                    new RecursiveArrayIterator($this->current_nodes)
                );

                foreach ($current_nodes as $key => $value) {
                    $all_answers[$key] = $value;
                }

                $all_answers = [
                    ...$all_answers,
                    ...array_column($this->nodes_to_save, 'answer_id'),
                ];

                if ($next_node_id === 7855) dump($dd_id);
                if ($next_node_id === 7855) dump($node_id);
                if ($next_node_id === 7855) dump($answer_id);
                if ($next_node_id === 7855) dump($needed_answer_to_show_that_node);
                if ($next_node_id === 7855) dump(array_intersect($needed_answer_to_show_that_node, $all_answers));
                if ($next_node_id === 8447) dump($dd_id);
                if ($next_node_id === 8447) dump($node_id);
                if ($next_node_id === 8447) dump($answer_id);
                if ($next_node_id === 8447) dump($needed_answer_to_show_that_node);
                if ($next_node_id === 8447) dump(array_intersect($needed_answer_to_show_that_node, $all_answers));
                if ($next_node_id === 7855) dump(isset($answers_hash_map[$cc_id][$dd_id][$answer_id]));
                if ($next_node_id === 7855) dump(in_array($node_id, $answers_hash_map[$cc_id][$dd_id][$answer_id]));
                if ($next_node_id === 7855) dump(!in_array($node_id, Arr::flatten($dependency_map[$dd_id])));
                if ($next_node_id === 7855) dump(Arr::flatten($dependency_map[$dd_id]));
                if (
                    array_intersect($needed_answer_to_show_that_node, $all_answers)
                    || (isset($answers_hash_map[$cc_id][$dd_id][$answer_id]) && in_array($node_id, $answers_hash_map[$cc_id][$dd_id][$answer_id]))
                    || (!isset($qs_hash_map[$cc_id][$dd_id][$answer_id]) && !in_array($node_id, Arr::flatten($dependency_map[$dd_id])))
                ) {

                    $system = isset($node['system']) ? $node['system'] : 'others';
                    switch ($node['category']) {
                        case 'physical_exam':
                            if (!isset($this->current_nodes['consultation']['physical_exam'][$system][$next_node_id])) {
                                $this->current_nodes['consultation']['physical_exam'][$system][$next_node_id] = '';
                            }
                            break;
                        case 'symptom':
                        case 'predefined_syndrome':
                        case 'background_calculation':
                        case 'observed_physical_sign':
                        case 'exposure':
                        case 'chronic_condition':
                            if (!isset($this->current_nodes['consultation']['medical_history'][$system][$next_node_id])) {
                                $this->current_nodes['consultation']['medical_history'][$system][$next_node_id] = '';
                            }
                            break;
                        case 'assessment_test':
                            if (!isset($this->current_nodes['tests'][$next_node_id])) {
                                $this->current_nodes['tests'][$next_node_id] = '';
                            }
                            break;
                        case 'treatment_question':
                            if (!isset($this->current_nodes['diagnoses']['treatment_questions'][$next_node_id])) {
                                $this->current_nodes['diagnoses']['treatment_questions'][$next_node_id] = false;
                            }
                            break;
                    }

                    if (isset($this->current_nodes['consultation']['physical_exam'])) {
                        $this->algorithmService->sortSystemsAndNodes(
                            $this->current_nodes['consultation']['physical_exam'],
                            'physical_exam',
                            $this->cache_key
                        );
                    }
                    if (isset($this->current_nodes['consultation']['medical_history'])) {
                        $this->algorithmService->sortSystemsAndNodes(
                            $this->current_nodes['consultation']['medical_history'],
                            'medical_history',
                            $this->cache_key
                        );
                    }
                }
            } else {
                if (!isset($this->current_nodes['consultation'][$cc_id][$next_node_id])) {
                    $value = '';
                    $diverging_tree = false;

                    if (!isset($this->current_nodes['registration'][$next_node_id])) {

                        foreach ($this->current_nodes['consultation'] ?? [] as $consultation_cc_id => $nodes_per_cc) {
                            // If node is already shown on another tree
                            if (array_key_exists($next_node_id, $nodes_per_cc)) {
                                $next_node_value = $nodes_per_cc[$next_node_id];
                                foreach ($this->diagnoses_per_cc[$cc_id] as $dd_id_to_check => $label) {
                                    if (isset($answers_hash_map[$cc_id][$dd_id_to_check][$next_node_value])) {
                                        foreach ($answers_hash_map[$cc_id][$dd_id_to_check][$next_node_value] as $node_to_display) {
                                            $this->current_nodes['consultation'][$cc_id] = $this->appendOrInsertAtPos(
                                                $this->current_nodes['consultation'][$cc_id],
                                                [$node_to_display => ''],
                                                $node_id
                                            );
                                            if (array_key_exists($node_to_display, $nodes_per_cc)) {
                                                $children_value = $nodes_per_cc[$node_to_display];
                                                if (isset($answers_hash_map[$cc_id][$dd_id_to_check][$children_value])) {
                                                    foreach ($answers_hash_map[$cc_id][$dd_id_to_check][$children_value] as $children_node_to_display) {
                                                        $this->current_nodes['consultation'][$cc_id] = $this->appendOrInsertAtPos(
                                                            $this->current_nodes['consultation'][$cc_id],
                                                            [$children_node_to_display => ''],
                                                            $node_id
                                                        );
                                                    }
                                                }
                                                unset($this->current_nodes['consultation'][$cc_id][$node_to_display]);
                                            }
                                        }
                                    }
                                }
                                unset($this->current_nodes['consultation'][$cc_id][$next_node_id]);
                                $this->displayNextNode($cached_data, $next_node_id, $value, null);
                                $diverging_tree = true;
                                // if (isset($this->current_nodes['registration'][$node_id])) {
                                //     $this->algorithmService->sortNodesPerCC($this->current_nodes['consultation'], $this->cache_key);
                                // }
                            }

                            //For the current node that is displayed in other tree
                            if ($consultation_cc_id !== $this->current_cc && array_key_exists($node_id, $nodes_per_cc)) {
                                unset($this->current_nodes['consultation'][$consultation_cc_id][$node_id]);
                            }
                        }
                        if (!isset($this->current_nodes['consultation'][$cc_id])) {
                            $this->current_nodes['consultation'][$cc_id] = [];
                        }

                        if (!$diverging_tree && $this->current_cc === $cc_id && $this->current_step !== 'registration' && $this->current_step !== 'first_look_assessment') {
                            $this->current_nodes['consultation'][$cc_id] = $this->appendOrInsertAtPos(
                                $this->current_nodes['consultation'][$cc_id],
                                [$next_node_id => $value],
                                $node_id
                            );
                            // if (isset($this->current_nodes['registration'][$node_id])) {
                            //     $this->algorithmService->sortNodesPerCC($this->current_nodes['consultation'], $this->cache_key);
                            // }
                        }

                        if (!$diverging_tree && $this->current_cc !== $cc_id && $this->current_step !== 'registration' && $this->current_step !== 'first_look_assessment') {
                            foreach ($this->current_nodes['consultation'][$cc_id] as $node => $answer) {
                                if (isset($answers_hash_map[$cc_id][$dd_id][$answer])) {

                                    // If it's a children, we get the parent and check if it suppose to be displayed
                                    $node_to_check = $dependency_map[$dd_id][$answer][0] ?? 0;
                                    if (in_array($node_id, $answers_hash_map[$cc_id][$dd_id][$answer]) || in_array($node_to_check, $answers_hash_map[$cc_id][$dd_id][$answer])) {
                                        $this->current_nodes['consultation'][$cc_id] = $this->appendOrInsertAtPos(
                                            $this->current_nodes['consultation'][$cc_id],
                                            [$next_node_id => ''],
                                            $node_id
                                        );
                                    }
                                }
                            }

                            if (array_key_exists($node_id, $current_nodes_per_step[$cc_id])) {
                                $this->current_nodes['consultation'][$cc_id] = $this->appendOrInsertAtPos(
                                    $this->current_nodes['consultation'][$cc_id],
                                    [$next_node_id => ''],
                                    $node_id
                                );
                            }

                            // if (isset($this->current_nodes['registration'][$node_id])) {
                            //     $this->algorithmService->sortNodesPerCC($this->current_nodes['consultation'], $this->cache_key);
                            // }
                        }
                    }

                    if ($this->current_step === 'registration' ||  $this->current_step === 'first_look_assessment') {

                        $needed_answer_to_show_that_node = [];
                        if (isset($answers_hash_map[$cc_id][$dd_id])) {
                            foreach ($answers_hash_map[$cc_id][$dd_id] as $answers_hash_map_answer_id => $answers_hash_map_nodes) {
                                if (in_array($node_id, $answers_hash_map_nodes)) {
                                    if (isset($cut_off_hash_map['nodes'][$cc_id][$node_id][$answers_hash_map_answer_id])) {
                                        $condition = $cut_off_hash_map['nodes'][$cc_id][$node_id][$answers_hash_map_answer_id];
                                        if (isset($this->age_in_days)) {
                                            if ($condition['cut_off_start'] <= $this->age_in_days && $condition['cut_off_end'] > $this->age_in_days) {
                                                $needed_answer_to_show_that_node[] = $answers_hash_map_answer_id;
                                            }
                                        }
                                    } else {
                                        $needed_answer_to_show_that_node[] = $answers_hash_map_answer_id;
                                    }
                                }
                            }
                        }

                        $all_answers = [
                            ...$this->current_nodes['registration'],
                            ...array_column($this->nodes_to_save, 'answer_id'),
                            ...$this->current_nodes['consultation'][$cc_id] ?? []
                        ];

                        if (
                            array_intersect($needed_answer_to_show_that_node, $all_answers)
                            || (isset($answers_hash_map[$cc_id][$dd_id][$answer_id]) && in_array($node_id, $answers_hash_map[$cc_id][$dd_id][$answer_id]))
                            || !in_array($node_id, Arr::flatten($dependency_map[$dd_id]))
                        ) {

                            if (isset($cut_off_hash_map['nodes'][$cc_id][$next_node_id][$answer_id])) {
                                $condition = $cut_off_hash_map['nodes'][$cc_id][$next_node_id][$answer_id];
                                if (isset($this->age_in_days)) {
                                    if ($condition['cut_off_start'] <= $this->age_in_days && $condition['cut_off_end'] > $this->age_in_days) {
                                        if (!isset($this->current_nodes['registration'][$next_node_id])) {
                                            $this->current_nodes['consultation'][$cc_id] = $this->appendOrInsertAtPos(
                                                $this->current_nodes['consultation'][$cc_id],
                                                [$next_node_id => ''],
                                                $node_id
                                            );
                                        }
                                    }
                                }
                            } else {
                                if (!isset($this->current_nodes['registration'][$next_node_id])) {
                                    $this->current_nodes['consultation'][$cc_id] = $this->appendOrInsertAtPos(
                                        $this->current_nodes['consultation'][$cc_id],
                                        [$next_node_id => ''],
                                        $node_id
                                    );
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    public function getNextNodesId($cached_data, $answer_id)
    {
        $answers_hash_map = $cached_data['answers_hash_map'];
        $cut_off_hash_map = $cached_data['cut_off_hash_map'];
        $next_nodes = [];
        $answers_id = [$answer_id];

        foreach ($this->diagnoses_per_cc as $cc_id => $dds) {
            foreach ($dds as $dd_id => $label) {
                foreach ($answers_id as $answer_id) {
                    if (isset($answers_hash_map[$cc_id][$dd_id][$answer_id])) {
                        foreach ($answers_hash_map[$cc_id][$dd_id][$answer_id] as $node) {
                            //Respect cut off
                            if (isset($cut_off_hash_map['nodes'][$cc_id][$node][$answer_id])) {
                                foreach ($cut_off_hash_map['nodes'][$cc_id][$node] as $cut_off_answer_id => $condition) {
                                    if (intval($answer_id) === $cut_off_answer_id || in_array($cut_off_answer_id, array_column($this->nodes_to_save, 'answer_id'))) {
                                        if ($this->current_step === 'registration' && $this->algorithm_type === 'prevention' || array_key_exists($cc_id, $this->chosen_complaint_categories)) {
                                            if (isset($this->age_in_days)) {
                                                if ($condition['cut_off_start'] <= $this->age_in_days && $condition['cut_off_end'] > $this->age_in_days) {
                                                    $next_nodes[$cc_id][$dd_id][] = $node;
                                                }
                                            }
                                        }
                                    }
                                }
                            } else {
                                if ($this->current_step === 'registration' && $this->algorithm_type === 'prevention' || array_key_exists($cc_id, $this->chosen_complaint_categories)) {
                                    $next_nodes[$cc_id][$dd_id][] = $node;
                                }
                            }
                        }
                    }
                }
            }
        }

        return $next_nodes ?? null;
    }

    public function get_valid_diagnoses()
    {
        $nodes = $this->algorithm['item']['nodes'];
        $mc_nodes = $this->medical_case['nodes'];
        $diagnoses = $this->algorithm['item']['diagnoses'];

        // Filter diagnoses based on the complaint category and cut-off dates
        return array_filter($diagnoses, function ($diagnosis) use ($nodes, $mc_nodes) {
            return (
                isset($mc_nodes[$diagnosis['complaint_category']]) &&
                $mc_nodes[$diagnosis['complaint_category']]['answer'] === $this->getYesAnswer($nodes[$diagnosis['complaint_category']])['id'] &&
                $this->respectsCutOff($diagnosis)
            );
        });
    }
    private function getYesAnswer($node)
    {
        return collect($node['answers'])->where('reference', 1)->first()['id'];
    }

    private function getNoAnswer($node)
    {
        return collect($node['answers'])->where('reference', 2)->first()['id'];
    }

    public function respectsCutOff($condition)
    {
        $cut_off_start = isset($condition['cut_off_start']) ? $condition['cut_off_start'] : null;
        $cut_off_end = isset($condition['cut_off_end']) ? $condition['cut_off_end'] : null;

        if (is_null($cut_off_start) && is_null($cut_off_end)) {
            return true;
        }

        if (is_null($cut_off_start)) {
            return $cut_off_end > $this->age_in_days;
        }

        if (is_null($cut_off_end)) {
            return $cut_off_start <= $this->age_in_days;
        }
        Log::info($this->age_in_days);
        Log::info($cut_off_start <= $this->age_in_days && $cut_off_end > $this->age_in_days);
        return $cut_off_start <= $this->age_in_days && $cut_off_end > $this->age_in_days;
    }

    public function getTopConditions($instances, $is_final_diagnosis = false)
    {
        return array_filter($instances, function ($instance) use ($is_final_diagnosis) {
            return empty($instance['conditions']) && ($is_final_diagnosis || is_null($instance['final_diagnosis_id']));
        });
    }

    public function handleChildren($children, $source, &$questions_to_display, $instances, $categories, $diagram_id, $diagram_type, $system, &$current_systems, $system_order)
    {
        $nodes = $this->algorithm['item']['nodes'];

        foreach ($children as $instance) {
            if (
                (!$this->excludedByCc($instance['id']) && empty($instance['conditions'])) ||
                $this->calculateCondition($instance, $source)
            ) {
                if ($nodes[$instance['id']]['type'] === config('medal.node_types.questions_sequence')) {
                    $top_conditions = $this->getTopConditions($nodes[$instance['id']]['instances']);
                    $this->handleChildren(
                        $top_conditions,
                        $instance['id'],
                        $questions_to_display,
                        $nodes[$instance['id']]['instances'],
                        $categories,
                        $instance['id'],
                        config('medal.node_types.questions_sequence'),
                        $system,
                        $current_systems,
                        $system_order
                    );
                } else {
                    if ($system) {
                        $this->addQuestionToSystem(
                            $instance['id'],
                            $questions_to_display,
                            $categories,
                            $current_systems,
                            $system_order
                        );
                    } else {
                        $this->addQuestion($instance['id'], $questions_to_display, $categories);
                    }
                }

                $healthcare_categories = [
                    config('medal.categories.drug'),
                    config('medal.categories.management'),
                ];

                $children_instances = array_filter($instance['children'], function ($child_id) use ($nodes, $diagram_type, $diagram_id, $healthcare_categories) {
                    return (
                        ($nodes[$child_id]['type'] !== config('medal.node_types.final_diagnosis') && $diagram_type === config('medal.node_types.diagnosis')) ||
                        ($diagram_type === config('medal.node_types.questions_sequence') && $child_id !== $diagram_id && !in_array($nodes[$child_id]['category'], $healthcare_categories))
                    );
                });

                if (!empty($children_instances)) {
                    $this->handleChildren(
                        $children_instances,
                        $instance['id'],
                        $questions_to_display,
                        $instances,
                        $categories,
                        $diagram_id,
                        $diagram_type,
                        $system,
                        $current_systems,
                        $system_order
                    );
                }
            }
        }
    }

    private function addQuestionToSystem($question_id, &$questions_to_display, $categories, &$current_systems, $system_order)
    {
        $last_system_updated = $this->get_last_system_updated();

        if (in_array($this->nodes[$question_id]['category'], $categories)) {
            $old_system_index = array_search($last_system_updated['system'], array_column($system_order, 'title'));
            $current_question_system_index = array_search($this->nodes[$question_id]['system'], array_column($system_order, 'title'));

            $visible_nodes = array_merge(...array_values($current_systems));
            $is_already_displayed = in_array($question_id, $visible_nodes);
            $is_nodes_already_answered = array_reduce($visible_nodes, function ($carry, $node_id) {
                return $carry || $this->mc_nodes[$node_id]['answer'] !== null || $this->mc_nodes[$node_id]['value'] !== '';
            }, false);

            if (
                !$is_already_displayed &&
                $is_nodes_already_answered &&
                $current_question_system_index < $old_system_index ||
                in_array($question_id, $current_systems['follow_up_questions'] ?? [])
            ) {
                $questions_to_display['follow_up_questions'][] = $question_id;
            } else {
                $questions_to_display[$this->nodes[$question_id]['system']][] = $question_id;
            }
        }
    }

    private function addQuestion($question_id, &$questions_to_display, $categories)
    {
        if (in_array($this->nodes[$question_id]['category'], $categories)) {
            $questions_to_display[] = $question_id;
        }
    }

    public function excludedByCc($question_id)
    {
        $mc_nodes = $this->medical_case['nodes'];
        $nodes = $this->algorithm['item']['nodes'];

        if (
            $nodes[$question_id]['type'] === config('medal.node_types.final_diagnosis') ||
            $nodes[$question_id]['category'] === config('medal.categories.drug') ||
            $nodes[$question_id]['category'] === config('medal.categories.management')
        ) {
            return false;
        }

        return array_reduce($nodes[$question_id]['conditioned_by_cc'], function ($carry, $cc_id) use ($mc_nodes, $nodes) {
            return $carry || $mc_nodes[$cc_id]['answer'] === $this->getNoAnswer($nodes[$cc_id]);
        }, false);
    }

    public function calculateCondition($instance, $source_id = null, $new_nodes = [])
    {
        $mc_nodes = array_replace($this->medical_case['nodes'], $new_nodes);

        if ($this->excludedByCc($instance['id'])) {
            return false;
        }

        if (empty($instance['conditions'])) {
            return true;
        }

        $conditions = array_filter($instance['conditions'], function ($condition) use ($source_id) {
            if (is_null($source_id)) {
                return true;
            }
            return $condition['node_id'] === $source_id;
        });

        return array_reduce($conditions, function ($carry, $condition) use ($mc_nodes) {
            return $carry || (
                $mc_nodes[$condition['node_id']]['answer'] === $condition['answer_id'] &&
                $this->respectsCutOff($condition)
            );
        }, false);
    }

    public function getQsValue($nodes, $qs_id, $new_mc_nodes)
    {
        if ($nodes[$qs_id]['category'] === config('medal.categories.scored')) {
            return $this->scoredCalculateCondition($qs_id, $new_mc_nodes);
        } else {
            $conditions_values = array_map(function ($condition) use ($qs_id, $new_mc_nodes, $nodes) {
                Log::info('n');
                Log::info(json_encode($new_mc_nodes[$condition['node_id']]));
                if ($this->respectsCutOff($condition)) {
                    Log::info('y');
                    return $this->qsInstanceValue(
                        $nodes[$qs_id]['instances'][$condition['node_id']],
                        $new_mc_nodes,
                        $nodes[$qs_id]['instances'],
                        $qs_id
                    );
                } else {
                    return false;
                }
            }, $nodes[$qs_id]['conditions']);
            Log::info(json_encode($conditions_values));
            Log::info(json_encode($this->reduceConditions($conditions_values)));
            return $this->reduceConditions($conditions_values);
        }
    }

    public function qsInstanceValue($instance, $new_mc_nodes, $instances, $qs_id)
    {
        $mc_node = $new_mc_nodes[$instance['id']];
        $instance_condition = $this->calculateCondition($instance, null, $new_mc_nodes);

        if ($instance_condition && $mc_node['answer'] === null) {
            return null;
        }

        if ($instance_condition) {
            if (empty($instance['conditions'])) {
                return true;
            }

            $parents = array_filter($instance['conditions'], function ($condition) use ($new_mc_nodes) {
                return $new_mc_nodes[$condition['node_id']]['answer'] === $condition['answer_id'] &&
                    $this->respectsCutOff($condition);
            });

            if (empty($parents)) {
                return false;
            } else {
                $parents_condition = array_map(function ($parent) use ($instances, $new_mc_nodes, $qs_id) {
                    return $this->qsInstanceValue($instances[$parent['node_id']], $new_mc_nodes, $instances, $qs_id);
                }, $parents);

                return $this->reduceConditions($parents_condition);
            }
        } else {
            return false;
        }
    }

    public function scoredCalculateCondition($qs_id, $new_mc_nodes)
    {
        $algorithm = $this->algorithm;

        $qs = $algorithm['nodes'][$qs_id];

        // If this is a top parent node
        if (empty($qs['conditions'])) {
            return true;
        }

        $score_true = 0;
        $score_false = 0;
        $score_null = 0;
        $score_total_possible = 0;

        foreach ($qs['conditions'] as $condition) {
            $answer_id = $condition['answer_id'];
            $node_id = $condition['node_id'];
            $returned_boolean = false;

            if (isset($new_mc_nodes[$node_id]['answer'])) {
                $returned_boolean = intval($new_mc_nodes[$node_id]['answer']) === intval($answer_id);
            }

            $score_total_possible += $condition['score'];

            switch ($returned_boolean) {
                case true:
                    $score_true += $condition['score'];
                    break;
                case false:
                    $score_false += $condition['score'];
                    break;
                case null:
                    $score_null += $condition['score'];
                    break;
            }
        }

        // If score true so this QS is true
        if ($score_true >= $qs['min_score']) {
            return true;
        }
        // If there are more false conditions than the minimum necessary, return false
        if ($score_total_possible - $score_false <= $qs['min_score']) {
            return false;
        }
        // If there are more null conditions than the minimum necessary, return null
        if ($score_total_possible - $score_null >= $qs['min_score']) {
            return null;
        }

        return null; // Just in case no conditions are met
    }

    public function reduceConditions($conditions_values)
    {
        return array_reduce($conditions_values, function ($carry, $value) {
            return $carry || $value;
        }, false);
    }

    public function diagramConditionsValues($node_id, $instance, $mc_nodes)
    {
        $nodes = $this->algorithm['item']['nodes'];

        return array_map(function ($condition) use ($instance, $mc_nodes) {
            if ($mc_nodes[$condition['node_id']]['answer'] === null) {
                return null;
            } else {
                return (
                    $mc_nodes[$condition['node_id']]['answer'] === $condition['answer_id'] &&
                    $this->respectsCutOff($condition)
                );
            }
        }, array_filter($nodes[$node_id]['conditions'], function ($condition) use ($instance) {
            return $condition['node_id'] === $instance['id'];
        }));
    }

    public function calculateConditionInverse($conditions, $mc_nodes)
    {
        $instances = $this->algorithm['item']['diagram']['instances'];

        if (empty($conditions)) {
            return true;
        }

        return array_reduce($conditions, function ($carry, $condition) use ($mc_nodes, $instances) {
            $condition_value = (
                $mc_nodes[$condition['node_id']]['answer'] === $condition['answer_id'] &&
                $this->respectsCutOff($condition)
            );

            if ($condition_value) {
                return $carry || $this->calculateConditionInverse(
                    $instances[$condition['node_id']]['conditions'] ?? [],
                    $mc_nodes
                );
            } else {
                return false;
            }
        }, false);
    }

    public function updateNode($node_id, $value)
    {
        $cached_data = Cache::get($this->cache_key);
        $nodes = $this->algorithm['item']['nodes']; // Assuming $this->algorithm contains algorithm data
        $new_medical_case = $this->medical_case; // Assuming $this->medical_case contains medical case data
        $node = $nodes[$node_id];
        $mc_node = $new_medical_case['nodes'][$node_id];
        $new_nodes = [];

        // Set the new value to the current node
        $new_values = $this->set_node_value($mc_node, $node, $value);

        $new_nodes[$node_id] = array_merge($mc_node, $new_values);

        // Update question sequence
        $new_nodes = $this->updateQuestionSequence(
            $node['id'],
            $new_nodes,
            $new_medical_case['nodes']
        );

        // Update related questions
        $new_nodes = $this->updateRelatedQuestion(
            $cached_data,
            $node['id'],
            $new_nodes,
            $new_medical_case['nodes']
        );

        // Assuming the method returns the updated nodes and activities
        return [
            'nodes' => $new_nodes,
        ];
    }

    public function updateQuestionSequence($node_id, $new_nodes, $mc_nodes)
    {
        // Retrieve the nodes from the algorithm state
        $nodes = $this->algorithm['item']['nodes'];

        // List of QS we need to update
        $qs_to_update = $nodes[$node_id]['qs'];

        while (count($qs_to_update) > 0) {
            $qs_id = $qs_to_update[0];
            $qs_boolean_value = $this->getQsValue($nodes, $qs_id, array_replace($mc_nodes, $new_nodes));
            Log::info("qs_id $qs_id");
            // dd($qs_boolean_value);
            Log::info("qs_boolean_value $qs_boolean_value");
            // If the QS has a value
            if (!is_null($qs_boolean_value)) {
                $qs_value = $qs_boolean_value ? $this->getYesAnswer($nodes[$qs_id]) : $this->getNoAnswer($nodes[$qs_id]);
                Log::info("qs_value $qs_value");
                // dd($qs_boolean_value);
                $new_qs_values = $this->handleAnswerId($nodes[$qs_id], $qs_value);

                // Set the QS value in the store
                $new_nodes[$qs_id] = array_replace($mc_nodes[$qs_id], $new_qs_values);
            } else {
                $new_nodes[$qs_id] = array_replace($mc_nodes[$qs_id], ['value' => null, 'answer' => null]);
            }

            // Add the related QS to the QS processing list
            $new_qs_list = array_filter($nodes[$qs_id]['qs'], function ($child_id) {
                return !$this->excludedByCC($child_id);
            });

            $qs_to_update = array_replace($qs_to_update, $new_qs_list);

            // uniq to avoid processing the same QS multiple times
            $qs_to_update = array_unique(array_slice($qs_to_update, 1));
        }

        return $new_nodes;
    }

    public function calculateReference($cached_data, $node_id)
    {
        $gender_question_id = $cached_data['gender_question_id'];
        $female_gender_answer_id = $cached_data['female_gender_answer_id'];
        //Reference table management
        if (isset($reference_hash_map[$node_id])) {
            foreach ($reference_hash_map[$node_id] as $ref_node_id) {
                $full_nodes = $cached_data['full_nodes'];

                $nodes['current'] = $full_nodes[$ref_node_id];
                // Get X and Y
                $reference_table_x_id = $nodes['current']['reference_table_x_id'];
                $reference_table_y_id = $nodes['current']['reference_table_y_id'];
                if (
                    $this->nodes_to_save[$reference_table_x_id]['value']
                    && $this->nodes_to_save[$reference_table_y_id]['value']
                ) {
                    $nodes['x'] = $full_nodes[$reference_table_x_id];
                    $nodes['y'] = $full_nodes[$reference_table_y_id];
                    $nodes['x']['value'] = $this->nodes_to_save[$reference_table_x_id]['value'];
                    $nodes['y']['value'] = $this->nodes_to_save[$reference_table_y_id]['value'];

                    // Get Z
                    if ($nodes['current']['reference_table_z_id'] !== null) {
                        $reference_table_z_id = $nodes['current']['reference_table_z_id'];
                        $nodes['z'] = $full_nodes[$reference_table_z_id];
                        $nodes['z']['value'] = $this->nodes_to_save[$reference_table_z_id]['value'];
                    }

                    $gender = $this->current_nodes['registration'][$gender_question_id] === $female_gender_answer_id ? 'female' : 'male';
                    $reference = $this->referenceCalculator->calculateReference($ref_node_id, $nodes, $gender, $this->cache_key);
                    if (!is_null($reference)) {
                        return $this->handleAnswers($cached_data, $ref_node_id, $reference);
                    }
                }
            }
        }
    }

    public function updateRelatedQuestion($cached_data, $node_id, $new_nodes, $mc_nodes)
    {
        // Retrieve the nodes from the algorithm state
        $nodes = $this->algorithm['item']['nodes'];

        // List of formulas/reference tables we need to update
        $questions_to_update = $nodes[$node_id]['referenced_in'];
        Log::info("node_id $node_id");
        while (count($questions_to_update) > 0) {
            $question_id = $questions_to_update[0];
            $node = $nodes[$question_id];
            $mc_node = $mc_nodes[$question_id];

            $value = null;
            Log::info($question_id);
            // Determine if the node is a formula or reference table

            if ($node['display_format'] === config('medal.display_format.formula')) {
                $value = $this->handleFormula($cached_data, $question_id);
            } else {
                $value = $this->calculateReference($cached_data, $question_id);
            }

            // // Perform validation
            // $validation = $this->questionValidationService($mc_node, $node, $value);

            $new_question_values = $this->handleNumeric($mc_node, $node, $value);
            // Set the question value in the store
            $new_nodes[$question_id] = array_replace($mc_nodes[$question_id], $new_question_values);
            // Add the related questions to the update list
            $questions_to_update = array_merge($questions_to_update, $node['referenced_in']);

            // Remove duplicates and process the next question in the list
            $new_nodes = $this->updateQuestionSequence($question_id, $new_nodes, $mc_nodes);
            $questions_to_update = array_unique(array_slice($questions_to_update, 1));
        }

        return $new_nodes;
    }

    public function manageRegistrationStep()
    {
        $registration_order = $this->algorithm['item']['config']['full_order']['registration_step'];
        $instances = $this->algorithm['item']['diagram']['instances'];
        $mc_nodes = $this->medical_case['nodes'];

        return array_fill_keys(array_filter($registration_order, function ($node_id) use ($instances, $mc_nodes) {
            return $this->calculateConditionInverse($instances[$node_id]['conditions'] ?? [], $mc_nodes);
        }), "");
    }

    public function manageFirstLookAssessmentStep()
    {
        $this->manageVitalSign();
        $this->manageComplaintCategory();
        $this->manageBasicMeasurement();
    }

    public function manageVitalSign()
    {
        $first_look_assessment_order = $this->algorithm['item']['config']['full_order']['first_look_assessment_step'];
        $instances = $this->algorithm['item']['diagram']['instances'];
        $mc_nodes = $this->medical_case['nodes'];

        // Filter the first look assessment order based on the condition inverse calculation
        return array_filter($first_look_assessment_order, function ($node_id) use ($instances, $mc_nodes) {
            return $this->calculateConditionInverse($instances[$node_id]['conditions'] ?? [], $mc_nodes);
        });
    }

    public function manageComplaintCategory()
    {
        $config = $this->algorithm['item']['config'];
        $full_order = $config['full_order'];
        $basic_questions = $config['basic_questions'];

        // Get CC order (older and neonate) and general cc id for neonat and general
        $older_cc = $full_order['complaint_categories_step']['older'];
        $neonat_cc = $full_order['complaint_categories_step']['neonat'];
        $older_general_id = $basic_questions['general_cc_id'];
        $neonat_general_id = $basic_questions['yi_general_cc_id'];
        $instances = $this->algorithm['item']['diagram']['instances'];
        $mc_nodes = $this->medical_case['nodes'];

        if ($this->age_in_days <= 59) {
            return array_filter($neonat_cc, function ($node_id) use ($neonat_general_id, $instances, $mc_nodes) {
                return $node_id !== $neonat_general_id &&
                    $this->calculateConditionInverse($instances[$node_id]['conditions'] ?? [], $mc_nodes);
            });
        } else {
            return array_filter($older_cc, function ($node_id) use ($older_general_id, $instances, $mc_nodes) {
                return $node_id !== $older_general_id &&
                    $this->calculateConditionInverse($instances[$node_id]['conditions'] ?? [], $mc_nodes);
            });
        }
    }

    public function manageBasicMeasurement()
    {
        $order = $this->algorithm['item']['config']['full_order']['basic_measurements_step'];
        $nodes = $this->algorithm['item']['nodes'];
        $mc_nodes = $this->medical_case['nodes'];
        $instances = $this->algorithm['item']['diagram']['instances'];

        return array_filter($order, function ($question_id) use ($nodes, $mc_nodes, $instances) {
            if (!empty($nodes[$question_id]['conditioned_by_cc'])) {
                // Check if any of the complaint categories (cc) are true; if so, exclude the question
                $exclude_question = array_reduce($nodes[$question_id]['conditioned_by_cc'], function ($carry, $cc_id) use ($mc_nodes, $nodes) {
                    return $carry || ($mc_nodes[$cc_id]['answer'] === $this->getYesAnswer($nodes[$cc_id])['id']);
                }, false);

                if ($exclude_question) {
                    return $this->calculateConditionInverse($instances[$question_id]['conditions'] ?? [], $mc_nodes);
                }
            }

            return $this->calculateConditionInverse($instances[$question_id]['conditions'] ?? [], $mc_nodes);
        });
    }

    public function manageconsultation_step()
    {
        $this->manageMedicalHistory();
        $this->managePhysicalExam();

        $this->current_cc = key(array_filter($this->chosen_complaint_categories));

        if ($this->algorithm_type === 'prevention' && empty($this->completion_per_substep)) {
            foreach (array_keys(array_filter($this->chosen_complaint_categories)) as $cc) {
                $this->completion_per_substep[$cc] = [
                    'start' => 0,
                    'end' => 0,
                ];
            }
        }
    }

    public function manageMedicalHistory()
    {
        $medical_history_categories = [
            config('medal.categories.symptom'),
            config('medal.categories.exposure'),
            config('medal.categories.chronic_condition'),
            config('medal.categories.vaccine'),
            config('medal.categories.observed_physical_sign'),
        ];

        $question_per_systems = [];
        $medical_history_step = $this->algorithm['item']['config']['full_order']['medical_history_step'];

        // Get valid diagnoses
        $valid_diagnoses = $this->get_valid_diagnoses();

        $current_systems = $this->questions_per_system['item']['medical_history'];

        foreach ($valid_diagnoses as $diagnosis) {
            $top_conditions = $this->getTopConditions($diagnosis['instances']);

            $this->handleChildren(
                $top_conditions,
                null,
                $question_per_systems,
                $diagnosis['instances'],
                $medical_history_categories,
                $diagnosis['id'],
                config('medal.node_types.diagnosis'),
                true,
                $current_systems,
                $medical_history_step
            );
        }

        $updated_systems = [];
        foreach ($medical_history_step as $system) {
            $new_questions = array_filter($system['data'], function ($question_id) use ($question_per_systems, $system) {
                return in_array($question_id, $question_per_systems[$system['title']] ?? []);
            });

            if (!$this->is_equal($current_systems[$system['title']], $new_questions)) {
                $updated_systems[$system['title']] = $new_questions;
            }
        }

        $updated_systems['follow_up_questions'] = array_unique($question_per_systems['follow_up_questions'] ?? []);

        $this->questions_per_system['item']['medical_history'] = array_merge(
            $this->questions_per_system['item']['medical_history'],
            $updated_systems
        );
    }

    public function managePhysicalExam()
    {
        $question_per_systems = [];
        $physical_exam_categories = [
            config('medal.categories.physical_exam'),
            config('medal.categories.vital_sign_anthropometric')
        ];
        $physical_exam_step = $this->algorithm['item']['config']['full_order']['physical_exam_step'];
        $current_systems = $this->questions_per_system['item']['physical_exam'];
        $instances = $this->algorithm['item']['diagram']['instances'];

        // Get valid diagnoses
        $valid_diagnoses = $this->get_valid_diagnoses();

        // Handle children nodes based on valid diagnoses
        foreach ($valid_diagnoses as $diagnosis) {
            $top_conditions = $this->getTopConditions($diagnosis['instances']);

            $this->handleChildren(
                $top_conditions,
                null,
                $question_per_systems,
                $diagnosis['instances'],
                $physical_exam_categories,
                $diagnosis['id'],
                config('medal.node_types.diagnosis'),
                true,
                $current_systems,
                $physical_exam_step
            );
        }

        $mc_nodes = $this->medical_case['nodes'];
        $updated_systems = [];

        foreach ($physical_exam_step as $system) {
            $new_questions = array_filter($system['data'], function ($question_id) use ($question_per_systems, $system, $instances, $mc_nodes) {
                return in_array($question_id, $question_per_systems[$system['title']] ?? []) &&
                    $this->calculateConditionInverse($instances[$question_id]['conditions'] ?? [], $mc_nodes);
            });

            if (!$this->is_equal($current_systems[$system['title']] ?? [], $new_questions)) {
                $updated_systems[$system['title']] = $new_questions;
            }
        }

        $updated_systems['follow_up_questions'] = array_unique($question_per_systems['follow_up_questions'] ?? []);

        return $updated_systems;
    }

    public function manageTestStep()
    {

        $assessment_categories = [config('medal.categories.assessment')];
        $assessment_step = $this->algorithm['item']['config']['full_order']['test_step'];

        $valid_diagnoses = $this->get_valid_diagnoses();
        $questions_to_display = [];

        foreach ($valid_diagnoses as $diagnosis) {
            $top_conditions = $this->getTopConditions($diagnosis['instances']);

            $this->handleChildren(
                $top_conditions,
                null,
                $questions_to_display,
                $diagnosis['instances'],
                $assessment_categories,
                $diagnosis['id'],
                config('medal.node_types.diagnosis'),
                false,
                [],
                []
            );
        }

        return array_filter($assessment_step, function ($question) use ($questions_to_display) {
            return in_array($question, $questions_to_display);
        });
    }

    public function manageDiagnosesStep()
    {
        $this->manageFinalDiagnose();
        $this->manageTreatment();
        $this->manageDrugs();
        $this->manageSummary();
        $this->manageReferral();
    }

    public function manageTreatment()
    {
        $final_diagnostics = $this->medical_case['diagnosis']['agreed'];
        $final_diagnostics_additional = $this->medical_case['diagnosis']['additional'];
        $nodes = $this->algorithm['item']['nodes'];
        $diagnoses = $this->algorithm['item']['diagnoses'];
        $questions_to_display = [];

        $all_final_diagnostics = array_merge($final_diagnostics, $final_diagnostics_additional);

        foreach ($all_final_diagnostics as $agreed_final_diagnostic) {
            $final_diagnostic = $nodes[$agreed_final_diagnostic['id']];

            $instances = $diagnoses[$final_diagnostic['diagnosis_id']]['final_diagnoses'][$final_diagnostic['id']]['instances'];
            $top_conditions = $this->getTopConditions($instances, true);

            $this->handleChildren(
                $top_conditions,
                null,
                $questions_to_display,
                $instances,
                [config('medal.categories.treatment_question')],
                $final_diagnostic['diagnosis_id'],
                config('medal.node_types.diagnosis'),
                false,
                [],
                []
            );
        }

        return array_unique($questions_to_display);
    }

    public function manageReferral()
    {
        $referral_order = $this->algorithm['item']['config']['full_order']['referral_step'];
        $instances = $this->algorithm['item']['diagram']['instances'];
        $mc_nodes = $this->medical_case['nodes'];

        return array_filter($referral_order, function ($node_id) use ($instances, $mc_nodes) {
            return $this->calculateConditionInverse($instances[$node_id]['conditions'] ?? [], $mc_nodes);
        });
    }

    public function manageFinalDiagnose()
    {
        $mc_diagnosis = $this->deepCopy($this->medical_case['diagnosis']);
        $medical_case = $this->medical_case;
        $diagnoses = $this->algorithm['item']['diagnoses'];
        $nodes = $this->algorithm['item']['nodes'];
        $valid_diagnoses = $this->get_valid_diagnoses();
        $valid_diagnoses_ids = array_map(fn($diagnosis) => $diagnosis['id'], $valid_diagnoses);

        // Exclude diagnoses based on cut off and complaint category exclusion
        $mc_diagnosis['excluded'] = array_merge(...array_map(function ($diagnosis) use ($valid_diagnoses_ids) {
            if (!in_array($diagnosis['id'], $valid_diagnoses_ids)) {
                return array_map(fn($final_diagnosis) => $final_diagnosis['id'], $diagnosis['final_diagnoses']);
            }
            return [];
        }, $diagnoses));

        // Find all the included diagnoses
        $mc_diagnosis['proposed'] = array_merge(...array_map(function ($diagnosis) {
            return $this->findProposedFinalDiagnoses($diagnosis);
        }, $valid_diagnoses));

        $mc_diagnosis['proposed'] = array_filter($mc_diagnosis['proposed'], function ($final_diagnosis_id) use (&$mc_diagnosis, $nodes) {
            return !$nodes[$final_diagnosis_id]['excluding_final_diagnoses'] || !array_reduce($nodes[$final_diagnosis_id]['excluding_final_diagnoses'], function ($carry, $excluding_final_diagnosis_id) use (&$mc_diagnosis, $final_diagnosis_id) {
                if (in_array($excluding_final_diagnosis_id, array_map(fn($a) => $a['id'], $mc_diagnosis['agreed']))) {
                    $mc_diagnosis['excluded'][] = $final_diagnosis_id;
                }
                return $carry || (in_array($excluding_final_diagnosis_id, $mc_diagnosis['proposed']) && !in_array($excluding_final_diagnosis_id, $mc_diagnosis['refused']));
            }, false);
        });

        // Remove agreed diagnosis if it is no longer proposed
        foreach ($mc_diagnosis['agreed'] as $key => $diagnosis) {
            if (!in_array($diagnosis['id'], $mc_diagnosis['proposed'])) {
                unset($mc_diagnosis['agreed'][$key]);
            }
        }

        $excluded_diagnoses = array_merge(...array_map(function ($diagnosis) {
            return array_filter($this->findExcludedFinalDiagnoses($diagnosis), fn($final_diagnosis) => $final_diagnosis['value'] === false);
        }, $valid_diagnoses));

        $mc_diagnosis['excluded'] = array_merge($mc_diagnosis['excluded'], array_map(fn($final_diagnosis) => $final_diagnosis['id'], $excluded_diagnoses));

        return array_merge($medical_case, ['diagnosis' => $mc_diagnosis]);
    }

    private function deepCopy($array)
    {
        return json_decode(json_encode($array), true);
    }

    public function calculateConditionInverseFinalDiagnosis($conditions, $mc_nodes, $instances)
    {
        if (empty($conditions)) {
            return true;
        }

        return array_reduce($conditions, function ($carry, $condition) use ($mc_nodes, $instances) {
            $condition_value = (
                $mc_nodes[$condition['node_id']]['answer'] === $condition['answer_id'] &&
                $this->respectsCutOff($condition)
            );

            if ($condition_value) {
                return $carry || $this->calculateConditionInverseFinalDiagnosis(
                    $instances[$condition['node_id']]['conditions'] ?? [],
                    $mc_nodes,
                    $instances
                );
            } else {
                return false;
            }
        }, false);
    }

    public function getAvailableHealthcare($final_diagnosis, $key, $exclusion = true)
    {
        $nodes = $this->algorithm['item']['nodes'];
        $instances = array_merge(
            $this->algorithm['item']['diagnoses'][$final_diagnosis['diagnosis_id']]['final_diagnoses'][$final_diagnosis['id']]['instances'],
            $final_diagnosis[$key]
        );

        $questions_to_display = [];

        if ($key === 'drugs') {
            $all_drugs = array_filter($instances, function ($instance) use ($nodes) {
                return $nodes[$instance['id']]['category'] === config('medal.categories.drug');
            });
            $this->handleDrugs($all_drugs, $questions_to_display, $instances, $exclusion);
        } else {
            $all_managements = array_filter($instances, function ($instance) use ($nodes) {

                return $nodes[$instance['id']]['category'] === config('medal.categories.management');
            });
            $this->handleManagements($all_managements, $questions_to_display, $instances);
        }

        return array_unique($questions_to_display);
    }

    public function isHealthcareExcluded($healthcare_id, $agreed_healthcares)
    {
        $nodes = $this->algorithm['item']['nodes'];

        return in_array($healthcare_id, $nodes[$healthcare_id]['excluding_nodes_ids']) &&
            !empty(array_intersect($agreed_healthcares, $nodes[$healthcare_id]['excluding_nodes_ids']));
    }

    public function handleManagements($management_instances, &$questions_to_display, $instances)
    {
        $mc_nodes = $this->medical_case['nodes'];
        $nodes = $this->algorithm['item']['nodes'];
        $agreed_final_diagnoses = $this->medical_case['diagnosis']['agreed'];

        $managements = array_map(function ($agreed_final_diagnosis) use ($nodes) {
            return array_map(function ($management) use ($nodes) {
                return $management['id'];
            }, array_filter($nodes[$agreed_final_diagnosis['id']]['managements'], function ($management) {
                return $this->calculateCondition($management);
            }));
        }, $agreed_final_diagnoses);

        $managements = Arr::flatten($managements);

        foreach ($management_instances as $instance) {
            if ($this->calculateConditionInverseFinalDiagnosis($instance['conditions'], $mc_nodes, $instances)) {

                if ($nodes[$instance['id']]['category'] === config('medal.categories.management')) {
                    if (!$this->isHealthcareExcluded($instance['id'], $managements)) {
                        $questions_to_display[] = $instance['id'];
                    }
                }
            }
        }
    }

    public function handleDrugs($drug_instances, &$questions_to_display, $instances, $exclusion)
    {
        $agreed_final_diagnoses = $this->medical_case['diagnosis']['agreed'];
        $mc_nodes = $this->medical_case['nodes'];

        $agreed_drugs = $exclusion ? array_map(function ($agreed_final_diagnosis) {
            return array_map(function ($drug) {
                return $drug['id'];
            }, $agreed_final_diagnosis['drugs']['agreed']);
        }, $agreed_final_diagnoses) : [];

        $agreed_drugs = Arr::flatten($agreed_drugs);

        foreach ($drug_instances as $instance) {
            if ($this->calculateConditionInverseFinalDiagnosis($instance['conditions'], $mc_nodes, $instances)) {
                if ($exclusion) {
                    if (!$this->isHealthcareExcluded($instance['id'], $agreed_drugs)) {
                        $questions_to_display[] = $instance['id'];
                    }
                } else {
                    $questions_to_display[] = $instance['id'];
                }
            }
        }
    }

    public function drugIsAgreed($drug)
    {
        $diagnoses = $this->medical_case['diagnosis'];
        foreach ($drug['diagnoses'] as $diagnosis) {
            if (isset($diagnoses[$diagnosis['key']][$diagnosis['id']]['drugs']['agreed'][$drug['id']])) {
                return true;
            }
        }
        return false;
    }

    public function drugIsRefused($drug)
    {
        $diagnoses = $this->medical_case['diagnosis'];
        foreach ($drug['diagnoses'] as $diagnosis) {
            if (in_array($drug['id'], $diagnoses[$diagnosis['key']][$diagnosis['id']]['drugs']['refused'])) {
                return true;
            }
        }
        return false;
    }

    public function reworkAndOrderDrugs()
    {
        $nodes = $this->algorithm['item']['nodes'];
        $diagnoses = $this->medical_case['diagnosis'];

        $diagnosis_types = ['agreed', 'additional', 'custom'];
        $drug_types = ['agreed', 'proposed', 'additional', 'custom'];

        $new_drugs = [
            'calculated' => [],
            'additional' => [],
            'custom' => [],
        ];

        foreach ($diagnosis_types as $diagnosis_type) {
            foreach ($diagnoses[$diagnosis_type] as $diagnosis) {
                foreach ($drug_types as $drug_type) {
                    if (isset($diagnosis['drugs'][$drug_type])) {
                        foreach ($diagnosis['drugs'][$drug_type] as $drug) {
                            $drug_id = ($drug_type === 'proposed') ? $drug : $drug['id'];
                            $drug_key = in_array($drug_type, ['proposed', 'agreed']) ? 'calculated' : $drug_type;
                            $diagnosis_label = ($diagnosis_type === 'custom') ? $diagnosis['name'] : $nodes[$diagnosis['id']]['label']['en'];

                            $drug_index = $this->getDrugIndex($new_drugs, $drug_id);

                            if ($drug_index > -1) {
                                $diagnosis_exists = array_search($diagnosis['id'], array_column($new_drugs[$drug_key][$drug_index]['diagnoses'], 'id')) !== false;
                                if (!$diagnosis_exists) {
                                    $new_drugs[$drug_key][$drug_index]['duration'] = $new_drugs[$drug_key][$drug_index]['duration'];
                                    $new_drugs[$drug_key][$drug_index]['diagnoses'][] = [
                                        'id' => $diagnosis['id'],
                                        'key' => $diagnosis_type,
                                        'label' => $diagnosis_label,
                                    ];
                                }
                            } else {
                                $drug_label = ($drug_type === 'custom') ? $drug['name'] : $nodes[$drug_id]['label']['en'];
                                $new_drugs[$drug_key][] = [
                                    'id' => $drug_id,
                                    'key' => $drug_type,
                                    'label' => $drug_label,
                                    'level_of_urgency' => $nodes[$drug_id]['level_of_urgency'] ?? null,
                                    'diagnoses' => [
                                        [
                                            'id' => $diagnosis['id'],
                                            'key' => $diagnosis_type,
                                            'label' => $diagnosis_label,
                                        ],
                                    ],
                                    'duration' => $drug['duration'],
                                    'added_at' => $drug['added_at'] ?? null,
                                    'selected_formulation_id' => $drug['formulation_id'] ?? null,
                                ];
                            }
                        }
                    }
                }
            }
        }

        return $new_drugs;
    }

    private function getDrugIndex($drugs, $drug_id)
    {
        foreach ($drugs as $drug_type) {
            $found_index = array_search($drug_id, array_column($drug_type, 'id'));
            if ($found_index !== false) {
                return $found_index;
            }
        }
        return -1;
    }

    public function findProposedFinalDiagnoses($diagnosis)
    {
        $top_conditions = $this->getTopConditions($diagnosis['instances']);

        return array_unique(
            array_merge(...array_map(function ($instance) use ($diagnosis) {
                return $this->searchFinalDiagnoses($instance, $diagnosis['instances'], $diagnosis['id']);
            }, $top_conditions))
        );
    }

    private function searchFinalDiagnoses($instance, $instances, $source_id = null, $final_diagnoses = [])
    {
        $nodes = $this->algorithm['item']['nodes'];
        $mc_nodes = $this->medical_case['nodes'];

        $instance_condition = $this->calculateCondition($instance, $source_id);

        if ($instance_condition) {
            foreach ($instance['children'] as $child_id) {
                if ($nodes[$child_id]['type'] === config('medal.node_types.final_diagnosis')) {
                    $final_diagnosis_condition = $this->reduceConditions(
                        $this->diagramConditionsValues($child_id, $instance, $mc_nodes)
                    );

                    if ($final_diagnosis_condition) {
                        $final_diagnoses[] = $child_id;
                    }
                } else {
                    $final_diagnoses = $this->searchFinalDiagnoses(
                        $instances[$child_id],
                        $instances,
                        $instance['id'],
                        $final_diagnoses
                    );
                }
            }
        }

        return $final_diagnoses;
    }

    public function getNewDiagnoses($final_diagnoses, $remove_drugs = false)
    {
        $nodes = $this->algorithm['item']['nodes'];
        $new_final_diagnoses = [];
        $new_agreed = [];

        // Regroup all agreed drugs for exclusions
        foreach ($final_diagnoses as $final_diagnosis) {
            foreach ($final_diagnosis['drugs']['agreed'] as $drug) {
                if (array_key_exists($drug['id'], $new_agreed)) {
                    $new_agreed[$drug['id']]['final_diagnoses_id'][] = $final_diagnosis['id'];
                } else {
                    $new_agreed[$drug['id']] = array_merge($drug, [
                        'formulation_id' => $drug['formulation_id'] ?? null,
                        'final_diagnoses_id' => [$final_diagnosis['id']]
                    ]);
                }
            }
        }

        // Find for all final diagnosis the proposed drug
        foreach ($final_diagnoses as $final_diagnosis) {
            $available_drugs = $this->getAvailableHealthcare(
                $nodes[$final_diagnosis['id']],
                'drugs',
                false
            );
            $new_additional = json_decode(json_encode($final_diagnosis['drugs']['additional']), true);

            if ($remove_drugs) {
                $drug_to_remove = array_filter(array_keys($final_diagnosis['drugs']['agreed']), function ($agreed_drug_id) use ($available_drugs, $new_agreed) {
                    return !in_array((int)$agreed_drug_id, $available_drugs) || $this->isHealthcareExcluded(
                        $agreed_drug_id,
                        array_column($new_agreed, 'id')
                    );
                });

                foreach ($drug_to_remove as $drug_id) {
                    unset($new_agreed[$drug_id]);
                }
            }

            $new_proposed = array_filter($available_drugs, function ($drug_id) use ($new_agreed) {
                return !$this->isHealthcareExcluded(
                    $drug_id,
                    array_column($new_agreed, 'id')
                );
            });

            $agreed_with_formulations = [];
            foreach ($new_agreed as $drug) {
                if (in_array($final_diagnosis['id'], $drug['final_diagnoses_id'])) {
                    $drug_formulations = $nodes[$drug['id']]['formulations'];
                    $agreed_with_formulations[$drug['id']] = array_merge($drug, [
                        'formulation_id' => count($drug_formulations) === 1
                            ? $drug_formulations[0]['id']
                            : $final_diagnosis['drugs']['agreed'][$drug['id']]['formulation_id'] ?? null
                    ]);
                }
            }

            $additional_with_formulations = [];
            foreach ($new_additional as $drug) {
                $drug_formulations = $nodes[$drug['id']]['formulations'];
                $additional_with_formulations[$drug['id']] = array_merge($drug, [
                    'formulation_id' => count($drug_formulations) === 1
                        ? $drug_formulations[0]['id']
                        : $final_diagnosis['drugs']['additional'][$drug['id']]['formulation_id'] ?? null
                ]);
            }

            // Management calculations
            $managements = $this->getAvailableHealthcare(
                $nodes[$final_diagnosis['id']],
                'managements'
            );

            $new_final_diagnoses[$final_diagnosis['id']] = array_merge($final_diagnosis, [
                'drugs' => [
                    'proposed' => $new_proposed,
                    'agreed' => $agreed_with_formulations,
                    'additional' => $additional_with_formulations,
                ],
                'managements' => $managements
            ]);
        }

        return $new_final_diagnoses;
    }

    public function findExcludedFinalDiagnoses($diagnosis)
    {
        $top_conditions = $this->getTopConditions($diagnosis['instances']);

        return array_map(function ($final_diagnosis) use ($top_conditions, $diagnosis) {
            return [
                'id' => $final_diagnosis['id'],
                'value' => $this->reduceConditions(array_map(function ($instance) use ($diagnosis, $final_diagnosis) {
                    return $this->searchExcludedFinalDiagnoses(
                        $instance,
                        $diagnosis['instances'],
                        $final_diagnosis
                    );
                }, $top_conditions))
            ];
        }, $diagnosis['final_diagnoses']);
    }

    private function searchExcludedFinalDiagnoses($instance, $instances, $final_diagnosis)
    {
        $nodes = $this->algorithm['item']['nodes'];
        $mc_node = $this->medical_case['nodes'][$instance['id']];

        $instance_condition = $this->calculateCondition($instance);

        if ($instance_condition && $mc_node['answer'] === null) {
            return null;
        }

        if ($instance_condition) {
            return $this->reduceConditions(array_map(function ($child_id) use ($nodes, $instances, $final_diagnosis) {
                if ($nodes[$child_id]['type'] === config('medal.node_types.final_diagnosis')) {
                    return $final_diagnosis['id'] === $child_id && $this->calculateCondition($nodes[$child_id]);
                } else {
                    return $this->searchExcludedFinalDiagnoses(
                        $instances[$child_id],
                        $instances,
                        $final_diagnosis
                    );
                }
            }, $instance['children']));
        } else {
            return false;
        }
    }

    public function roundValue($value, $step = 1.0)
    {
        $inv = 1.0 / $step;
        $result = round($value * $inv) / $inv;
        return (fmod($result, 1) === 0.0) ? (int) $result : $result;
    }

    public function handleNumeric($mc_node, $node, $value)
    {
        $response = ['answer' => null, 'value' => $value];
        $unavailable_answer = collect($node['answers'])->firstWhere('value', 'not_available');

        if (is_null($value)) {
            $response['answer'] = null;
            $response['value'] = '';
        } elseif (
            isset($mc_node['unavailable_value']) ||
            ($unavailable_answer && $unavailable_answer['id'] === $value)
        ) {
            // Unavailable question
            $response['answer'] = (int)$value;
            $response['value'] = $node['answers'][$response['answer']]['value'];
        } else {
            // Normal process
            if (!is_null($value)) {
                $response['answer'] = (int)$value;
            } else {
                $response['answer'] = null;
            }

            if (isset($node['round']) && !is_null($node['round'])) {
                $response['rounded_value'] = $this->roundValue($value, $node['round']);
            }
        }

        return $response;
    }

    public function handleAnswerId($node, $value)
    {
        $answer = null;
        if (!is_null($value)) {
            // Set Number only if this is a number
            if (is_numeric($value)) {
                $answer = (int)$value;
                Log::info(json_encode($node['answers'][$answer]));
                $value = $node['answers'][$answer]['value'];
            } else {
                $answer = collect($node['answers'])->firstWhere('value', $value);
                $answer = $answer ? $answer['id'] : null;
            }
        }
        return ['answer' => $answer, 'value' => $value];
    }

    public function setNodeValue($mc_node, $node, $value)
    {
        $value_formats = config('medal.value_formats');
        switch ($node['value_format']) {
            case $value_formats['int']:
            case $value_formats['float']:
            case $value_formats['date']:
                return $this->handleNumeric($mc_node, $node, $value);
            case $value_formats['bool']:
            case $value_formats['array']:
            case $value_formats['present']:
            case $value_formats['positive']:
                return $this->handleAnswerId($node, $value);
            case $value_formats['string']:
                return ['answer' => null, 'value' => $value];
            default:
                return ['answer' => null, 'value' => null];
        }
    }

    public function generateQuestion($node)
    {
        $answer = $node['answer'] ?? null;
        $value = $node['value'] ?? '';
        $rounded_value = $node['rounded_value'] ?? '';
        $estimable = $node['estimable'] ?? false;
        $estimable_value = $node['estimable_value'] ?? 'measured';
        $validation_message = $node['validation_message'] ?? null;
        $validation_type = $node['validation_type'] ?? null;
        $unavailable_value = $node['unavailable_value'] ?? false;

        $hash = array_merge(
            $this->_generateCommon($node),
            [
                'answer' => $answer,
                'value' => $value,
                'rounded_value' => $rounded_value,
                'validation_message' => $validation_message,
                'validation_type' => $validation_type,
                'unavailable_value' => $unavailable_value
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

    public function generateQuestionsSequence($node)
    {
        $answer = $node['answer'] ?? null;

        return array_merge(
            $this->_generateCommon($node),
            [
                'answer' => $answer
            ]
        );
    }

    public function _generateCommon($node)
    {
        return [
            'id' => $node['id']
        ];
    }

    public function generateNewNodes($nodes)
    {
        $new_nodes = [];

        foreach ($nodes as $node) {
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

    public function goToStep(string $step): void
    {
        $cached_data = Cache::get($this->cache_key);
        $full_nodes = $cached_data['full_nodes'];
        $nodes_per_step = $cached_data['nodes_per_step'];
        $conditioned_nodes_hash_map = $cached_data['conditioned_nodes_hash_map'];
        $cut_off_hash_map = $cached_data['cut_off_hash_map'];

        if ($this->algorithm_type === 'prevention') {
            $this->validate();
        }

        if ($this->algorithm_type === 'prevention') {
            if (empty(array_filter($this->diagnoses_per_cc, 'array_filter'))) {
                flash()->addError('There is no recommendation for this age range');
                return;
            }
        }

        if ($this->algorithm_type === 'dynamic') {
            if (empty(array_filter($this->diagnoses_per_cc, 'array_filter'))) {
                flash()->addError('This patient is ineligible for the study (age). No clinical data will be collected');
                return;
            }
        }

        if ($this->current_step === 'registration') {
            $this->manageRegistrationStep();
        }

        if ($step === 'first_look_assessment') {
            $this->manageFirstLookAssessmentStep();
        }

        if ($step === 'consultation') {
            $this->manageconsultation_step();
        }

        if ($step === 'tests') {
            $this->manageTestStep();
        }

        if ($step === 'diagnoses') {
            $this->manageDiagnosesStep();
        }


        //quick and dirty fix for training mode
        //todo actually calculate it and change from int to string and
        //search for the index in the array
        if ($this->algorithm_type === 'training' && $step === 'diagnoses') {
            $this->saved_step = 2;
            $this->completion_per_step[1] = 100;
        }

        $this->current_step = $step;
        //We trigger again the calculation in case of modification from the registration
        if ($step === 'consultation') {
            // dd($this->current_nodes['consultation']);
            foreach (array_keys(array_filter($this->chosen_complaint_categories)) as $cc) {
                $this->calculateCompletionPercentage($cached_data, $cc);
            }
        }

        //We set the first substep
        if ($this->algorithm_type === 'dynamic') {
            if (!empty($this->steps[$this->algorithm_type][$this->current_step])) {
                $this->current_sub_step = $this->steps[$this->algorithm_type][$this->current_step][0];
            }
        }

        //Need to be on the future validateStep function, not here and remove the max
        $this->saved_step = max($this->saved_step, array_search($this->current_step, array_keys($this->steps[$this->algorithm_type])) + 1);

        // $this->dispatch('scrollTop');
    }

    public function goToSubStep(string $step, string $substep): void
    {
        $cached_data = Cache::get($this->cache_key);

        $this->goToStep($step);
        $this->current_sub_step = $substep;

        // medicines
        if (($substep === 'medicines') && isset($this->diagnoses_status) && count(array_filter($this->diagnoses_status))) {
            $health_cares = $cached_data['health_cares'];
            $agreed_diagnoses = array_filter($this->diagnoses_status);
            $common_agreed_df = array_intersect_key($this->df_to_display, $agreed_diagnoses);
            foreach ($this->drugs_to_display as $drug_id => $is_displayed) {
                $this->drugs_to_display[$drug_id] = false;
                foreach ($common_agreed_df as $diagnosis_id => $drugs) {
                    if (array_key_exists($drug_id, $drugs)) {
                        if (empty($this->drugs_formulation[$drug_id])) {
                            if ((count($health_cares[$drug_id]['formulations']) <= 1)) {
                                $formulation = $health_cares[$drug_id]['formulations'][0];
                                $this->drugs_formulation[$drug_id] = $formulation['id'];
                            }
                        }
                        $this->drugs_to_display[$drug_id] = true;
                    }
                }
            }
        }
        // summary
        if (($substep === 'summary') && isset($this->drugs_status) && count(array_filter($this->drugs_status))) {
            // drug ids in drug_status and formulations in drugs_formulation
            $common_agreed_df = array_intersect_key($this->df_to_display, array_filter($this->diagnoses_status));
            $common_agreed_drugs = array_intersect_key($this->drugs_formulation, array_filter($this->drugs_status));
            $weight = $this->current_nodes['first_look_assessment']['basic_measurements_nodes_id'][$cached_data['weight_question_id']];
            $formulations = new FormulationService($common_agreed_drugs, $common_agreed_df, $this->cache_key, $weight);
            $this->formulations_to_display = $formulations->getFormulations();
        }
    }

    public function goToCc($cc_id): void
    {
        // if ($this->algorithm_type === 'prevention') {
        //     $this->validate(
        //         [
        //             "current_nodes.consultation.{$this->current_cc}.*" => 'required',
        //         ],
        //         [
        //             'required' => 'This field is required',
        //         ]
        //     );
        // }
        // $this->dispatch('scrollTop');

        $this->current_cc = $cc_id;
    }

    public function goToNextCc(): void
    {
        // if ($this->algorithm_type === 'prevention') {
        //     $this->validate(
        //         [
        //             "current_nodes.consultation.{$this->current_cc}.*" => 'required',
        //         ],
        //         [
        //             'required' => 'This field is required',
        //         ]
        //     );
        // }
        // $this->dispatch('scrollTop');

        $keys = array_keys($this->chosen_complaint_categories);
        $current_index = array_search($this->current_cc, $keys);

        if ($current_index === false) {
            return;
        }

        $next_index = ($current_index + 1) % count($keys);

        $next_key = $keys[$next_index];
        $this->current_cc = $next_key;
    }

    public function goToPreviousCc(): void
    {
        // if ($this->algorithm_type === 'prevention') {
        //     $this->validate(
        //         [
        //             "current_nodes.consultation.{$this->current_cc}.*" => 'required',
        //         ],
        //         [
        //             'required' => 'This field is required',
        //         ]
        //     );
        // }
        // $this->dispatch('scrollTop');

        $keys = array_keys($this->chosen_complaint_categories);
        $current_index = array_search($this->current_cc, $keys);

        if ($current_index === false) {
            return;
        }

        $count = count($keys);
        $previous_index = ($current_index - 1 + $count) % $count;

        $previous_key = $keys[$previous_index];
        $this->current_cc = $previous_key;
    }

    public function sendToErpNext()
    {
        if (!$this->data) {
            return flash()->addError('No current patient');
        }

        $cached_data = Cache::get($this->cache_key);
        $nodes = $cached_data['full_nodes'];
        $df = $cached_data['final_diagnoses'];
        $health_cares = $cached_data['health_cares'];

        foreach (array_filter($this->diagnoses_status) as $diagnose_id => $accepted) {
            $response = Http::acceptJson()
                ->withToken('354d0b462045526:7b87001c6800153', 'token')
                ->post("http://development.localhost:8000/api/resource/Diagnosis", [
                    'diagnosis' => $df[$diagnose_id]['label']['en'],
                    'estimated_duration' => 259200
                ])
                ->throwUnlessStatus(409);

            $data["diagnosis"][] = [
                "docstatus" => 1,
                "diagnosis" => $df[$diagnose_id]['label']['en'],
            ];
        }

        foreach (array_filter($this->chosen_complaint_categories) as $cc_id => $accepted) {
            $response = Http::acceptJson()
                ->withToken('354d0b462045526:7b87001c6800153', 'token')
                ->post("http://development.localhost:8000/api/resource/Complaint", [
                    'complaints' => $nodes[$cc_id]['label']['en'],
                ])
                ->throwUnlessStatus(409);

            $data["symptoms"][] = [
                "docstatus" => 1,
                "complaint" => $nodes[$cc_id]['label']['en']
            ];
        }

        $response = Http::acceptJson()
            ->withToken('354d0b462045526:7b87001c6800153', 'token')
            ->post("http://development.localhost:8000/api/resource/Medication%20Class", ['medication_class' => 'Generic'])
            ->throwUnlessStatus(409);

        foreach (array_filter($this->drugs_status) as $drug_id => $agreed) {

            $items[$drug_id] = [
                'docstatus' => 1,
                'item_code' => $health_cares[$drug_id]['label']['en'],
                'item_name' => $health_cares[$drug_id]['label']['en'],
                "item_group" => "Drug",
                "stock_uom" => "Gram",
            ];

            //Leave that here in case of formulation needed later
            // if (array_key_exists($drug_id, $this->df_to_display[$df_id])) {
            // $formulation = collect($health_cares[$drug_id]['formulations'])->where('id', '=', $this->drugs_formulation[$drug_id])->first();
            // }

            $drugs[$drug_id] = [
                "docstatus" => 1,
                "generic_name" => $health_cares[$drug_id]['label']['en'],
                "medication_class" => "Generic",
                "strength" => 1.0,
                "strength_uom" => "Gram",
                "default_interval" => 0,
                "default_interval_uom" => "Hour",
                "change_in_item" => 0
            ];

            $data["drug_prescription"][] = [
                "docstatus" => 1,
                "medication" => $health_cares[$drug_id]['label']['en'],
                "drug_code" => $health_cares[$drug_id]['label']['en'],
                "drug_name" => $health_cares[$drug_id]['label']['en'],
                "strength" => 1.0,
                "strength_uom" => "Gram",
                "dosage_form" => "Capsule",
                "dosage_by_interval" => 0,
                "dosage" => "1-0-0",
                "interval" => 1,
                "interval_uom" => "Day",
                "period" => "1 Day",
                "number_of_repeats_allowed" => 0.0,
                "update_schedule" => 1
            ];

            $response = Http::acceptJson()
                ->withToken('354d0b462045526:7b87001c6800153', 'token')
                ->post("http://development.localhost:8000/api/resource/Item", $items[$drug_id])
                ->throwUnlessStatus(409);

            $response = Http::acceptJson()
                ->withToken('354d0b462045526:7b87001c6800153', 'token')
                ->post("http://development.localhost:8000/api/resource/Medication", $drugs[$drug_id])
                ->throwUnlessStatus(409);
        }

        if (isset($this->data['existing']) && !$this->data['existing']) {
            $response = Http::acceptJson()
                ->withToken('354d0b462045526:7b87001c6800153', 'token')
                ->put("http://development.localhost:8000/api/resource/Patient%20Encounter/" . $this->data['name'], $data)
                ->throwUnlessStatus(409)
                ->json();
        } else {

            $data["practitioner"] = "Lui";
            $data["patient"] = "{$this->data['first_name']} {$this->data['last_name']}";

            $response = Http::acceptJson()
                ->withToken('354d0b462045526:7b87001c6800153', 'token')
                ->post("http://development.localhost:8000/api/resource/Patient%20Encounter", $data)
                ->throwUnlessStatus(409)
                ->json();
        }

        flash()->addSuccess('Patient updated successfully');
        return Redirect::to('http://development.localhost:8000/app/patient-encounter/' . $response['data']['name']);
    }

    public function sendToMedalData()
    {
        // We need to flatten nodes before
        $current_nodes = new RecursiveIteratorIterator(
            new RecursiveArrayIterator($this->current_nodes)
        );

        foreach ($current_nodes as $key => $value) {
            $nodes[$key] = $value;
        }

        $data = [
            'nodes' => $nodes,
            'nodes_to_save' => $this->nodes_to_save,
            'df' => $this->df_to_display,
            'df_status' => $this->diagnoses_status,
            'drugs_status' => $this->drugs_status,
            'drugs_formulation' => $this->drugs_formulation,
            'complaint_categories' => $this->chosen_complaint_categories,
            'patient_id' => $this->patient_id,
            'version_id' => $this->id,
        ];

        $json = $this->jsonExportService->prepareJsonData($data);
        dd($json);

        // $response = $this->fhirService->setConditionsToPatient($this->patient_id, $conditions);

        // if (!$response) {
        //     flash()->addError('An error occured while saving. Please try again');
        //     return;
        // }

        flash()->addSuccess('Patient updated successfully');
        return redirect()->route("home.hidden");
    }

    public function setConditionsToPatients()
    {
        // We need to flatten nodes before
        $current_nodes = new RecursiveIteratorIterator(
            new RecursiveArrayIterator($this->current_nodes)
        );

        foreach ($current_nodes as $key => $value) {
            $nodes[$key] = $value;
        }

        $data = [
            'nodes' => $nodes,
            'nodes_to_save' => $this->nodes_to_save,
            'df' => $this->df_to_display,
            'df_status' => $this->diagnoses_status,
            'drugs_status' => $this->drugs_status,
            'drugs_formulation' => $this->drugs_formulation,
            'complaint_categories' => $this->chosen_complaint_categories,
            'patient_id' => $this->patient_id,
            'version_id' => $this->id,
        ];

        $json = $this->jsonExportService->prepareJsonData($data);
        dd($json);

        if (!$this->patient_id) {
            return flash()->addError('No current patient');
        }

        $cached_data = Cache::get($this->cache_key);
        $df = $cached_data['final_diagnoses'];

        $agreed_diagnoses = array_filter($this->diagnoses_status);
        foreach ($agreed_diagnoses as $diagnose_id => $accepted) {
            $conditions[] = [
                'medal_c_id' => "$diagnose_id",
                'label' => $df[$diagnose_id]['label']['en'],
            ];
        }
        if (!isset($conditions)) {
            flash()->addError('There is no agreed diagnose');
            return;
        }

        $response = $this->fhirService->setConditionsToPatient($this->patient_id, $conditions);

        if (!$response) {
            flash()->addError('An error occured while saving. Please try again');
            return;
        }

        flash()->addSuccess('Patient updated successfully');
        return redirect()->route("home.hidden");
    }

    private function appendOrInsertAtPos(array $input_array, array $insert, int $target_key)
    {
        $output = array();
        $new_value = reset($insert);
        $new_key = key($insert);

        foreach ($input_array as $key => $value) {
            if ($key === $target_key) {
                $output[$key] = $value;
                $output[$new_key] = $new_value;
            } else {
                $output[$key] = $value;
            }
        }

        if (!isset($output[$new_key])) {
            $output[$new_key] = $new_value;
        }

        return $output;
    }

    public function render()
    {
        $view = match ($this->algorithm_type) {
            'dynamic' => 'livewire.dynamic-algorithm',
            'prevention' => 'livewire.prevention-algorithm',
            'training' => 'livewire.training-algorithm'
        };

        return view($view);
    }
}
