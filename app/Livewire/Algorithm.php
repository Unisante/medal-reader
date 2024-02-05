<?php

namespace App\Livewire;

use App\Services\AlgorithmService;
use App\Services\FHIRService;
use App\Services\FormulationService;
use Barryvdh\Debugbar\Facades\Debugbar;
use Cerbero\JsonParser\JsonParser;
use DateTime;
use DCarbone\PHPFHIRGenerated\R4\PHPFHIRResponseParser;
use DivisionByZeroError;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Component;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

class Algorithm extends Component
{
    public int $id;
    public $patient_id;
    public string $cache_key;
    public int $cache_expiration_time;
    public string $title;
    public string $algorithm_type;
    public bool $debug_mode = true;
    //todo remove definition when in prod
    public string $age_key = 'older';
    public string $current_step = 'registration';
    public int $saved_step = 1;
    public int $current_cc;
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

    private AlgorithmService $algorithmService;
    private FHIRService $fhirService;
    public array $treatment_questions;
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

    public array $completion_per_step = [
        'registration' => 0,
        'first_look_assessment' => 0,
        'consultation' => 0,
        'tests' => 0,
        'diagnoses' => 0,
    ];

    public string $current_sub_step = '';

    public function boot(AlgorithmService $algorithmService, FHIRService $fhirService)
    {
        $this->algorithmService = $algorithmService;
        $this->fhirService = $fhirService;
    }

    public function mount($id = null, $patient_id = null)
    {
        $this->id = $id;
        $this->patient_id = $patient_id;

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
        //todo set the update cache behovior on json update and set it indefinitely
        $this->cache_expiration_time = 86400; // 24 hours

        //todo set that up in redis when in prod
        if (config('app.debug')) {
            Cache::forget($this->cache_key);
        }

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
                    'first_look_nodes_id' => $json['medal_r_json']['config']['full_order']['first_look_assessment_step'] ?? [],
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
                'df_hash_map' => [],
                'drugs_hash_map' => [],
                'conditioned_nodes_hash_map' => [],
                'managements_hash_map' => [],
                'dependency_map' => [],
                'nodes_to_update' => [],
                'nodes_per_step' => [],
                'no_condition_nodes' => [],
                'need_emergency' => [],
                'female_gender_answer_id' => '',
                'male_gender_answer_id' => '',
                'registration_total' => '',
                'first_look_assessment_total' => '',
            ], $this->cache_expiration_time);
        }

        $cached_data = Cache::get($this->cache_key);

        $df_hash_map = [];
        $drugs_hash_map = [];
        $managements_hash_map = [];

        foreach ($cached_data['final_diagnoses'] as $df) {
            foreach ($df['conditions'] as $condition) {
                $df_hash_map[$condition['answer_id']][] = $df['id'];
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
        $nodes_to_update = [];
        $conditioned_nodes_hash_map = [];
        $need_emergency = [];
        JsonParser::parse(Storage::get("$extract_dir/$id.json"))
            ->pointer('/medal_r_json/nodes')
            ->traverse(function (mixed $value, string|int $key, JsonParser $parser) use (&$formula_hash_map, &$nodes_to_update, &$conditioned_nodes_hash_map, &$need_emergency) {
                foreach ($value as $node) {
                    if ($node['type'] === 'QuestionsSequence' || $node['display_format'] === 'Reference') {
                        continue;
                    }
                    if ($node['emergency_status'] === 'emergency') {
                        $need_emergency[$node['emergency_answer_id']] = $node['id'];
                    }
                    if ($node['display_format'] === "Input" || $node['display_format'] === "Formula") {
                        $this->nodes_to_save[$node['id']]  = [
                            'value' => '',
                            'answer_id' => '',
                            'label' => '',
                        ];
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
        $female_gender_answer_id = '';
        $male_gender_answer_id = '';

        foreach ($cached_data['complaint_categories_steps'] as $step) {
            $diagnosesForStep = collect($cached_data['diagnoses'])->filter(function ($diag) use ($step, $female_gender_answer_id, $male_gender_answer_id) {
                return $diag['complaint_category'] === $step;
            });

            foreach ($diagnosesForStep as $diag) {
                foreach ($diag['instances'] as $instance_id => $instance) {

                    if (!array_key_exists('display_format', $cached_data['full_nodes'][$instance_id])) {
                        continue;
                    }

                    if ($instance_id === $cached_data['gender_question_id']) {
                        $female_gender_answer_id = collect($cached_data['full_nodes'][$instance_id]['answers'])->where('value', 'female')->first()['id'];
                        $male_gender_answer_id = collect($cached_data['full_nodes'][$instance_id]['answers'])->where('value', 'male')->first()['id'];
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
                        $consultation_nodes[$system][$step][$instance_id] = '';
                    }

                    if (!empty($instance['conditions'])) {

                        foreach ($instance['conditions'] as $condition) {
                            $answer_id = $condition['answer_id'];
                            $node_id = $condition['node_id'];
                            if (!isset($answers_hash_map[$step][$answer_id])) {
                                $answers_hash_map[$step][$answer_id] = [];
                            }

                            if (!in_array($instance_id, $answers_hash_map[$step][$answer_id])) {
                                $answers_hash_map[$step][$answer_id][] = $instance_id;
                            }

                            $this->algorithmService->breadthFirstSearch($diag['instances'], $node_id, $answer_id, $dependency_map, $this->cache_key);

                            $node = $cached_data['full_nodes'][$node_id];

                            if ($node['type'] === 'QuestionsSequence') {
                                foreach ($node['instances'] as $qs_instance) {
                                    if (!empty($qs_instance['conditions'])) {
                                        foreach ($qs_instance['conditions'] as $qs_condition) {
                                            // We only add or treat nodes that have a system
                                            //todo fix breadthFirstSearch children for questionSequence
                                            // Example Vomiting everything -> no is linked to Unconscious or Lethargic (Unusually sleepy)
                                            // which then remove it
                                            // Need to find the good if because if we just filter priority_sign it will then not remove
                                            // if (isset($cached_data['full_nodes'][$qs_instance['id']]['system']) && $cached_data['full_nodes'][$qs_instance['id']]['system'] !== 'priority_sign') {
                                            $answers_hash_map[$step][$qs_condition['answer_id']][] = $qs_instance['id'];
                                            $this->algorithmService->breadthFirstSearch($node['instances'], $qs_condition['node_id'], $qs_condition['answer_id'], $dependency_map, $this->cache_key);
                                            // }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }


        $this->algorithmService->sortSystemsAndNodesPerCC($consultation_nodes, $this->cache_key);

        $nodes_per_step = [
            'registration' => $registration_nodes,
            'first_look_assessment' => $first_look_assessment_nodes ?? [],
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
                'need_emergency' => $need_emergency,
                'female_gender_answer_id' => $female_gender_answer_id,
                'male_gender_answer_id' => $male_gender_answer_id,
                'registration_total' => count($cached_data['registration_nodes_id']),
                'first_look_assessment_total' =>  count($cached_data['first_look_assessment_nodes_id']),
            ], $this->cache_expiration_time);
            $cached_data = Cache::get($this->cache_key);
        }

        $this->current_nodes['registration'] = $registration_nodes;

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

        if ($this->algorithm_type === 'prevention') {
            $this->current_nodes['registration'] +=
                $nodes_per_step['first_look_assessment']['basic_measurements_nodes_id'];

            $this->current_nodes['first_look_assessment']['complaint_categories_nodes_id'] =
                $cached_data['nodes_per_step']['first_look_assessment']['complaint_categories_nodes_id'][$this->age_key];
        }
        //END TO REMOVE
        // dd($this->current_nodes);
        // If we are in training mode then we go directly to consultation step
        if ($this->algorithm_type === 'training') {
            $this->chosen_complaint_categories[$cached_data['general_cc_id']] = true;
            $this->goToStep('consultation');
        }

        if ($patient_id) {
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
            }
        }

        // dd($this->registration_nodes_id);
        // dd($cached_data);
        // dump($conditioned_nodes_hash_map);
        // dd($cached_data['full_nodes']);
        // dd($this->current_nodes);
        // dump($cached_data['full_order']);
        // dump($cached_data['nodes_per_step']);
        // dump(array_unique(Arr::flatten($cached_data['nodes_per_step'])));
        dump($cached_data['formula_hash_map']);
        // dump($cached_data['drugs_hash_map']);
        dump($cached_data['answers_hash_map']);
        // dump($cached_data['dependency_map']);
        // dump($cached_data['df_hash_map']);
        // dump($cached_data['consultation_nodes']);
        dump($cached_data['nodes_to_update']);
        // dump($cached_data['managements_hash_map']);
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

                $cached_data = Cache::get($this->cache_key);
                //todo optimize this function. As it take 1,3s for 35 nodes
                $this->updateLinkedNodesOfDob($value);

                // Set the first_look_assessment nodes that are depending on the age key
                $this->current_nodes['first_look_assessment']['complaint_categories_nodes_id'] =
                    $cached_data['nodes_per_step']['first_look_assessment']['complaint_categories_nodes_id'][$this->age_key];
            }
            return;
        }

        // If the answer trigger the emergency modal
        if ($this->algorithm_type === 'dynamic') {
            if (array_key_exists($value, $need_emergency)) {
                $this->dispatch('openEmergencyModal');
            }
        }
    }

    public function updatingCurrentNodes($value, $key)
    {
        if ($this->algorithmService->isDate($value)) return;
        $node_id = Str::of($key)->explode('.')->last();
        $old_answer_id = Arr::get($this->current_nodes, $key);

        $this->saveNode($node_id, $value, $value, $old_answer_id);
    }
    public function updatingChosenComplaintCategories($key, int $modified_cc_id)
    {
        $cached_data = Cache::get($this->cache_key);
        $nodes_per_step = $cached_data['nodes_per_step'];
        $conditioned_nodes_hash_map = $cached_data['conditioned_nodes_hash_map'];
        $old_value = $this->chosen_complaint_categories[$modified_cc_id] ?? null;
        $current_nodes_per_step = $nodes_per_step['consultation']['general'];

        // We only do this modification behavior if the consultation step has already been calculated
        if (isset($this->current_nodes['consultation'])) {
            if ($this->algorithm_type !== 'dynamic') {
                if ($old_value) {
                    unset($this->current_nodes['consultation'][$modified_cc_id]);
                } else {
                    foreach ($current_nodes_per_step as $system_name => $system_data) {
                        if ($modified_cc_id === $system_name) {
                            $this->current_nodes['consultation'][$modified_cc_id] = $system_data;
                        }
                    }
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
    public function saveNode($node_id, $value, $answer_id, $old_answer_id)
    {
        Debugbar::startMeasure("saveNode");
        Debugbar::startMeasure("saveNodecache");

        $cached_data = Cache::get($this->cache_key);
        $formula_hash_map = $cached_data['formula_hash_map'];
        $drugs_hash_map = $cached_data['drugs_hash_map'];
        $managements_hash_map = $cached_data['managements_hash_map'];
        $nodes_to_update = $cached_data['nodes_to_update'];
        $full_nodes = $cached_data['full_nodes'];

        Debugbar::stopMeasure("saveNodecache");

        if (array_key_exists($node_id, $this->nodes_to_save)) {
            $node = $full_nodes[$node_id];
            $system = isset($node['system']) ? $node['system'] : 'others';
            $this->nodes_to_save[$node_id]['value'] = $value;
            Log::info("savedNode $node_id -> $value");

            if (array_key_exists($node_id, $formula_hash_map)) {
                $pretty_answer = $this->handleFormula($node_id);

                if ($this->current_step === 'registration') {
                    $this->current_nodes['registration'][$node_id] = $pretty_answer;
                }

                if ($this->current_step === 'first_look_assessment') {
                    $this->current_nodes['first_look_assessment']['basic_measurements_nodes_id'][$node_id] = $pretty_answer;
                }

                if ($this->current_step === 'consultation') {
                    if ($this->algorithm_type === 'dynamic') {
                        $this->current_nodes['consultation']['medical_history'][$system][$node_id] = $pretty_answer;
                    } else {
                        $this->current_nodes['consultation']['medical_history'][$this->current_cc][$node_id] = $pretty_answer;
                    }
                }
            }

            //This function will save in nodes_to_save the answer
            //We do not display this pretty answer for now
            $old_answer_id = $this->nodes_to_save[$node_id]['answer_id'] ?? null;
            $pretty_answer_not_used = $this->handleAnswers($node_id, $this->nodes_to_save[$node_id]['value']);

            // If answer will set a drug, we add it to the drugs to display
            if (array_key_exists($this->nodes_to_save[$node_id]['answer_id'], $drugs_hash_map)) {
                foreach ($drugs_hash_map[$this->nodes_to_save[$node_id]['answer_id']] as $drug_id) {
                    if (!array_key_exists($drug_id, $this->drugs_to_display)) {
                        $this->drugs_to_display[$drug_id] = false;
                    }
                }
            }

            // If answer will set a management, we add it to the managements to display
            if (isset($this->all_managements_to_display)) {
                if (array_key_exists($this->nodes_to_save[$node_id]['answer_id'], $managements_hash_map)) {
                    $this->all_managements_to_display = [
                        ...$this->all_managements_to_display,
                        ...$managements_hash_map[$this->nodes_to_save[$node_id]['answer_id']]
                    ];
                }
            }

            // If node is linked to some bc, we calculate them directly
            if (array_key_exists($node_id, $nodes_to_update)) {
                foreach ($nodes_to_update[$node_id] as $node_to_update_id) {
                    $pretty_answer = $this->handleFormula($node_to_update_id);
                    if ($this->current_step === 'registration') {
                        $this->current_nodes['registration'][$node_to_update_id] = $pretty_answer;
                    }
                    if ($this->current_step === 'first_look_assessment') {
                        $this->current_nodes['first_look_assessment']['basic_measurements_nodes_id'][$node_to_update_id] = $pretty_answer;
                    }
                    if ($this->current_step === 'consultation') {
                        if ($this->algorithm_type === 'dynamic') {
                            $this->current_nodes['consultation']['medical_history']['others'][$node_to_update_id] = $pretty_answer;
                        } else {
                            $this->current_nodes['consultation']['medical_history'][$this->current_cc][$node_to_update_id] = $pretty_answer;
                        }
                    }
                }
            }
        }
        Debugbar::stopMeasure("saveNode");
        $t = $this->nodes_to_save[$node_id]['answer_id'] ?? $answer_id;

        Log::info("displayNextNode $t -> $old_answer_id");

        return $this->displayNextNode($node_id, $this->nodes_to_save[$node_id]['answer_id'] ?? $answer_id, $old_answer_id);
    }

    #[On('nodeUpdated')]
    public function displayNextNode($node_id, $value, $old_value)
    {
        Debugbar::startMeasure("displayNextNode");
        Debugbar::startMeasure("displayNextNodeCache");
        $cached_data = Cache::get($this->cache_key);
        $dependency_map = $cached_data['dependency_map'];
        $formula_hash_map = $cached_data['formula_hash_map'];
        $final_diagnoses = $cached_data['final_diagnoses'];
        $df_hash_map = $cached_data['df_hash_map'];
        $health_cares = $cached_data['health_cares'];

        Debugbar::stopMeasure("displayNextNodeCache");

        // Modification behavior
        if ($old_value && $value !== $old_value) {
            // Remove linked diag and management
            if (isset($df_hash_map[$old_value])) {
                foreach ($df_hash_map[$old_value] as $df) {
                    if (array_key_exists($df, $this->df_to_display)) {
                        if (isset($final_diagnoses[$df]['managements'])) {
                            unset($this->all_managements_to_display[key($final_diagnoses[$df]['managements'])]);
                        }
                        unset($this->df_to_display[$df]);
                    }
                }
            }
            // Remove every linked nodes to old answer
            if (array_key_exists($old_value, $dependency_map)) {
                foreach ($dependency_map[$old_value] as $node_id) {
                    foreach ($this->current_nodes['consultation']['medical_history'] as $system_name => $nodes_per_system) {
                        if (isset($this->current_nodes['consultation']['medical_history'][$system_name][$node_id])) {
                            // Remove every df and managements dependency of linked nodes
                            if (array_key_exists($this->current_nodes['consultation']['medical_history'][$system_name][$node_id], $df_hash_map)) {
                                foreach ($df_hash_map[$this->current_nodes['consultation']['medical_history'][$system_name][$node_id]] as $df) {
                                    if (array_key_exists($df, $this->df_to_display)) {
                                        if (isset($final_diagnoses[$df]['managements'])) {
                                            unset($this->all_managements_to_display[key($final_diagnoses[$df]['managements'])]);
                                        }
                                        unset($this->df_to_display[$df]);
                                    }
                                }
                            }
                            unset($this->current_nodes['consultation']['medical_history'][$system_name][$node_id]);
                        }
                    }
                }
            }
        }

        $next_nodes_id = $this->getNextNodesId($value);

        //if next node is background calc -> calc and directly show next <3
        if ($next_nodes_id) {
            foreach ($next_nodes_id as $node) {
                if (array_key_exists($node, $formula_hash_map)) {
                    if (!array_key_exists($node, $this->current_nodes['registration'])) {
                        $pretty_answer = $this->handleFormula($node);
                        if ($this->current_step === 'first_look_assessment') {
                            $this->current_nodes['first_look_assessment']['basic_measurements_nodes_id'][$node] = $pretty_answer;
                        }
                        if ($this->current_step === 'consultation') {
                            if ($this->algorithm_type === 'dynamic') {
                                $this->current_nodes['consultation']['medical_history']['others'][$node] = $pretty_answer;
                            } else {
                                $this->current_nodes['consultation']['medical_history'][$this->current_cc][$node] = $pretty_answer;
                            }
                        }
                        $next_nodes_id = $this->getNextNodesId($this->nodes_to_save[$node]['value']);
                    }
                }
            }
        }

        //if next node is DF, add it to df_to_display <3
        if (isset($df_hash_map[$value])) {
            $other_conditions_met = true;
            foreach ($df_hash_map[$value] as $df) {
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
                                && intval($this->nodes_to_save[$condition['node_id']]['answer_id']) != $condition['answer_id']
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
                                if (array_key_exists($drug['id'], $this->drugs_to_display)) {
                                    $drugs[$drug_id] = $drug_id;
                                }
                            }
                        }

                        $this->df_to_display[$df] = $drugs;
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

        if ($next_nodes_id) {
            foreach ($next_nodes_id as $node) {
                if (!array_key_exists($node, $this->current_nodes['registration'])) {
                    Log::info("set next node $node");
                    $this->setNextNode($node);
                }
            }
        }

        Debugbar::stopMeasure("displayNextNode");
    }

    public function handleFormula($node_id)
    {
        $cached_data = Cache::get($this->cache_key);
        $formula_hash_map = $cached_data['formula_hash_map'];
        $full_nodes = $cached_data['full_nodes'];
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
                    if ($days <= 59) {
                        if ($this->algorithm_type === 'dynamic') {
                            $this->age_key = 'neonat';
                            $this->current_cc = $yi_general_cc_id;
                            $this->chosen_complaint_categories[$yi_general_cc_id] = true;
                            if (array_key_exists($general_cc_id, $this->chosen_complaint_categories)) {
                                unset($this->chosen_complaint_categories[$general_cc_id]);
                            }
                        }
                    } else {
                        $this->age_key = 'older';
                        if ($this->algorithm_type === 'dynamic') {
                            $this->current_cc = $general_cc_id;
                            $this->chosen_complaint_categories[$general_cc_id] = true;
                            if (array_key_exists($yi_general_cc_id, $this->chosen_complaint_categories)) {
                                unset($this->chosen_complaint_categories[$yi_general_cc_id]);
                            }
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

        return $this->handleAnswers($node_id, $result);
    }

    public function handleAnswers($node_id, $value)
    {
        $answers = Cache::get($this->cache_key)['full_nodes'][$node_id]["answers"];
        foreach ($answers as $answer) {
            $result = intval($value);
            $answer_value = $answer['value'];
            $answer_values = explode(',', $answer_value);
            $minValue = intval($answer_values[0]);
            $maxValue = intval($answer_values[1] ?? $minValue);

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
                return $label;
            }
        }
        $this->nodes_to_save[$node_id]['value'] = $value;

        return $value;
    }

    public function updateLinkedNodesOfDob($value)
    {
        $cached_data = Cache::get($this->cache_key);
        $birth_date_formulas = $cached_data['birth_date_formulas'];

        foreach ($birth_date_formulas as $node_id) {
            $pretty_answer = $this->handleFormula($node_id);
            $this->current_nodes['registration'][$node_id] = $pretty_answer;
        }
    }

    public function setNextNode($next_node_id)
    {
        $cached_data = Cache::get($this->cache_key);
        $full_nodes = $cached_data['full_nodes'];

        if (isset($full_nodes[$next_node_id])) {
            $node = $full_nodes[$next_node_id];
            //We don't sort non dynamic study for now
            // sort physical_exam, observed_physical_sign as one group
            // symptom, predefined_syndrome,background_calculation,exposure as one group
            // assessment test goes somewhere else.
            if ($this->algorithm_type === 'dynamic') {
                $system = isset($node['system']) ? $node['system'] : 'others';
                match ($node['category']) {
                    'physical_exam' => $this->current_nodes['consultation']['physical_exam'][$system][$node["id"]] = '',
                    'symptom', 'predefined_syndrome', 'background_calculation', 'exposure' => $this->current_nodes['consultation']['medical_history'][$system][$node["id"]] = '',
                    'assessment_test' => $this->current_nodes['tests'][$node["id"]] = '',
                    'treatment_question' => $this->current_nodes['diagnoses']['treatment_questions'][$node["id"]] = false,
                    default => null,
                };
                if (isset($this->current_nodes['consultation']['physical_exam'])) {
                    $this->algorithmService->sortSystemsAndNodes($this->current_nodes['consultation']['physical_exam'], $this->cache_key);
                }
                if (isset($this->current_nodes['consultation']['medical_history'])) {
                    $this->algorithmService->sortSystemsAndNodes($this->current_nodes['consultation']['medical_history'], $this->cache_key);
                }
            } else {
                $this->current_nodes[$this->current_step]['medical_history'][$this->current_cc][$next_node_id] = '';
            }
        }
    }


    public function getNextNodesId($node_id)
    {
        $cached_data = Cache::get($this->cache_key);
        $answers_hash_map = $cached_data['answers_hash_map'];

        $next_nodes = [];

        foreach ($this->chosen_complaint_categories as $category => $chosen) {
            if ($chosen) {
                if (isset($answers_hash_map[$category][$node_id])) {
                    $next_nodes = [
                        ...$next_nodes,
                        ...$answers_hash_map[$category][$node_id]
                    ];
                }
            }
        }

        return $next_nodes ?? null;
    }

    public function goToStep(string $step): void
    {
        $cached_data = Cache::get($this->cache_key);
        $nodes_per_step = $cached_data['nodes_per_step'];
        $conditioned_nodes_hash_map = $cached_data['conditioned_nodes_hash_map'];

        if ($this->algorithm_type === 'dynamic') {
            if ($step === 'first_look_assessment') {
                if (!isset($this->current_nodes['first_look_assessment']['first_look_nodes_id'])) {
                    $this->current_nodes['first_look_assessment']['first_look_nodes_id'] =
                        $nodes_per_step['first_look_assessment']['first_look_nodes_id'];

                    $this->current_nodes['first_look_assessment']['basic_measurements_nodes_id'] =
                        $nodes_per_step['first_look_assessment']['basic_measurements_nodes_id'];
                }
            }
        }

        if ($step === 'consultation') {
            if (!isset($this->current_nodes['consultation'])) {
                $cc_order = array_flip($cached_data['complaint_categories_steps']);

                // Respect the order in the complaint_categories_step key
                if (!$this->algorithm_type !== 'dynamic') {
                    uksort($this->chosen_complaint_categories, function ($a, $b) use ($cc_order) {
                        return $cc_order[$a] <=> $cc_order[$b];
                    });
                }

                $this->current_cc = key(array_filter($this->chosen_complaint_categories));
                $current_nodes_per_step = $nodes_per_step[$step];

                foreach ($current_nodes_per_step as $system_name => $system_data) {
                    foreach ($system_data as $cc_id => $nodes) {
                        if (isset($this->chosen_complaint_categories[$cc_id])) {
                            if ($this->algorithm_type === 'dynamic') {
                                $consultation_nodes[$system_name] = $system_data[$cc_id];
                            } else {
                                $consultation_nodes[$cc_id] = $system_data[$cc_id];
                            }
                            continue;
                        }

                        // We only add nodes that are not excluded by CC
                        if (isset($conditioned_nodes_hash_map[$cc_id])) {
                            $consultation_nodes[$system_name] = array_diff(
                                $consultation_nodes[$system_name] ?? [],
                                $conditioned_nodes_hash_map[$cc_id]
                            );
                        }
                    }
                }
                $this->current_nodes['consultation']['medical_history'] = $consultation_nodes;
            }
            $this->current_cc = key(array_filter($this->chosen_complaint_categories));

            // dd($consultation_nodes);
        } else {
            // For registration step we do not know the $age_key yet
            // $this->current_nodes = $cached_data['nodes_per_step'][$step];
        }

        //quick and dirty fix for training mode
        //todo actually calculate it and change from int to string and
        //search for the index in the array
        if ($step === 'diagnoses') {
            $this->saved_step = 2;
            $this->completion_per_step[1] = 100;
        }

        $this->current_step = $step;

        //We set the first substep
        if (!empty($this->steps[$this->current_step])) {
            $this->current_sub_step = $this->steps[$this->current_step][0];
        }

        //todo uncomment it when in prod
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

    public function goToNextCc(): void
    {
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
