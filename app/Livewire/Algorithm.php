<?php

namespace App\Livewire;

use App\Services\AlgorithmService;
use App\Services\FHIRService;
use App\Services\FormulationService;
use App\Services\JsonExportService;
use App\Services\ReferenceCalculator;
use Carbon\Carbon;
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

class Algorithm extends Component
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
    public object $created_at;
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
    public array $last_system_updated;
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
    public array $formulations;

    private AlgorithmService $algorithmService;
    private ReferenceCalculator $referenceCalculator;
    private FHIRService $fhirService;
    private JsonExportService $jsonExportService;
    public array $treatment_questions;
    // private array $diagnoses_formulation;

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
            'consultation' => [
                'medical_history',
            ],
            'diagnoses' => [
                'final_diagnoses',
            ],
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
        $this->created_at = Carbon::now();
        $json_version = $json['medal_r_json_version'];
        $project_name = $json['algorithm_name'] ?? $json['medal_r_json']['algorithm_name'];
        $matching_projects = array_filter(config('medal.projects'), function ($project) use ($project_name) {
            return Str::contains($project_name, $project);
        });

        $this->algorithm_type = $matching_projects ? key($matching_projects) : 'training';
        $this->cache_key = "json_data_{$this->id}_$json_version";

        //todo set that up in redis or else when in prod
        if (config('app.debug')) {
            Cache::forget($this->cache_key);
        }

        $algorithm = $json['medal_r_json'];
        $algorithm['nodes'] = array_replace(
            $json['medal_r_json']['nodes'],
            $json['medal_r_json']['final_diagnoses'],
            $json['medal_r_json']['health_cares']
        );

        $cache_found = Cache::has($this->cache_key);
        if (!$cache_found) {
            Cache::put($this->cache_key, [
                'algorithm' => $algorithm,
                'full_nodes' => collect($json['medal_r_json']['nodes'])->keyBy('id')->all(),
                'birth_date_formulas' => $json['medal_r_json']['config']['birth_date_formulas'],
                'general_cc_id' => $json['medal_r_json']['config']['basic_questions']['general_cc_id'],
                'yi_general_cc_id' => $json['medal_r_json']['config']['basic_questions']['yi_general_cc_id'],
                'gender_question_id' => $json['medal_r_json']['config']['basic_questions']['gender_question_id'],
                'weight_question_id' => $json['medal_r_json']['config']['basic_questions']['weight_question_id'],
                'village_question_id' => $json['medal_r_json']['config']['optional_basic_questions']['village_question_id'],
                'villages' => array_merge(...$json['medal_r_json']['village_json'] ?? []), // No village for non dynamic study;

                // All logics that will be calulated
                'formula_hash_map' => [],
                'max_path_length' => [],
                'need_emergency' => [],
                'female_gender_answer_id' => '',
                'male_gender_answer_id' => '',
            ]);
        }

        $json_data = Cache::get($this->cache_key);

        $formula_hash_map = [];
        $need_emergency = [];
        JsonParser::parse(Storage::get("$extract_dir/$id.json"))
            ->pointer('/medal_r_json/nodes')
            ->traverse(function (mixed $value, string|int $key, JsonParser $parser) use (&$formula_hash_map, &$need_emergency) {
                foreach ($value as $node) {

                    if ($node['type'] === 'QuestionsSequence') {
                        continue;
                    }

                    if ($node['emergency_status'] === 'emergency') {
                        $need_emergency[$node['emergency_answer_id']] = $node['id'];
                    }

                    if ($node['category'] === "background_calculation" || $node['display_format'] === "Formula") {
                        $formula_hash_map[$node['id']] = $node['formula'] ?? '';
                    }
                }
            });

        $answers_hash_map = [];
        $max_path_length = [];
        $consultation_nodes = [];
        $female_gender_answer_id = '';
        $male_gender_answer_id = '';
        $qs_hash_map = [];

        $ccs = [
            ...$algorithm['config']['full_order']['complaint_categories_step']['older'],
            ...$algorithm['config']['full_order']['complaint_categories_step']['neonat'],
        ];

        foreach ($ccs as $step) {
            $diagnosesForStep = collect($json_data['algorithm']['diagnoses'])->filter(function ($diag) use ($step, $female_gender_answer_id, $male_gender_answer_id, &$qs_hash_map) {
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

                    if (!array_key_exists('display_format', $json_data['full_nodes'][$instance_id]) && $json_data['full_nodes'][$instance_id]['type'] !== 'QuestionsSequence') {
                        continue;
                    }

                    if ($instance_id === $json_data['gender_question_id']) {
                        $female_gender_answer_id = collect($json_data['full_nodes'][$instance_id]['answers'])->where('value', 'female')->first()['id'];
                        $male_gender_answer_id = collect($json_data['full_nodes'][$instance_id]['answers'])->where('value', 'male')->first()['id'];
                    }

                    $instance_node = $json_data['full_nodes'][$instance_id];

                    if (empty($instance['conditions'])) {

                        if (!isset($dependency_map[$diag['id']])) {
                            $dependency_map[$diag['id']] = [];
                        }

                        if ($instance_node['type'] === 'QuestionsSequence' && $instance['final_diagnosis_id'] === null) {
                            $this->algorithmService->manageQS($json_data, $diag, $instance_node, $step, $consultation_nodes, $answers_hash_map, $qs_hash_map, $dependency_map, true);
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
                                $this->algorithmService->manageQS($json_data, $diag, $instance_node, $step, $consultation_nodes, $answers_hash_map, $qs_hash_map, $dependency_map, false, $answer_id);
                            }

                            $node = $json_data['full_nodes'][$node_id];
                            if ($node['type'] !== 'QuestionsSequence') {
                                $this->algorithmService->breadthFirstSearch($diag['instances'], $diag['id'], $node_id, $answer_id, $dependency_map, true);
                            } else {
                                if ($instance['final_diagnosis_id'] === null) {
                                    $this->algorithmService->manageQS($json_data, $diag, $node, $step, $consultation_nodes, $answers_hash_map, $qs_hash_map, $dependency_map, false, $answer_id);
                                }
                            }

                            foreach ($instance['children'] as $child_node_id) {
                                $child_node = $json_data['full_nodes'][$child_node_id] ?? null;
                                if ($child_node) {
                                    if ($child_node['type'] !== 'QuestionsSequence') {
                                        $this->algorithmService->breadthFirstSearch($diag['instances'], $diag['id'], $child_node_id, $answer_id, $dependency_map);
                                    } else {
                                        if ($instance['final_diagnosis_id'] === null) {
                                            $this->algorithmService->manageQS($json_data, $diag, $child_node, $step, $consultation_nodes, $answers_hash_map, $qs_hash_map, $dependency_map, empty($instance['conditions']));
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

        //todo actually stop calulating again if cache found. Create function in service and
        //cache get or cache create and get
        if (!$cache_found) {
            Cache::put($this->cache_key, [
                ...$json_data,
                'formula_hash_map' => $formula_hash_map,
                'max_path_length' => $max_path_length,
                'need_emergency' => $need_emergency,
                'female_gender_answer_id' => $female_gender_answer_id,
                'male_gender_answer_id' => $male_gender_answer_id,
            ]);
            $json_data = Cache::get($this->cache_key);
        }

        $this->medical_case = [
            'nodes' => [],
            'drugs' => [],
        ];

        $this->medical_case['nodes'] = $this->algorithmService->createMedicalCaseNodes($algorithm['nodes']);
        $this->manageRegistrationStep($json_data);

        $this->current_nodes['diagnoses'] = [
            'proposed' => [],
            'excluded' => [],
            'additional' => [],
            'agreed' => [],
            'refused' => [],
            'custom' => [],
        ];
        $this->last_system_updated = [
            'stage' => null,
            'step' => null,
            'system' => null,
        ];


        if ($this->algorithm_type === 'prevention') {
            $this->manageBasicMeasurement($json_data);
            $this->current_nodes['registration'] = array_replace(
                $this->current_nodes['registration'],
                $this->current_nodes['first_look_assessment']['basic_measurements_nodes_id'],
            );
            unset($this->current_nodes['registration']['first_name']);
            unset($this->current_nodes['registration']['last_name']);
        }

        //todo remove these when in prod
        //START TO REMOVE
        if ($this->algorithm_type !== 'dynamic') {
            $this->current_cc = $this->age_key === "older"
                ? $json_data['general_cc_id']
                : $json_data['yi_general_cc_id'];
        }

        if ($this->algorithm_type === 'prevention' && config('app.debug')) {
            $this->current_nodes['registration'][42321] = 43855;
            $this->current_nodes['registration']['birth_date'] = '1974-01-01';
            $this->chosen_complaint_categories = [];
            $this->df_to_display = [];
            $this->diagnoses_per_cc = [];
            $this->updatingCurrentNodesRegistrationBirthDate('1974-01-01');
            $this->manageBasicMeasurement($json_data);
            $this->current_nodes['registration'] = array_replace(
                $this->current_nodes['registration'],
                $this->current_nodes['first_look_assessment']['basic_measurements_nodes_id'],
            );
        }

        if ($this->id === 96 && config('app.debug')) {
            $this->current_nodes['registration']['birth_date'] = '2018-01-01';
            $this->updatingCurrentNodesRegistrationBirthDate('2018-01-01');
            $this->current_nodes['registration'][7852] = 6258; //male
            $this->current_nodes['first_look_assessment']['basic_measurements_nodes_id'][7805] = 40;
            $this->current_nodes['first_look_assessment']['basic_measurements_nodes_id'][7435] = 140;
            $this->current_nodes['first_look_assessment']['basic_measurements_nodes_id'][7471] = 20;
            $this->current_nodes['first_look_assessment']['basic_measurements_nodes_id'][7827] = 50;
            $this->updatingCurrentNodes(6258, 'registration.7852');
            $this->updatingCurrentNodes(50, 'first_look_assessment.basic_measurements_nodes_id.7827');
            $this->updatingCurrentNodes(20, 'first_look_assessment.basic_measurements_nodes_id.7471');
            $this->updatingCurrentNodes(140, 'first_look_assessment.basic_measurements_nodes_id.7435');
            $this->updatingCurrentNodes(40, 'first_look_assessment.basic_measurements_nodes_id.7805');
            $this->goToStep('consultation');
            $this->updatingCurrentNodes(6147, 'consultation.medical_history.general.7807');
            $this->updatingCurrentNodes(5641, 'consultation.medical_history.general.7539');
            $this->updatingCurrentNodes(6167, 'consultation.medical_history.respiratory_circulation.7817');
            $this->goToSubStep('consultation', 'physical_exam');
            $this->updatingCurrentNodes(6155, 'consultation.physical_exam.respiratory_circulation.7811');
            $this->updatingCurrentNodes(5, 'consultation.physical_exam.respiratory_circulation.8385');
            $this->goToStep('tests');
            // $this->goToSubStep('diagnoses', 'final_diagnoses');
        }
        //END TO REMOVE

        // If we are in training mode then we go directly to consultation step
        if ($this->algorithm_type === 'training') {
            $this->chosen_complaint_categories[$json_data['general_cc_id']] = true;
            $valid_diagnoses = $this->getValidPreventionDiagnoses($json_data);
            $this->diagnoses_per_cc = $valid_diagnoses;
            $this->saved_step = 2;
            $this->goToSubStep('consultation', 'medical_history');
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
                $this->current_nodes['registration'][$json_data['gender_question_id']] = $gender === 'female' ?
                    $json_data['female_gender_answer_id'] :
                    $json_data['male_gender_answer_id'];
                $this->current_nodes['registration'][$json_data['village_question_id']] = $city;
                $this->updatingCurrentNodesRegistrationBirthDate($date_of_birth);
                $this->calculateCompletionPercentage($json_data);
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
            $this->current_nodes['registration'][$json_data['gender_question_id']] = $gender === 'Female' ?
                $json_data['female_gender_answer_id'] :
                $json_data['male_gender_answer_id'];
            $this->current_nodes['registration'][$json_data['village_question_id']] = $city;
            $this->updatingCurrentNodesRegistrationBirthDate($date_of_birth);
            $this->calculateCompletionPercentage($json_data);
        }
    }

    private function calculateCompletionPercentage($json_data, $other_cc = null)
    {
        if ($this->current_step === 'diagnoses' || $this->current_step === 'tests') {
            return;
        }

        $max_path_length = $json_data['max_path_length'];
        $formula_hash_map = $json_data['formula_hash_map'];
        $total = 0;
        $current_nodes = [];
        // todo $current_nodes can contains background_calculation

        if ($this->current_step === 'registration') {
            $current_nodes = array_diff_key(
                $this->current_nodes[$this->current_step],
                $json_data['formula_hash_map']
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
                    $answers = array_keys($json_data['full_nodes'][$node_id]['answers']);
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

        if (
            isset($this->completion_per_substep[$other_cc ?? $this->current_cc])
            && $this->algorithm_type === 'prevention' && $this->current_step !== 'registration'
            && $this->current_step !== 'first_look_assessment'
        ) {
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

    public function updatingCurrentNodes($value, $key)
    {
        if ($this->algorithmService->isDate($value)) return;

        if (
            Str::of($key)->contains('first_name')
            || Str::of($key)->contains('last_name')
            || Str::of($key)->contains('drugs')
        ) return;

        if (Str::of($key)->contains('first_look_nodes_id')) {
            if ($value) {
                $this->dispatch('openEmergencyModal');
            }
        }

        $json_data = Cache::get($this->cache_key);
        $algorithm = $json_data['algorithm'];
        $need_emergency = $json_data['need_emergency'];
        $nodes = $algorithm['nodes'];
        $node_id = intval(Str::of($key)->explode('.')->last());
        Arr::set($this->current_nodes, $key, $value);

        // If the answer trigger the emergency modal
        if (array_key_exists($value, $need_emergency)) {
            $this->dispatch('openEmergencyModal');
        }

        $node = $nodes[$node_id];
        $mc_node = $this->medical_case['nodes'][$node_id];
        $new_nodes = [];

        // Set the new value to the current node
        $new_values = $this->setNodeValue($json_data, $mc_node, $node, $value);
        $new_nodes[$node_id] = array_replace($mc_node, $new_values);

        // Update question sequence
        $new_nodes = $this->updateQuestionSequence(
            $json_data,
            $node['id'],
            $new_nodes,
            $this->medical_case['nodes']
        );

        // Update related questions
        $new_nodes = $this->updateRelatedQuestion(
            $json_data,
            $node['id'],
            $new_nodes,
            $this->medical_case['nodes']
        );

        $this->last_system_updated = [
            'stage' => $this->current_sub_step,
            'step' => $this->current_step,
            'system' => $node['system'] ?? '',
        ];

        $this->medical_case['nodes'] = array_replace($this->medical_case['nodes'], $new_nodes);

        match ($this->current_step) {
            'consultation' => $this->current_sub_step === 'medical_history'
                ? $this->manageMedicalHistory($json_data)
                : $this->managePhysicalExam($json_data),
            'tests' => $this->manageTestStep($json_data),
            'diagnoses' => $this->manageDiagnosesStep($json_data),
            default => null,
        };
    }

    public function updatedDiagnosesStatus($value, $key)
    {
        $json_data = Cache::get($this->cache_key);
        $agreed = $this->current_nodes['diagnoses']['agreed'];
        $refused = $this->current_nodes['diagnoses']['refused'];
        $nodes = $json_data['algorithm']['nodes'];

        $is_in_agreed = array_key_exists($key, $agreed);
        $is_in_refused = in_array($key, $refused);

        // From null to Agree
        if ($value && !$is_in_agreed) {
            $current_node = $nodes[$key];
            $available_drugs = $this->getAvailableHealthcare($json_data, $current_node, 'drugs');
            $available_managements = $this->getAvailableHealthcare($json_data, $current_node, 'managements');

            $this->current_nodes['diagnoses']['agreed'][$key] = [
                'id' => $key,
                'managements' => $available_managements,
                'drugs' => [
                    'proposed' => $available_drugs,
                    'agreed' => [],
                    'refused' => [],
                    'additional' => [],
                    'custom' => [],
                ]
            ];

            // From Disagree to Agree
            if ($is_in_refused) {
                $refused_diagnoses = &$this->current_nodes['diagnoses']['refused'];
                $index = array_search(intval($key), $refused_diagnoses);
                if ($index !== false) {
                    unset($refused_diagnoses[$index]);
                    $refused_diagnoses = array_values($refused_diagnoses);
                }
            }
        }

        // From null to Disagree
        if (!$value && !$is_in_refused) {
            $this->current_nodes['diagnoses']['refused'][] = intval($key);

            // From Agree to Disagree
            if ($is_in_agreed) {
                unset($this->current_nodes['diagnoses']['agreed'][intval($key)]);
            }
        }

        $this->manageFinalDiagnose($json_data);
    }

    public function updatedDrugsStatus($value, $key)
    {
        $json_data = Cache::get($this->cache_key);
        $drug_id = intval($key);

        $diagnoses = array_filter($this->current_nodes['drugs']['calculated'], function ($v) use ($drug_id) {
            return intval($v['id']) === $drug_id;
        });

        foreach ($diagnoses as $diagnosis) {
            foreach ($diagnosis['diagnoses'] as $drug_diagnosis) {

                $diagnosis_id = $drug_diagnosis['id'];
                $diagnosis_key = $drug_diagnosis['key'];
                $diagnosis = $this->current_nodes['diagnoses']['agreed'][$diagnosis_id];

                $is_in_agreed = array_key_exists($drug_id, $diagnosis['drugs']['agreed']);
                $is_in_refused = in_array($drug_id, $diagnosis['drugs']['refused'] ?? []);

                // From null to Agree
                if ($value && !$is_in_agreed) {
                    $this->current_nodes['diagnoses'][$diagnosis_key][$diagnosis_id]['drugs']['agreed'][$drug_id] = [
                        'id' => $drug_id
                    ];

                    // From Disagree to Agree
                    if ($is_in_refused) {
                        $refused_drugs = &$this->current_nodes['diagnoses'][$diagnosis_key][$diagnosis_id]['drugs']['refused'];

                        $index = array_search($drug_id, $refused_drugs);

                        if ($index !== false) {
                            unset($refused_drugs[$index]);
                            $refused_drugs = array_values($refused_drugs);
                        }
                    }
                }

                // From null to Disagree
                if (!$value && !$is_in_refused) {
                    $this->current_nodes['diagnoses'][$diagnosis_key][$diagnosis_id]['drugs']['refused'][] = $drug_id;

                    // From Agree to Disagree
                    if ($is_in_agreed) {
                        unset($this->current_nodes['diagnoses'][$diagnosis_key][$diagnosis_id]['drugs']['agreed'][$drug_id]);
                    }
                }
            }
        }

        $this->manageDrugs($json_data);
    }

    public function updatedFormulations($value, $key)
    {
        $calculated_drugs = $this->current_nodes['drugs']['calculated'];
        $drug_id = intval($key);

        $drugs = array_filter($calculated_drugs, function ($v) use ($drug_id) {
            return intval($v['id']) === $drug_id;
        });

        if (empty($drugs)) {
            return;
        }

        foreach ($drugs as $drug) {
            foreach ($drug['diagnoses'] as $diagnosis) {
                $drug_key = $drug['key'] === 'proposed' ? 'agreed' : $drug['key'];
                $this->current_nodes['diagnoses'][$diagnosis['key']][$diagnosis['id']]['drugs'][$drug_key][$drug['id']]['formulation_id'] = $value;
            }
        }
    }

    public function updatingChosenComplaintCategories($value, int $modified_cc_id)
    {
        $json_data = Cache::get($this->cache_key);
        $nodes = $json_data['algorithm']['nodes'];

        if ($value) {
            $this->medical_case['nodes'][$modified_cc_id]['answer'] = $this->algorithmService->getYesAnswer($nodes[$modified_cc_id]);
        } else {
            $this->medical_case['nodes'][$modified_cc_id]['answer'] = $this->algorithmService->getNoAnswer($nodes[$modified_cc_id]);
        }
    }

    public function updatingCurrentNodesRegistrationBirthDate($birth_date)
    {
        $json_data = Cache::get($this->cache_key);
        $nodes = $json_data['full_nodes'];
        $birth_date_formulas = $json_data['birth_date_formulas'];
        $formula_hash_map = $json_data['formula_hash_map'];
        $general_cc_id = $json_data['general_cc_id'];
        $yi_general_cc_id = $json_data['yi_general_cc_id'];
        $older_ccs = $json_data['algorithm']['config']['full_order']['complaint_categories_step']['older'];
        $neonat_ccs = $json_data['algorithm']['config']['full_order']['complaint_categories_step']['neonat'];

        $this->current_nodes['registration']['birth_date'] = $birth_date;

        foreach ($birth_date_formulas as $node_id) {
            $value = null;

            // If the user reset the date of birth
            if ($birth_date !== null) {
                $formula = $formula_hash_map[$node_id] ?? null;
                //In this situation we just have to get the days/months/years
                if ($formula && ($formula === "ToDay" || $formula === "ToMonth" || $formula === "ToYear")) {
                    $dob = new DateTime($birth_date);
                    $interval = $this->created_at->diff($dob);

                    if ($formula === "ToDay") {
                        $days = $interval->format('%a');
                        if ($nodes[$node_id]['label']['en'] === 'Age in days') {
                            $this->age_in_days = $days;
                            if ($days <= 59) {
                                $this->current_cc = $yi_general_cc_id;
                                if ($this->algorithm_type === 'dynamic') {
                                    $this->chosen_complaint_categories[$yi_general_cc_id] = true;
                                    if (array_key_exists($general_cc_id, $this->chosen_complaint_categories)) {
                                        unset($this->chosen_complaint_categories[$general_cc_id]);
                                    }
                                }
                                $this->medical_case['nodes'][$yi_general_cc_id]['answer'] = $this->algorithmService->getYesAnswer($nodes[$yi_general_cc_id]);
                                foreach ($older_ccs as $older_cc_id) {
                                    $this->medical_case['nodes'][$older_cc_id]['answer'] = $this->algorithmService->getNoAnswer($nodes[$older_cc_id]);
                                }

                                if ($this->age_key === 'older' && isset($this->current_nodes['first_look_assessment']['complaint_categories_nodes_id'])) {
                                    unset($this->current_nodes['first_look_assessment']['complaint_categories_nodes_id']);
                                }
                                $this->age_key = 'neonat';
                            } else {
                                $this->current_cc = $general_cc_id;
                                if ($this->algorithm_type === 'dynamic') {
                                    $this->chosen_complaint_categories[$general_cc_id] = true;
                                    if (array_key_exists($yi_general_cc_id, $this->chosen_complaint_categories)) {
                                        unset($this->chosen_complaint_categories[$yi_general_cc_id]);
                                    }
                                }
                                $this->medical_case['nodes'][$general_cc_id]['answer'] = $this->algorithmService->getYesAnswer($nodes[$general_cc_id]);
                                foreach ($neonat_ccs as $neonat_cc_id) {
                                    $this->medical_case['nodes'][$neonat_cc_id]['answer'] = $this->algorithmService->getNoAnswer($nodes[$neonat_cc_id]);
                                }
                                if ($this->age_key === 'neonat' && isset($this->current_nodes['first_look_assessment']['complaint_categories_nodes_id'])) {
                                    unset($this->current_nodes['first_look_assessment']['complaint_categories_nodes_id']);
                                }
                                $this->age_key = 'older';
                            }
                        }
                        $value = $days;
                    } elseif ($formula === "ToMonth") {
                        $value = $interval->m + ($interval->y * 12);
                    } elseif ($formula === "ToYear") {
                        $value = $interval->y;
                    }
                }
            }

            $new_value = $this->handleNumeric($json_data, $this->medical_case['nodes'][$node_id], $nodes[$node_id], $value);

            $new_nodes[$node_id] = array_replace($this->medical_case['nodes'][$node_id], $new_value);
            $this->medical_case['nodes'][$node_id] = $new_nodes[$node_id];

            // Update related questions based on the new value
            $new_nodes = $this->updateRelatedQuestion(
                $json_data,
                $node_id,
                $new_nodes,
                $this->medical_case['nodes']
            );

            $this->medical_case['nodes'] = array_replace($this->medical_case['nodes'], $new_nodes);

            foreach ($new_nodes as $new_node_id) {
                if ($node_id !== $new_node_id && !empty($new_nodes[$new_node_id['id']]['label'])) {
                    $this->current_nodes['registration'][$new_node_id['id']] = $new_nodes[$new_node_id['id']]['label'];
                }
            }

            if (!empty($new_nodes[$node_id]['value'])) {
                $this->current_nodes['registration'][$node_id] = $new_nodes[$node_id]['value'];
            }
        }

        $this->manageComplaintCategory($json_data);

        $this->medical_case['nodes'] = array_replace($this->medical_case['nodes'], $new_nodes);
        return;
    }

    private function handleAnswers($json_data, $node_id, $value)
    {
        $answers = $json_data['full_nodes'][$node_id]['answers'];

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

                return [
                    'answer' => intval($answer['id']),
                    'label' => $label
                ];
            }
        }

        return ['answer' => null, 'label' => null];
    }

    private function calculateFormula($json_data, $node_id, $new_nodes)
    {
        $formula_hash_map = $json_data['formula_hash_map'];
        $full_nodes = $json_data['full_nodes'];
        $general_cc_id = $json_data['general_cc_id'];
        $yi_general_cc_id = $json_data['yi_general_cc_id'];
        $older_ccs = $json_data['algorithm']['config']['full_order']['complaint_categories_step']['older'];
        $neonat_ccs = $json_data['algorithm']['config']['full_order']['complaint_categories_step']['neonat'];

        $formula = $formula_hash_map[$node_id];

        //In this situation we just have to get the days/months/years
        if ($formula === "ToDay" || $formula === "ToMonth" || $formula === "ToYear") {
            $dob = new DateTime($this->current_nodes['registration']['birth_date']);
            $interval = $this->created_at->diff($dob);

            if ($formula === "ToDay") {
                $days = $interval->format('%a');
                //My eyes are burning....
                //But no other way as the Age in days node id is not saved anywhere
                if ($full_nodes[$node_id]['label']['en'] === 'Age in days') {
                    $this->age_in_days = $days;
                    if ($days <= 59) {
                        if ($this->algorithm_type === 'dynamic') {
                            $this->chosen_complaint_categories[$yi_general_cc_id] = true;
                            if (array_key_exists($general_cc_id, $this->chosen_complaint_categories)) {
                                unset($this->chosen_complaint_categories[$general_cc_id]);
                            }
                        }
                        $this->current_cc = $yi_general_cc_id;
                        $this->medical_case['nodes'][$yi_general_cc_id]['answer'] = $this->algorithmService->getYesAnswer($full_nodes[$yi_general_cc_id]);
                        foreach ($older_ccs as $older_cc_id) {
                            $this->medical_case['nodes'][$older_cc_id]['answer'] = $this->algorithmService->getNoAnswer($full_nodes[$older_cc_id]);
                        }

                        if (
                            $this->age_key === 'older'
                            && isset($this->current_nodes['first_look_assessment']['complaint_categories_nodes_id'])
                        ) {
                            unset($this->current_nodes['first_look_assessment']['complaint_categories_nodes_id']);
                        }
                        $this->age_key = 'neonat';
                    } else {
                        if ($this->algorithm_type === 'dynamic') {
                            $this->chosen_complaint_categories[$general_cc_id] = true;
                            if (array_key_exists($yi_general_cc_id, $this->chosen_complaint_categories)) {
                                unset($this->chosen_complaint_categories[$yi_general_cc_id]);
                            }
                        }
                        $this->current_cc = $general_cc_id;
                        $this->medical_case['nodes'][$general_cc_id]['answer'] = $this->algorithmService->getYesAnswer($full_nodes[$general_cc_id]);
                        foreach ($neonat_ccs as $neonat_cc_id) {
                            $this->medical_case['nodes'][$neonat_cc_id]['answer'] = $this->algorithmService->getNoAnswer($full_nodes[$neonat_cc_id]);
                        }
                        if (
                            $this->age_key === 'neonat'
                            && isset($this->current_nodes['first_look_assessment']['complaint_categories_nodes_id'])
                        ) {
                            unset($this->current_nodes['first_look_assessment']['complaint_categories_nodes_id']);
                        }
                        $this->age_key = 'older';
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
            $formula = preg_replace_callback('/\[(\d+)\]/', function ($matches) use ($new_nodes) {
                return $new_nodes[$matches[1]]['value'] ?? $this->medical_case['nodes'][$matches[1]]['value'];
            }, $formula);

            try {
                $result = round((new ExpressionLanguage())->evaluate($formula), 3);
            } catch (DivisionByZeroError $e) {
                return null;
            } catch (Exception $e) {
                return null;
            }
        }

        return $result;
    }

    private function getValidDiagnoses($json_data)
    {
        $algorithm = $json_data['algorithm'];
        $nodes = $algorithm['nodes'];
        $diagnoses = $algorithm['diagnoses'];
        $mc_nodes = $this->medical_case['nodes'];

        // Filter diagnoses based on the complaint category and cut-off dates
        return array_filter($diagnoses, function ($diagnosis) use ($nodes, $mc_nodes) {
            return (
                isset($mc_nodes[$diagnosis['complaint_category']])
                && $mc_nodes[$diagnosis['complaint_category']]['answer'] === $this->algorithmService->getYesAnswer($nodes[$diagnosis['complaint_category']])
                && $this->respectsCutOff($diagnosis)
            );
        });
    }

    private function getValidPreventionDiagnoses($json_data)
    {
        $algorithm = $json_data['algorithm'];
        $diagnoses = $algorithm['diagnoses'];

        $valid_dds = array_filter($diagnoses, function ($diagnosis) {
            return $this->respectsCutOff($diagnosis);
        });

        foreach ($valid_dds as $diagnose) {
            $dd_per_cc[$diagnose['complaint_category']][$diagnose['id']] = $diagnose;
        }

        return $dd_per_cc ?? [];
    }

    private function respectsCutOff($condition)
    {
        $cut_off_start = isset($condition['cut_off_start']) ? $condition['cut_off_start'] : null;
        $cut_off_end = isset($condition['cut_off_end']) ? $condition['cut_off_end'] : null;

        if ($this->algorithm_type === 'training') {
            return true;
        }

        if (!isset($this->age_in_days)) {
            return false;
        }

        if (is_null($cut_off_start) && is_null($cut_off_end)) {
            return true;
        }

        if (is_null($cut_off_start)) {
            return $cut_off_end > $this->age_in_days;
        }

        if (is_null($cut_off_end)) {
            return $cut_off_start <= $this->age_in_days;
        }

        return $cut_off_start <= $this->age_in_days && $cut_off_end > $this->age_in_days;
    }

    private function getTopConditions($instances, $is_final_diagnosis = false)
    {
        return array_filter($instances, function ($instance) use ($is_final_diagnosis) {
            return empty($instance['conditions']) && ($is_final_diagnosis || is_null($instance['final_diagnosis_id']));
        });
    }

    private function handleChildren(
        $json_data,
        $children,
        $source,
        &$questions_to_display,
        $instances,
        $categories,
        $diagram_id,
        $diagram_type,
        $system,
        &$current_systems,
        $system_order
    ) {
        $algorithm = $json_data['algorithm'];
        $nodes = $algorithm['nodes'];

        foreach ($children as $instance) {
            if (
                (!$this->excludedByCc($json_data, $instance['id']) && empty($instance['conditions'])) ||
                $this->calculateCondition($json_data, $instance, $source)
            ) {
                if ($nodes[$instance['id']]['type'] === config('medal.node_types.questions_sequence')) {
                    $top_conditions = $this->getTopConditions($nodes[$instance['id']]['instances']);
                    $this->handleChildren(
                        $json_data,
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
                            $json_data,
                            $instance['id'],
                            $questions_to_display,
                            $categories,
                            $current_systems,
                            $system_order
                        );
                    } else {
                        $this->addQuestion($json_data, $instance['id'], $questions_to_display, $categories);
                    }
                }

                $healthcare_categories = [
                    config('medal.categories.drug'),
                    config('medal.categories.management'),
                ];

                $children_instances_id = array_filter($instance['children'], function ($child_id) use ($nodes, $diagram_type, $diagram_id, $healthcare_categories) {
                    // Check if the condition for either a diagnosis or a question sequence is met
                    $is_valid_condition = (
                        (isset($nodes[$child_id]['type']) && $nodes[$child_id]['type'] !== config('medal.node_types.final_diagnosis')
                            && $diagram_type === config('medal.node_types.diagnosis')
                            || ($diagram_type === config('medal.node_types.questions_sequence') &&
                                $child_id !== $diagram_id
                            ))
                    );

                    // Ensure that the node's category is not in healthcare categories
                    return $is_valid_condition && !in_array($nodes[$child_id]['category'], $healthcare_categories);
                });

                foreach ($children_instances_id as $child_id) {
                    $children_instances[$child_id] = $instances[$child_id];
                }

                if (isset($children_instances) && !empty($children_instances)) {
                    $this->handleChildren(
                        $json_data,
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

    private function addQuestionToSystem(
        $json_data,
        $question_id,
        &$questions_to_display,
        $categories,
        &$current_systems,
        $system_order
    ) {
        $algorithm = $json_data['algorithm'];
        $nodes = $algorithm['nodes'];
        $mc_nodes = $this->medical_case['nodes'];

        if (in_array($nodes[$question_id]['category'], $categories)) {

            $old_system_index = array_search(
                $this->last_system_updated['system'] ?? null,
                array_column($system_order, 'title')
            );

            $current_question_system_index = array_search(
                $nodes[$question_id]['system'],
                array_column($system_order, 'title')
            );

            $visible_nodes = [];
            array_walk_recursive($current_systems, function ($value, $key) use (&$visible_nodes) {
                $visible_nodes[] = $key;
            });

            $is_already_displayed = in_array($question_id, $visible_nodes);
            $is_nodes_already_answered = $this->isNodeAnswered($visible_nodes, $mc_nodes);

            if (
                (!$is_already_displayed &&
                    $is_nodes_already_answered &&
                    $current_question_system_index < $old_system_index) ||
                in_array($question_id, $current_systems['follow_up_questions'] ?? [])
            ) {
                if (isset($questions_to_display['follow_up_questions'])) {
                    $questions_to_display['follow_up_questions'][] = $question_id;
                } else {
                    $questions_to_display['follow_up_questions'] = [$question_id];
                }
            } else {
                if ($this->algorithm_type === 'dynamic') {
                    if (isset($questions_to_display[$nodes[$question_id]['system']])) {
                        $questions_to_display[$nodes[$question_id]['system']][] = $question_id;
                    } else {
                        $questions_to_display[$nodes[$question_id]['system']] = [$question_id];
                    }
                } else {
                    if (isset($questions_to_display)) {
                        $questions_to_display[] = $question_id;
                    } else {
                        $questions_to_display = [$question_id];
                    }
                }
            }
        }
    }

    private function isNodeAnswered($visible_nodes, $mc_nodes)
    {
        foreach ($visible_nodes as $node_to_check_id) {
            $is_nodes_already_answered = $mc_nodes[$node_to_check_id]['answer'] !== null
                || $mc_nodes[$node_to_check_id]['value'] !== '';

            if ($is_nodes_already_answered) {
                return true;
            }
        }
        return false;
    }

    private function addQuestion($json_data, $question_id, &$questions_to_display, $categories)
    {
        $algorithm = $json_data['algorithm'];
        $nodes = $algorithm['nodes'];

        if ($this->algorithm_type !== 'training') {
            if (in_array($nodes[$question_id]['category'], $categories)) {
                $questions_to_display[] = $question_id;
            }
        } else {
            $questions_to_display[] = $question_id;
        }
    }

    private function excludedByCc($json_data, $question_id)
    {
        $algorithm = $json_data['algorithm'];
        $mc_nodes = $this->medical_case['nodes'];
        $nodes = $algorithm['nodes'];

        if ((isset($nodes[$question_id]['type'])
                && $nodes[$question_id]['type'] === config('medal.node_types.final_diagnosis')
            ) || $nodes[$question_id]['category'] === config('medal.categories.drug') ||
            $nodes[$question_id]['category'] === config('medal.categories.management')
        ) {
            return false;
        }

        if (empty($nodes[$question_id]['conditioned_by_cc'])) {
            return false;
        }

        foreach ($nodes[$question_id]['conditioned_by_cc'] as $cc_id) {
            if ($mc_nodes[$cc_id]['answer'] === $this->algorithmService->getNoAnswer($nodes[$cc_id])) {
                return true;
            }
        }

        return false;
    }

    private function calculateCondition($json_data, $instance, $source_id = null, $new_nodes = [])
    {
        $mc_nodes = array_replace($this->medical_case['nodes'], $new_nodes);

        if ($this->excludedByCc($json_data, $instance['id'])) {
            return false;
        }

        if (empty($instance['conditions'])) {
            return true;
        }

        foreach ($instance['conditions'] as $condition) {
            if ($source_id !== null && $condition['node_id'] !== $source_id) {
                continue;
            }

            if (
                isset($mc_nodes[$condition['node_id']]) &&
                $mc_nodes[$condition['node_id']]['answer'] === $condition['answer_id'] &&
                $this->respectsCutOff($condition)
            ) {
                return true;
            }
        }

        return false;
    }

    private function getQsValue($json_data, $nodes, $qs_id, $new_mc_nodes)
    {
        if ($nodes[$qs_id]['category'] === config('medal.categories.scored')) {
            return $this->scoredCalculateCondition($json_data, $qs_id, $new_mc_nodes);
        } else {
            $conditions_values = array_map(function ($condition) use ($json_data, $qs_id, $new_mc_nodes, $nodes) {
                if ($new_mc_nodes[$condition['node_id']]['answer'] === $condition['answer_id'] && $this->respectsCutOff($condition)) {
                    return $this->qsInstanceValue(
                        $json_data,
                        $nodes[$qs_id]['instances'][$condition['node_id']],
                        $new_mc_nodes,
                        $nodes[$qs_id]['instances'],
                        $qs_id
                    );
                } else {
                    return false;
                }
            }, $nodes[$qs_id]['conditions']);

            return $this->reduceConditions($conditions_values);
        }
    }

    private function qsInstanceValue($json_data, $instance, $new_mc_nodes, $instances, $qs_id)
    {
        $mc_node = $new_mc_nodes[$instance['id']];
        $instance_condition = $this->calculateCondition($json_data, $instance, null, $new_mc_nodes);

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
                $parents_condition = array_map(function ($parent) use ($json_data, $instances, $new_mc_nodes, $qs_id) {
                    return $this->qsInstanceValue($json_data, $instances[$parent['node_id']], $new_mc_nodes, $instances, $qs_id);
                }, $parents);

                return $this->reduceConditions($parents_condition);
            }
        } else {
            return false;
        }
    }

    private function scoredCalculateCondition($json_data, $qs_id, $new_mc_nodes)
    {
        $algorithm = $json_data['algorithm'];

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

    private function reduceConditions($conditions_values)
    {
        return array_reduce($conditions_values, function ($result, $value) {
            return $this->comparingBooleanOr($result, $value);
        }, false);
    }

    private function diagramConditionsValues($json_data, $node_id, $instance, $mc_nodes)
    {

        $algorithm = $json_data['algorithm'];
        $nodes = $algorithm['nodes'];

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

    private function calculateConditionInverse($json_data, $conditions, $mc_nodes)
    {
        $algorithm = $json_data['algorithm'];
        $instances = $algorithm['diagram']['instances'];

        if (empty($conditions)) {
            return true;
        }

        foreach ($conditions as $condition) {

            $condition_value = $mc_nodes[$condition['node_id']]['answer'] === $condition['answer_id']
                && $this->respectsCutOff($condition);

            if ($condition_value) {
                if ($this->calculateConditionInverse(
                    $json_data,
                    $instances[$condition['node_id']]['conditions'],
                    $mc_nodes
                )) {
                    return true;
                }
            }
        }
        return false;
    }

    private function updateQuestionSequence($json_data, $node_id, $new_nodes, $mc_nodes)
    {
        $algorithm = $json_data['algorithm'];
        $nodes = $algorithm['nodes'];

        // List of QS we need to update
        $qs_to_update = $nodes[$node_id]['qs'];

        while (count($qs_to_update) > 0) {
            $qs_id = $qs_to_update[0];
            $qs_boolean_value = $this->getQsValue($json_data, $nodes, $qs_id, array_replace($mc_nodes, $new_nodes));

            // If the QS has a value
            if (!is_null($qs_boolean_value)) {
                $qs_value = $qs_boolean_value ?
                    $this->algorithmService->getYesAnswer($nodes[$qs_id]) : $this->algorithmService->getNoAnswer($nodes[$qs_id]);

                $new_qs_values = $this->handleAnswerId($nodes[$qs_id], $qs_value);

                // Set the QS value in the store
                $new_nodes[$qs_id] = array_replace($mc_nodes[$qs_id], $new_qs_values);
            } else {
                $new_nodes[$qs_id] = array_replace($mc_nodes[$qs_id], ['value' => null, 'answer' => null]);
            }

            // Add the related QS to the QS processing list
            $new_qs_list = array_filter($nodes[$qs_id]['qs'], function ($child_id) use ($json_data) {
                return !$this->excludedByCC($json_data, $child_id);
            });

            $qs_to_update = array_merge($qs_to_update, $new_qs_list);

            // uniq to avoid processing the same QS multiple times
            $qs_to_update = array_unique(array_slice($qs_to_update, 1));
        }

        return $new_nodes;
    }

    private function calculateReference($json_data, $node_id, $mc_nodes)
    {
        $algo_nodes = $json_data['algorithm']['nodes'];
        $gender_question_id = $json_data['gender_question_id'];
        $female_gender_answer_id = $json_data['female_gender_answer_id'];

        $nodes['current'] = $algo_nodes[$node_id];
        // Get X and Y
        $reference_table_x_id = $nodes['current']['reference_table_x_id'];
        $reference_table_y_id = $nodes['current']['reference_table_y_id'];

        if ($mc_nodes[$reference_table_x_id]['value'] && $mc_nodes[$reference_table_y_id]['value']) {
            $nodes['x'] = $algo_nodes[$reference_table_x_id];
            $nodes['y'] = $algo_nodes[$reference_table_y_id];
            $nodes['x']['value'] = $mc_nodes[$reference_table_x_id]['value'];
            $nodes['y']['value'] = $mc_nodes[$reference_table_y_id]['value'];

            // Get Z
            if ($nodes['current']['reference_table_z_id'] !== null) {
                $reference_table_z_id = $nodes['current']['reference_table_z_id'];
                $nodes['z'] = $algo_nodes[$reference_table_z_id];
                $nodes['z']['value'] = $this->medical_case['nodes'][$reference_table_z_id]['value'];
            }

            $gender = $this->current_nodes['registration'][$gender_question_id] === $female_gender_answer_id ? 'female' : 'male';

            return $this->referenceCalculator->calculateReference($node_id, $nodes, $gender, $this->cache_key);
        }

        return null;
    }

    private function updateRelatedQuestion($json_data, $node_id, $new_nodes, $mc_nodes)
    {
        $algorithm = $json_data['algorithm'];
        $nodes = $algorithm['nodes'];

        // List of formulas/reference tables we need to update
        $questions_to_update = $nodes[$node_id]['referenced_in'];

        while (count($questions_to_update) > 0) {
            $question_id = $questions_to_update[0];
            $node = $nodes[$question_id];
            $mc_node = $mc_nodes[$question_id];

            $value = null;

            // Determine if the node is a formula or reference table
            if ($node['display_format'] === config('medal.display_format.formula')) {
                $value = $this->calculateFormula($json_data, $question_id, $new_nodes);
            } else {
                $value = $this->calculateReference($json_data, $question_id, array_replace($mc_nodes, $new_nodes));
            }

            // Perform validation
            $validation = $this->questionValidationService($mc_node, $node, $value);

            $new_question_values = $this->handleNumeric($json_data, $mc_node, $node, $value);
            // Set the question value in the store
            $new_nodes[$question_id] = array_replace($mc_nodes[$question_id], $new_question_values);

            // Add the related questions to the update list
            $questions_to_update = array_merge($questions_to_update, $node['referenced_in']);

            // Remove duplicates and process the next question in the list
            $new_nodes = $this->updateQuestionSequence($json_data, $question_id, $new_nodes, $mc_nodes);
            $questions_to_update = array_unique(array_slice($questions_to_update, 1));
        }

        return $new_nodes;
    }

    private function questionValidationService($mc_node, $node, $value)
    {
        $validation_message = null;
        $validation_type = null;

        // Retrieve the unavailable answer if it exists
        $unavailable_answer = collect($node['answers'])->firstWhere('value', 'not_available');

        // Skip validation if answer is set as unavailable
        if (isset($mc_node['unavailable_value']) || $value === $unavailable_answer['id']) {
            return [
                'validation_message' => $validation_message,
                'validation_type' => $validation_type
            ];
        }

        // Validate only integer and float questions
        if (in_array($node['value_format'], [config('medal.value_formats.int'), config('medal.value_formats.float')])) {
            $formatted_value = floatval($value);

            if (
                $value !== null &&
                ($formatted_value < $node['min_value_warning'] ||
                    $formatted_value > $node['max_value_warning'] ||
                    $formatted_value < $node['min_value_error'] ||
                    $formatted_value > $node['max_value_error'])
            ) {
                // Warning
                if ($formatted_value < $node['min_value_warning'] && $node['min_value_warning'] !== null) {
                    $validation_message = $node['min_message_warning'];
                }

                if ($formatted_value > $node['max_value_warning'] && $node['max_value_warning'] !== null) {
                    $validation_message = $node['max_message_warning'];
                }

                $validation_type = 'warning';

                // Error
                if ($node['min_value_error'] !== null || $node['max_value_error'] !== null) {
                    if ($formatted_value < $node['min_value_error'] || $formatted_value > $node['max_value_error']) {
                        if ($formatted_value < $node['min_value_error'] && $node['min_value_error'] !== null) {
                            $validation_message = $node['min_message_error'];
                        }

                        if ($formatted_value > $node['max_value_error'] && $node['max_value_error'] !== null) {
                            $validation_message = $node['max_message_error'];
                        }
                        $validation_type = 'error';
                    }
                }
                return [
                    // 'validation_message' => $this->translate($validation_message),
                    'validation_message' => $validation_message['en'],
                    'validation_type' => $validation_type
                ];
            }
        }

        return [
            'validation_message' => $validation_message,
            'validation_type' => $validation_type
        ];
    }

    private function manageRegistrationStep($json_data)
    {
        $algorithm = $json_data['algorithm'];
        $registration_order = $algorithm['config']['full_order']['registration_step'];
        $instances = $algorithm['diagram']['instances'];
        $mc_nodes = $this->medical_case['nodes'];

        $registration_nodes = array_fill_keys(
            array_filter(
                $registration_order,
                function ($node_id) use ($json_data, $instances, $mc_nodes) {
                    return $this->calculateConditionInverse($json_data, $instances[$node_id]['conditions'] ?? [], $mc_nodes);
                }
            ),
            ''
        );

        if ($this->algorithm_type === 'prevention') {
            unset($registration_nodes['first_name']);
            unset($registration_nodes['last_name']);
        }

        $this->current_nodes['registration'] = array_replace(
            $registration_nodes,
            $this->current_nodes['registration'] ?? []
        );
    }

    private function manageFirstLookAssessmentStep($json_data)
    {
        $this->manageVitalSign($json_data);
        $this->manageComplaintCategory($json_data);
        $this->manageBasicMeasurement($json_data);
    }

    private function manageVitalSign($json_data)
    {
        $algorithm = $json_data['algorithm'];
        $first_look_assessment_order = $algorithm['config']['full_order']['first_look_assessment_step'];
        $instances = $algorithm['diagram']['instances'];
        $mc_nodes = $this->medical_case['nodes'];

        // Filter the first look assessment order based on the condition inverse calculation
        if (!isset($this->current_nodes['first_look_assessment']['first_look_nodes_id'])) {
            $this->current_nodes['first_look_assessment']['first_look_nodes_id'] = array_fill_keys(
                array_filter(
                    $first_look_assessment_order,
                    function ($node_id) use ($json_data, $instances, $mc_nodes) {
                        return $this->calculateConditionInverse($json_data, $instances[$node_id]['conditions'] ?? [], $mc_nodes);
                    }
                ),
                ''
            );
        }
    }

    private function manageComplaintCategory($json_data)
    {
        $algorithm = $json_data['algorithm'];
        $config = $algorithm['config'];
        $full_order = $config['full_order'];
        $basic_questions = $config['basic_questions'];

        // Get CC order (older and neonate) and general cc id for neonat and general
        $older_cc = $full_order['complaint_categories_step']['older'];
        $neonat_cc = $full_order['complaint_categories_step']['neonat'];
        $older_general_id = $basic_questions['general_cc_id'];
        $neonat_general_id = $basic_questions['yi_general_cc_id'];
        $instances = $algorithm['diagram']['instances'];
        $mc_nodes = $this->medical_case['nodes'];

        if (!isset($this->current_nodes['first_look_assessment']['complaint_categories_nodes_id'])) {
            if ($this->age_in_days <= 59) {
                $this->current_nodes['first_look_assessment']['complaint_categories_nodes_id'] = array_fill_keys(
                    array_filter(
                        $neonat_cc,
                        function ($node_id) use ($json_data, $neonat_general_id, $instances, $mc_nodes) {
                            return $node_id !== $neonat_general_id &&
                                $this->calculateConditionInverse(
                                    $json_data,
                                    $instances[$node_id]['conditions'] ?? [],
                                    $mc_nodes
                                );
                        }
                    ),
                    ''
                );
            } else {
                $this->current_nodes['first_look_assessment']['complaint_categories_nodes_id'] = array_fill_keys(
                    array_filter(
                        $older_cc,
                        function ($node_id) use ($json_data, $older_general_id, $instances, $mc_nodes) {
                            return $node_id !== $older_general_id &&
                                $this->calculateConditionInverse(
                                    $json_data,
                                    $instances[$node_id]['conditions'] ?? [],
                                    $mc_nodes
                                );
                        }
                    ),
                    ''
                );
            }
        }
    }

    private function manageBasicMeasurement($json_data)
    {
        $algorithm = $json_data['algorithm'];
        $order = $algorithm['config']['full_order']['basic_measurements_step'];
        $nodes = $algorithm['nodes'];
        $mc_nodes = $this->medical_case['nodes'];
        $instances = $algorithm['diagram']['instances'];

        $nodes = array_fill_keys(array_filter($order, function ($question_id) use ($json_data, $nodes, $mc_nodes, $instances) {
            // Check if the question is conditioned by any complaint category (CC)
            if (!empty($nodes[$question_id]['conditioned_by_cc'])) {
                // If one of the CCs is true, we need to exclude the question
                $exclude = array_filter($nodes[$question_id]['conditioned_by_cc'], function ($cc_id) use ($mc_nodes, $nodes) {
                    return $mc_nodes[$cc_id]['answer'] === $this->algorithmService->getYesAnswer($nodes[$cc_id]);
                });

                return !empty($exclude) && $this->calculateConditionInverse(
                    $json_data,
                    $instances[$question_id]['conditions'] ?? [],
                    $mc_nodes
                );
            }

            return $this->calculateConditionInverse($json_data, $instances[$question_id]['conditions'] ?? [], $mc_nodes);
        }), '');

        $this->current_nodes['first_look_assessment']['basic_measurements_nodes_id'] = array_replace(
            $nodes,
            $this->current_nodes['first_look_assessment']['basic_measurements_nodes_id'] ?? [],
        );
    }

    private function manageConsultationStep($json_data)
    {
        if ($this->current_sub_step === 'medical_history') {
            $this->manageMedicalHistory($json_data);
        }
        if ($this->current_sub_step === 'physical_exam') {
            $this->managePhysicalExam($json_data);
        }

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

    private function manageMedicalHistory($json_data)
    {
        if ($this->algorithm_type === 'prevention') {
            return $this->managePreventionMedicalHistory($json_data);
        }

        if ($this->algorithm_type === 'training') {
            return $this->manageTrainingMedicalHistory($json_data);
        }

        $algorithm = $json_data['algorithm'];

        $question_per_systems = [];

        $medical_history_categories = [
            config('medal.categories.symptom'),
            config('medal.categories.exposure'),
            config('medal.categories.chronic_condition'),
            config('medal.categories.vaccine'),
            config('medal.categories.observed_physical_sign'),
        ];
        $instances = $algorithm['diagram']['instances'];
        $medical_history_step = $algorithm['config']['full_order']['medical_history_step'];
        $current_systems = $this->current_nodes['consultation']['medical_history'] ?? [];
        $mc_nodes = $this->medical_case['nodes'];

        $valid_diagnoses = $this->getValidDiagnoses($json_data);

        foreach ($valid_diagnoses as $diagnosis) {
            $top_conditions = $this->getTopConditions($diagnosis['instances']);
            $this->handleChildren(
                $json_data,
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
            $new_questions = array_fill_keys(array_filter(
                $system['data'],
                function ($question_id) use ($json_data, $question_per_systems, $system, $instances, $mc_nodes) {
                    return in_array($question_id, $question_per_systems[$system['title']] ?? []) &&
                        $this->calculateConditionInverse($json_data, $instances[$question_id]['conditions'] ?? [], $mc_nodes);
                }
            ), '');

            foreach ($new_questions as $key => $v) {
                if (array_key_exists($key, $current_systems[$system['title']] ?? [])) {
                    $new_questions[$key] = $current_systems[$system['title']][$key];
                }
            }

            $updated_systems[$system['title']] = $new_questions;
            $updated_systems[$system['title']] = array_intersect_key($new_questions, $updated_systems[$system['title']]);
        }
        $updated_systems['follow_up_questions'] = array_unique($question_per_systems['follow_up_questions'] ?? []);

        $this->current_nodes['consultation']['medical_history'] = array_replace(
            $this->current_nodes['consultation']['medical_history'] ?? [],
            $updated_systems,
        );
    }

    private function managePreventionMedicalHistory($json_data)
    {
        $algorithm = $json_data['algorithm'];

        $question_per_systems = [];

        $medical_history_categories = [
            config('medal.categories.symptom'),
            config('medal.categories.exposure'),
            config('medal.categories.chronic_condition'),
            config('medal.categories.vaccine'),
            config('medal.categories.observed_physical_sign'),
        ];
        $instances = $algorithm['diagram']['instances'];
        $medical_history_step = $algorithm['config']['full_order']['medical_history_step'];
        $cc_order = $algorithm['config']['full_order']['complaint_categories_step'][$this->age_key];
        $current_systems = $this->current_nodes['consultation']['medical_history'] ?? [];
        $mc_nodes = $this->medical_case['nodes'];

        $valid_diagnoses = $this->getValidPreventionDiagnoses($json_data);
        $this->diagnoses_per_cc = $valid_diagnoses;

        if (empty($valid_diagnoses)) {
            flash()->addError('There is no recommendation for this age range');
            return;
        }

        foreach ($valid_diagnoses as $cc_id => $diagnosis_per_cc) {
            foreach ($diagnosis_per_cc as $diagnosis) {
                $top_conditions = $this->getTopConditions($diagnosis['instances']);

                $this->handleChildren(
                    $json_data,
                    $top_conditions,
                    null,
                    $question_per_systems[$cc_id],
                    $diagnosis['instances'],
                    $medical_history_categories,
                    $diagnosis['id'],
                    config('medal.node_types.diagnosis'),
                    true,
                    $current_systems,
                    $medical_history_step
                );
            }
        }

        $ccs = [
            ...$algorithm['config']['full_order']['complaint_categories_step']['older'],
            ...$algorithm['config']['full_order']['complaint_categories_step']['neonat'],
        ];

        $cc_order = array_flip($ccs);

        // Respect the order in the complaint_categories_step key
        if (!$this->algorithm_type !== 'dynamic') {
            uksort($this->chosen_complaint_categories, function ($a, $b) use ($cc_order) {
                return $cc_order[$a] <=> $cc_order[$b];
            });
        }

        $updated_systems = [];
        foreach ($medical_history_step as $system) {
            if ($system['title'] !== 'general') {
                continue;
            }
            foreach (array_filter($this->chosen_complaint_categories) as $cc_id => $accepted) {
                $new_questions = array_fill_keys(array_filter(
                    $system['data'],
                    function ($question_id) use ($json_data, $question_per_systems, $cc_id, $instances, $mc_nodes) {
                        return in_array($question_id, $question_per_systems[$cc_id] ?? []) &&
                            $this->calculateConditionInverse($json_data, $instances[$question_id]['conditions'] ?? [], $mc_nodes);
                    }
                ), '');

                foreach ($new_questions as $key => $v) {
                    if (array_key_exists($key, $current_systems[$cc_id] ?? [])) {
                        $new_questions[$key] = $current_systems[$cc_id][$key];
                    }
                }

                if (!empty($new_questions)) {
                    $updated_systems[$cc_id] = $new_questions;
                }
            }
        }

        foreach ($updated_systems as $cc_id_to_check => $nodes_per_cc) {
            foreach ($nodes_per_cc as $node_id => $value) {
                if (isset($already_displayed[$node_id])) {
                    unset($updated_systems[$cc_id_to_check][$node_id]);
                }
                $already_displayed[$node_id] = true;
            }
        }

        $this->current_nodes['consultation']['medical_history'] = array_replace(
            $this->current_nodes['consultation']['medical_history'] ?? [],
            $updated_systems,
        );
    }

    private function manageTrainingMedicalHistory($json_data)
    {
        $algorithm = $json_data['algorithm'];

        $question_per_systems = [];

        $medical_history_categories = [
            config('medal.categories.symptom'),
            config('medal.categories.exposure'),
            config('medal.categories.chronic_condition'),
            config('medal.categories.vaccine'),
            config('medal.categories.observed_physical_sign'),
        ];
        $instances = $algorithm['diagram']['instances'];
        $medical_history_step = $algorithm['config']['full_order']['medical_history_step'];

        $current_systems = $this->current_nodes['consultation']['medical_history'] ?? [];
        $mc_nodes = $this->medical_case['nodes'];

        $valid_diagnoses = $this->getValidPreventionDiagnoses($json_data);

        $this->diagnoses_per_cc = $valid_diagnoses;

        if (empty($valid_diagnoses)) {
            flash()->addError('There is no recommendation for this age range');
            return;
        }

        foreach ($valid_diagnoses as $cc_id => $diagnosis_per_cc) {
            foreach ($diagnosis_per_cc as $diagnosis) {
                $top_conditions = $this->getTopConditions($diagnosis['instances']);

                $this->handleChildren(
                    $json_data,
                    $top_conditions,
                    null,
                    $question_per_systems[$cc_id],
                    $diagnosis['instances'],
                    $medical_history_categories,
                    $diagnosis['id'],
                    config('medal.node_types.diagnosis'),
                    false,
                    $current_systems,
                    $medical_history_step
                );
            }
        }

        $updated_systems = [];
        foreach ($medical_history_step as $system) {
            foreach (array_filter($this->chosen_complaint_categories) as $cc_id => $accepted) {
                $new_questions = array_fill_keys(array_filter(
                    $system['data'],
                    function ($question_id) use ($json_data, $question_per_systems, $cc_id, $instances, $mc_nodes) {
                        return in_array($question_id, $question_per_systems[$cc_id] ?? []) &&
                            $this->calculateConditionInverse($json_data, $instances[$question_id]['conditions'] ?? [], $mc_nodes);
                    }
                ), '');
                $new_questions[6639] = '';
                foreach ($new_questions as $key => $v) {
                    if (array_key_exists($key, $current_systems[$cc_id] ?? [])) {
                        $new_questions[$key] = $current_systems[$cc_id][$key];
                    }
                }
                if (!empty($new_questions)) {
                    $updated_systems[$cc_id] = array_replace($updated_systems[$cc_id] ?? [], $new_questions);
                }
            }
        }

        foreach (array_filter($this->chosen_complaint_categories) as $cc_id => $accepted) {
            $this->current_nodes['consultation']['medical_history'][$cc_id] = array_replace(
                $this->current_nodes['consultation']['medical_history'][$cc_id] ?? [],
                $updated_systems[$cc_id],
            );
        }
    }

    private function managePhysicalExam($json_data)
    {
        $algorithm = $json_data['algorithm'];
        $question_per_systems = [];
        $physical_exam_categories = [
            config('medal.categories.physical_exam'),
            config('medal.categories.vital_sign_anthropometric')
        ];
        $instances = $algorithm['diagram']['instances'];
        $physical_exam_step = $algorithm['config']['full_order']['physical_exam_step'];
        $current_systems =  $this->current_nodes['consultation']['physical_exam'] ?? [];
        $mc_nodes = $this->medical_case['nodes'];

        $valid_diagnoses = $this->getValidDiagnoses($json_data);

        foreach ($valid_diagnoses as $diagnosis) {
            $top_conditions = $this->getTopConditions($diagnosis['instances']);

            $this->handleChildren(
                $json_data,
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

        $updated_systems = [];
        foreach ($physical_exam_step as $system) {
            $new_questions = array_fill_keys(array_filter(
                $system['data'],
                function ($question_id) use ($json_data, $question_per_systems, $system, $instances, $mc_nodes) {
                    return in_array($question_id, $question_per_systems[$system['title']] ?? []) &&
                        $this->calculateConditionInverse($json_data, $instances[$question_id]['conditions'] ?? [], $mc_nodes);
                }
            ), '');

            foreach ($new_questions as $key => $v) {
                if (array_key_exists($key, $current_systems[$system['title']] ?? [])) {
                    $new_questions[$key] = $current_systems[$system['title']][$key];
                }
            }
            $updated_systems[$system['title']] = $new_questions;
            // $updated_systems[$system['title']] = array_intersect_key($updated_systems[$system['title']], $new_questions);
            $updated_systems[$system['title']] = array_intersect_key($new_questions, $updated_systems[$system['title']]);
        }


        $updated_systems['follow_up_questions'] = array_unique($question_per_systems['follow_up_questions'] ?? []);;

        $this->current_nodes['consultation']['physical_exam'] = array_replace(
            $this->current_nodes['consultation']['physical_exam'] ?? [],
            $updated_systems,
        );
    }

    private function manageTestStep($json_data)
    {
        $algorithm = $json_data['algorithm'];
        $current_systems =  $this->current_nodes['consultation']['tests'] ?? [];
        $assessment_categories = [config('medal.categories.assessment')];
        $assessment_step = $algorithm['config']['full_order']['test_step'];

        $valid_diagnoses = $this->getValidDiagnoses($json_data);
        $questions_to_display = [];

        foreach ($valid_diagnoses as $diagnosis) {
            $top_conditions = $this->getTopConditions($diagnosis['instances']);

            $this->handleChildren(
                $json_data,
                $top_conditions,
                null,
                $questions_to_display,
                $diagnosis['instances'],
                $assessment_categories,
                $diagnosis['id'],
                config('medal.node_types.diagnosis'),
                false,
                $current_systems,
                []
            );
        }

        $tests_nodes = array_fill_keys(array_filter($assessment_step, function ($question) use ($questions_to_display) {
            return in_array($question, $questions_to_display);
        }), '');

        foreach ($tests_nodes as $node_id => $v) {
            if (array_key_exists($node_id, $this->current_nodes['tests'] ?? [])) {
                $tests_nodes[$node_id] = $this->current_nodes['tests'][$node_id];
            }
        }

        $this->current_nodes['tests'] = $tests_nodes;
        $this->current_nodes['tests'] = array_intersect_key($tests_nodes, $this->current_nodes['tests']);

        $this->current_nodes['tests'] = array_replace(
            $this->current_nodes['tests'] ?? [],
            $tests_nodes
        );
    }

    private function manageDiagnosesStep($json_data)
    {
        if ($this->current_sub_step === 'final_diagnoses') {
            $this->manageFinalDiagnose($json_data);
        }
        if ($this->current_sub_step === 'treatment_questions') {
            $this->manageTreatment($json_data);
        }
        if ($this->current_sub_step === 'medicines') {
            $this->manageDrugs($json_data);
        }
        if ($this->current_sub_step === 'summary') {
            $this->manageSummary($json_data);
        }
        if ($this->current_sub_step === 'referral') {
            $this->manageReferral($json_data);
        }
    }

    private function manageFinalDiagnose($json_data)
    {
        $algorithm = $json_data['algorithm'];
        $mc_diagnosis = $this->current_nodes['diagnoses'];

        $diagnoses = $algorithm['diagnoses'];
        $nodes = $algorithm['nodes'];
        $valid_diagnoses = $this->getValidDiagnoses($json_data);
        if ($this->algorithm_type === 'training') {
            $valid_diagnoses = $this->getValidPreventionDiagnoses($json_data)[$json_data['general_cc_id']];
        }

        $valid_diagnoses_ids = array_map(fn($diagnosis) => $diagnosis['id'], $valid_diagnoses);
        // Exclude diagnoses based on cut off and complaint category exclusion
        $mc_diagnosis['excluded'] = array_merge(
            ...array_map(function ($diagnosis) use ($valid_diagnoses_ids) {
                if (!in_array($diagnosis['id'], $valid_diagnoses_ids)) {
                    return array_map(fn($final_diagnosis) => $final_diagnosis['id'], $diagnosis['final_diagnoses']);
                }
                return [];
            }, $diagnoses)
        );

        // Find all the included diagnoses
        $mc_diagnosis['proposed'] = array_merge(
            ...array_map(function ($diagnosis) use ($json_data) {
                return $this->findProposedFinalDiagnoses($json_data, $diagnosis);
            }, $valid_diagnoses)
        );

        $mc_diagnosis['proposed'] = array_filter(
            $mc_diagnosis['proposed'],
            function ($final_diagnosis_id) use (&$mc_diagnosis, $nodes) {
                return !$nodes[$final_diagnosis_id]['excluding_final_diagnoses']
                    || !array_reduce(
                        $nodes[$final_diagnosis_id]['excluding_final_diagnoses'],
                        function ($carry, $excluding_final_diagnosis_id) use (&$mc_diagnosis, $final_diagnosis_id) {
                            if (in_array(
                                $excluding_final_diagnosis_id,
                                array_map(fn($a) => $a['id'], $mc_diagnosis['agreed'])
                            )) {
                                $mc_diagnosis['excluded'][] = $final_diagnosis_id;
                            }
                            return $carry || (
                                in_array($excluding_final_diagnosis_id, $mc_diagnosis['proposed'])
                                && !in_array($excluding_final_diagnosis_id, $mc_diagnosis['refused'])
                            );
                        },
                        false
                    );
            }
        );

        // Remove agreed diagnosis if it is no longer proposed
        foreach ($mc_diagnosis['agreed'] ?? [] as $key => $diagnosis) {
            if (!in_array($diagnosis['id'], $mc_diagnosis['proposed'])) {
                unset($mc_diagnosis['agreed'][$key]);
            }
        }


        $excluded_diagnoses = array_replace(...array_map(function ($diagnosis) use ($json_data) {
            return array_filter(
                $this->findExcludedFinalDiagnoses($json_data, $diagnosis),
                fn($final_diagnosis) => $final_diagnosis['value'] === false
            );
        }, $valid_diagnoses));

        $mc_diagnosis['excluded'] = array_replace(
            $mc_diagnosis['excluded'],
            array_map(fn($final_diagnosis) => $final_diagnosis['id'], $excluded_diagnoses)
        );

        foreach ($this->current_nodes['diagnoses'] as $dd_type => $dds) {
            $this->current_nodes['diagnoses'][$dd_type] = $mc_diagnosis[$dd_type];
        }

        uasort($this->current_nodes['diagnoses']['proposed'], function ($a, $b) use ($nodes) {
            // First, sort by level_of_urgency in descending order
            $level_urgency_comparison = $nodes[$b]['level_of_urgency'] <=> $nodes[$a]['level_of_urgency'];

            // If level_of_urgency is the same, sort by their original order in the proposed array (ascending)
            if ($level_urgency_comparison === 0) {
                $a_index = array_search($a, $this->current_nodes['diagnoses']['proposed']);
                $b_index = array_search($b, $this->current_nodes['diagnoses']['proposed']);

                return $a_index <=> $b_index;
            }

            return $level_urgency_comparison;
        });

        $this->df_to_display = array_flip($mc_diagnosis['proposed']);
    }

    private function manageTreatment($json_data)
    {
        $algorithm = $json_data['algorithm'];
        $final_diagnostics = $this->diagnoses_status ?? [];
        $final_diagnostics_additional = $this->current_nodes['diagnoses']['additional'] ?? [];
        $nodes = $algorithm['nodes'];
        $diagnoses = $algorithm['diagnoses'];
        $current_systems =  $this->current_nodes['diagnoses'] ?? [];
        $questions_to_display = [];

        $all_final_diagnostics = array_replace($final_diagnostics, $final_diagnostics_additional);

        foreach ($all_final_diagnostics as $agreed_final_diagnostic => $agreed) {
            if (!$agreed) {
                continue;
            }

            $final_diagnostic = $nodes[$agreed_final_diagnostic];

            $instances = $diagnoses[$final_diagnostic['diagnosis_id']]['final_diagnoses'][$final_diagnostic['id']]['instances'];
            $top_conditions = $this->getTopConditions($instances, true);

            $this->handleChildren(
                $json_data,
                $top_conditions,
                null,
                $questions_to_display,
                $instances,
                [config('medal.categories.treatment_question')],
                $final_diagnostic['diagnosis_id'],
                config('medal.node_types.diagnosis'),
                false,
                $current_systems,
                []
            );
        }

        $questions_to_display = array_fill_keys($questions_to_display, '');

        foreach ($questions_to_display as $node_id => $v) {
            if (array_key_exists($node_id, $this->current_nodes['diagnoses']['treatment_questions'] ?? [])) {
                $questions_to_display[$node_id] = $this->current_nodes['diagnoses']['treatment_questions'][$node_id];
            }
        }
        $this->current_nodes['diagnoses']['treatment_questions'] = $questions_to_display;

        $this->current_nodes['diagnoses']['treatment_questions'] = array_intersect_key(
            $questions_to_display,
            $this->current_nodes['diagnoses']['treatment_questions']
        );

        $this->current_nodes['diagnoses']['treatment_questions'] = array_replace(
            $this->current_nodes['diagnoses']['treatment_questions'] ?? [],
            $questions_to_display
        );
    }

    private function manageDrugs($json_data)
    {
        $nodes = $json_data['algorithm']['nodes'];

        $this->current_nodes['diagnoses']['agreed'] = $this->getNewDiagnoses(
            $json_data,
            $this->current_nodes['diagnoses']['agreed'],
            true
        );

        $new_drugs = $this->reworkAndOrderDrugs($json_data);

        $this->current_nodes['drugs'] = $new_drugs;

        uasort($this->current_nodes['drugs']['calculated'], function ($a, $b) use ($nodes) {
            return $nodes[$b['id']]['level_of_urgency'] <=> $nodes[$a['id']]['level_of_urgency'];
        });
    }

    private function manageSummary($json_data)
    {
        $weight = $this->current_nodes['first_look_assessment']['basic_measurements_nodes_id'][$json_data['weight_question_id']];
        $formulations = new FormulationService(
            $json_data,
            $this->current_nodes['diagnoses']['agreed'] ?? [],
            $weight
        );
        $this->current_nodes['diagnoses']['agreed'] = $formulations->getFormulations();
    }

    private function manageReferral($json_data)
    {
        $algorithm = $json_data['algorithm'];
        $referral_order = $algorithm['config']['full_order']['referral_step'];
        $instances = $algorithm['diagram']['instances'];
        $mc_nodes = $this->medical_case['nodes'];

        $referral_nodes = array_fill_keys(array_filter($referral_order, function ($node_id) use ($json_data, $instances, $mc_nodes) {
            return $this->calculateConditionInverse($json_data, $instances[$node_id]['conditions'] ?? [], $mc_nodes);
        }), '');

        $this->current_nodes['referral'] = array_replace(
            $referral_nodes,
            $this->current_nodes['referral'] ?? []
        );
    }

    private function calculateConditionInverseFinalDiagnosis($conditions, $mc_nodes, $instances)
    {
        if (empty($conditions)) {
            return true;
        }

        foreach ($conditions as $condition) {
            $condition_value =
                $mc_nodes[$condition['node_id']]['answer'] === $condition['answer_id'] &&
                $this->respectsCutOff($condition);

            if ($condition_value) {
                if ($this->calculateConditionInverseFinalDiagnosis(
                    $instances[$condition['node_id']]['conditions'],
                    $mc_nodes,
                    $instances
                )) {
                    return true;
                }
            }
        }

        return false;
    }

    private function getAvailableHealthcare($json_data, $final_diagnosis, $key, $exclusion = true)
    {
        $algorithm = $json_data['algorithm'];
        $nodes = $algorithm['nodes'];

        $instances = array_replace(
            $algorithm['diagnoses'][$final_diagnosis['diagnosis_id']]['final_diagnoses'][$final_diagnosis['id']]['instances'],
            $final_diagnosis[$key]
        );

        $questions_to_display = [];

        if ($key === 'drugs') {
            $all_drugs = array_filter($instances, function ($instance) use ($nodes) {
                return $nodes[$instance['id']]['category'] === config('medal.categories.drug');
            });
            $this->handleDrugs($json_data, $all_drugs, $questions_to_display, $instances, $exclusion);
        } else {
            $all_managements = array_filter($instances, function ($instance) use ($nodes) {

                return $nodes[$instance['id']]['category'] === config('medal.categories.management');
            });
            $this->handleManagements($json_data, $all_managements, $questions_to_display, $instances);
        }

        return array_unique($questions_to_display);
    }

    private function isHealthcareExcluded($json_data, $healthcare_id, $agreed_healthcares)
    {
        $algorithm = $json_data['algorithm'];
        $nodes = $algorithm['nodes'];

        return in_array($healthcare_id, $nodes[$healthcare_id]['excluding_nodes_ids']) &&
            !empty(array_intersect($agreed_healthcares, $nodes[$healthcare_id]['excluding_nodes_ids']));
    }

    private function handleManagements($json_data, $management_instances, &$questions_to_display, $instances)
    {
        $algorithm = $json_data['algorithm'];
        $mc_nodes = $this->medical_case['nodes'];
        $nodes = $algorithm['nodes'];
        $agreed_final_diagnoses = $this->current_nodes['diagnoses']['agreed'];

        $managements = array_map(function ($agreed_final_diagnosis) use ($json_data, $nodes) {
            return array_map(function ($management) {
                return $management['id'];
            }, array_filter($nodes[$agreed_final_diagnosis['id']]['managements'], function ($management) use ($json_data) {
                return $this->calculateCondition($json_data, $management);
            }));
        }, $agreed_final_diagnoses);

        $managements = Arr::flatten($managements);

        foreach ($management_instances as $instance) {
            if ($this->calculateConditionInverseFinalDiagnosis($instance['conditions'], $mc_nodes, $instances)) {

                if ($nodes[$instance['id']]['category'] === config('medal.categories.management')) {
                    if (!$this->isHealthcareExcluded($json_data, $instance['id'], $managements)) {
                        $questions_to_display[] = $instance['id'];
                    }
                }
            }
        }
    }

    private function handleDrugs($json_data, $drug_instances, &$questions_to_display, $instances, $exclusion)
    {
        $agreed_final_diagnoses = $this->current_nodes['diagnoses']['agreed'];
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
                    if (!$this->isHealthcareExcluded($json_data, $instance['id'], $agreed_drugs)) {
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
        $diagnoses = $this->current_nodes['diagnoses'];

        foreach ($drug['diagnoses'] as $diagnosis) {
            if (isset($diagnoses[$diagnosis['key']][$diagnosis['id']]['drugs']['agreed'][$drug['id']])) {
                return true;
            }
        }
        return false;
    }

    public function drugIsRefused($drug)
    {
        $diagnoses = $this->current_nodes['diagnoses'];
        foreach ($drug['diagnoses'] as $diagnosis) {
            if (in_array($drug['id'], $diagnoses[$diagnosis['key']][$diagnosis['id']]['drugs']['refused'])) {
                return true;
            }
        }
        return false;
    }

    private function reworkAndOrderDrugs($json_data)
    {
        $algorithm = $json_data['algorithm'];
        $nodes = $algorithm['nodes'];
        $diagnoses = $this->current_nodes['diagnoses'];

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
                            $diagnosis_label = ($diagnosis_type === 'custom')
                                ? $diagnosis['name']
                                : $nodes[$diagnosis['id']]['label']['en'];

                            $drug_index = $this->getDrugIndex($new_drugs, $drug_id);

                            if ($drug_index > -1) {

                                $diagnosis_exists = array_search(
                                    $diagnosis['id'],
                                    array_column($new_drugs[$drug_key][$drug_index]['diagnoses'], 'id')
                                ) !== false;

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
                                $current_duration = $drug['duration'] ?? null;

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
                                    'duration' => $drug_type === 'agreed' ?
                                        $this->extractDuration(
                                            $json_data,
                                            $diagnosis['id'],
                                            $drug_id,
                                            $current_duration ?? 0
                                        ) : $current_duration,
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

    private function extractDuration($json_data, $diagnosis_id, $drug_id, $current_duration = 0)
    {
        $drug_instance = $json_data['algorithm']['nodes'][$diagnosis_id]['drugs'][$drug_id];

        // Check if the drug is marked as pre-referral
        if ($drug_instance['is_pre_referral']) {
            return __('reader.formulations.drug.pre_referral_duration');
        }

        // If current_duration is an integer, try to extract the new duration
        if (is_int($current_duration)) {
            // Translate the duration string and match it against the regex
            $result = [];
            preg_match('/^\d{1,2}$/', $drug_instance['duration']['en'], $result);

            // If a valid duration is found, return the greater of the new and current duration
            if (!empty($result)) {
                $new_duration = intval($result[0]);
                return ($new_duration > $current_duration) ? $new_duration : $current_duration;
            }
        }

        // Return an invalid duration message if no valid duration is found
        return __('reader.containers.medical_case.drugs.duration_invalid');
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

    private function findProposedFinalDiagnoses($json_data, $diagnosis)
    {
        $top_conditions = $this->getTopConditions($diagnosis['instances']);

        return array_unique(
            array_merge(...array_map(function ($instance) use ($json_data, $diagnosis) {
                return $this->searchFinalDiagnoses($json_data, $instance, $diagnosis['instances'], $diagnosis['id']);
            }, $top_conditions))
        );
    }

    private function searchFinalDiagnoses($json_data, $instance, $instances, $source_id = null, $final_diagnoses = [])
    {
        $algorithm = $json_data['algorithm'];
        $nodes = $algorithm['nodes'];
        $mc_nodes = $this->medical_case['nodes'];

        $instance_condition = $this->calculateCondition($json_data, $instance, $source_id);

        if ($instance_condition) {
            foreach ($instance['children'] as $child_id) {
                if ($nodes[$child_id]['type'] === config('medal.node_types.final_diagnosis')) {
                    $final_diagnosis_condition = $this->reduceConditions(
                        $this->diagramConditionsValues($json_data, $child_id, $instance, $mc_nodes)
                    );

                    if ($final_diagnosis_condition) {
                        $final_diagnoses[] = $child_id;
                    }
                } else {
                    $final_diagnoses = $this->searchFinalDiagnoses(
                        $json_data,
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

    private function getNewDiagnoses($json_data, $final_diagnoses, $remove_drugs = false)
    {
        $algorithm = $json_data['algorithm'];
        $nodes = $algorithm['nodes'];
        $new_final_diagnoses = [];
        $new_agreed = [];

        // Regroup all agreed drugs for exclusions
        foreach ($final_diagnoses as $final_diagnosis) {
            foreach ($final_diagnosis['drugs']['agreed'] as $drug) {
                if (array_key_exists($drug['id'], $new_agreed)) {
                    $new_agreed[$drug['id']]['final_diagnoses_id'][] = $final_diagnosis['id'];
                } else {
                    $new_agreed[$drug['id']] = array_replace($drug, [
                        'formulation_id' => $drug['formulation_id'] ?? null,
                        'final_diagnoses_id' => [$final_diagnosis['id']]
                    ]);
                }
            }
        }

        // Find for all final diagnosis the proposed drugs
        foreach ($final_diagnoses as $final_diagnosis) {
            $available_drugs = $this->getAvailableHealthcare(
                $json_data,
                $nodes[$final_diagnosis['id']],
                'drugs',
                false
            );

            $new_additional = json_decode(json_encode($final_diagnosis['drugs']['additional']), true);

            if ($remove_drugs) {
                $drug_to_remove = array_filter(
                    array_keys($final_diagnosis['drugs']['agreed']),
                    function ($agreed_drug_id) use ($json_data, $available_drugs, $new_agreed) {
                        return !in_array((int)$agreed_drug_id, $available_drugs)
                            || $this->isHealthcareExcluded(
                                $json_data,
                                $agreed_drug_id,
                                array_column($new_agreed, 'id')
                            );
                    }
                );
                foreach ($drug_to_remove as $drug_id) {
                    unset($new_agreed[$drug_id]);
                }
            }

            $new_proposed = array_filter($available_drugs, function ($drug_id) use ($json_data, $new_agreed) {
                return !$this->isHealthcareExcluded(
                    $json_data,
                    $drug_id,
                    array_column($new_agreed, 'id')
                );
            });

            $agreed_with_formulations = [];
            foreach ($new_agreed as $drug) {
                if (in_array($final_diagnosis['id'], $drug['final_diagnoses_id'])) {
                    $drug_formulations = $nodes[$drug['id']]['formulations'];
                    $agreed_with_formulations[$drug['id']] = array_replace($drug, [
                        'formulation_id' => count($drug_formulations) === 1
                            ? $drug_formulations[0]['id']
                            : $final_diagnosis['drugs']['agreed'][$drug['id']]['formulation_id'] ?? null
                    ]);
                    if (count($drug_formulations) === 1) {
                        $this->formulations[$drug['id']] = strval($drug_formulations[0]['id']);
                        $this->updatedFormulations(strval($drug_formulations[0]['id']), $drug['id']);
                    }
                }
            }

            $additional_with_formulations = [];
            foreach ($new_additional as $drug) {
                $drug_formulations = $nodes[$drug['id']]['formulations'];
                $additional_with_formulations[$drug['id']] = array_replace($drug, [
                    'formulation_id' => count($drug_formulations) === 1
                        ? $drug_formulations[0]['id']
                        : $final_diagnosis['drugs']['additional'][$drug['id']]['formulation_id'] ?? null
                ]);
            }

            // Management calculations
            $managements = $this->getAvailableHealthcare(
                $json_data,
                $nodes[$final_diagnosis['id']],
                'managements'
            );

            $new_final_diagnoses[$final_diagnosis['id']] = array_replace($final_diagnosis, [
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

    private function findExcludedFinalDiagnoses($json_data, $diagnosis)
    {
        $top_conditions = $this->getTopConditions($diagnosis['instances']);

        return array_map(function ($final_diagnosis) use ($json_data, $top_conditions, $diagnosis) {
            return [
                'id' => $final_diagnosis['id'],
                'value' => $this->reduceConditions(array_map(function ($instance) use ($json_data, $diagnosis, $final_diagnosis) {
                    return $this->searchExcludedFinalDiagnoses(
                        $json_data,
                        $instance,
                        $diagnosis['instances'],
                        $final_diagnosis
                    );
                }, $top_conditions))
            ];
        }, $diagnosis['final_diagnoses']);
    }

    private function searchExcludedFinalDiagnoses($json_data, $instance, $instances, $final_diagnosis)
    {
        $algorithm = $json_data['algorithm'];
        $nodes = $algorithm['nodes'];
        $mc_node = $this->medical_case['nodes'][$instance['id']];

        $instance_condition = $this->calculateCondition($json_data, $instance);

        if ($instance_condition && $mc_node['answer'] === null) {
            return null;
        }

        if ($instance_condition) {
            return $this->reduceConditions(array_map(function ($child_id) use ($json_data, $nodes, $instances, $final_diagnosis) {
                if ($nodes[$child_id]['type'] === config('medal.node_types.final_diagnosis')) {
                    return $final_diagnosis['id'] === $child_id && $this->calculateCondition($json_data, $nodes[$child_id]);
                } else {
                    return $this->searchExcludedFinalDiagnoses(
                        $json_data,
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

    private function roundValue($value, $step = 1.0)
    {
        $inv = 1.0 / $step;
        $result = round($value * $inv) / $inv;
        return (fmod($result, 1) === 0.0) ? (int) $result : $result;
    }

    private function handleNumeric($json_data, $mc_node, $node, $value)
    {
        $response = ['answer' => null, 'value' => $value];
        $unavailable_answer = collect($node['answers'])->firstWhere('value', 'not_available');

        if (is_null($value)) {
            $response['answer'] = null;
            $response['value'] = '';
        } elseif (
            $mc_node['unavailable_value'] ||
            ($unavailable_answer && $unavailable_answer['id'] === $value)
        ) {
            // Unavailable question
            $response['answer'] = (int)$value;
            $response['value'] = $node['answers'][$response['answer']]['value'];
        } else {
            // Normal process
            if (!is_null($value)) {
                $answer = $this->handleAnswers($json_data, $node['id'], $value);
                $response['answer'] = $answer['answer'];
                $response['label'] = $answer['label'];
            } else {
                $response['answer'] = null;
            }

            if (isset($node['round']) && !is_null($node['round'])) {
                $response['rounded_value'] = $this->roundValue($value, $node['round']);
            }
        }

        return $response;
    }

    private function handleAnswerId($node, $value)
    {
        $answer = null;
        if (!is_null($value)) {
            // Set Number only if this is a number
            if (is_numeric($value)) {
                $answer = (int)$value;
                $value = $node['answers'][$answer]['value'] ?? null;
            } else {
                $answer = collect($node['answers'])->firstWhere('value', $value);
                $answer = $answer ? $answer['id'] : null;
            }
        }
        return ['answer' => $answer, 'value' => $value];
    }

    private function setNodeValue($json_data, $mc_node, $node, $value)
    {
        $value_formats = config('medal.value_formats');
        switch ($node['value_format']) {
            case $value_formats['int']:
            case $value_formats['float']:
            case $value_formats['date']:
                return $this->handleNumeric($json_data, $mc_node, $node, $value);
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

    private function comparingBooleanOr($first_boolean, $second_boolean)
    {
        if ($first_boolean === true || $second_boolean === true) {
            return true;
        }

        if ($first_boolean === false && $second_boolean === false) {
            return false;
        }

        if ($first_boolean === null || $second_boolean === null) {
            return null;
        }
    }

    public function goToStep(string $step): void
    {
        if ($this->algorithm_type === 'dynamic') {
            if (isset($this->current_nodes['registration']['birth_date'])) {
                $fifteen_years_ago = Carbon::now()->subYears(15)->gte($this->current_nodes['registration']['birth_date']);
                if ($fifteen_years_ago) {
                    flash()->addError('This patient is ineligible for the study (age). No clinical data will be collected');
                    return;
                }
            }
            if (!isset($this->age_in_days)) {
                flash()->addError('Please enter a date of birth');
                return;
            }
        }

        $json_data = Cache::get($this->cache_key);

        if ($this->current_sub_step === '' && $this->algorithm_type !== 'training') {
            if (!empty($this->steps[$this->algorithm_type][$step])) {
                $this->current_sub_step = $this->steps[$this->algorithm_type][$step][0];
            }
        }

        if ($this->algorithm_type === 'prevention') {
            $this->validate();
        }

        if ($step === 'registration') {
            $this->manageRegistrationStep($json_data);
        }

        if ($step === 'first_look_assessment') {
            if ($this->algorithm_type === 'prevention') {
                $valid_diagnoses = $this->getValidPreventionDiagnoses($json_data);
                if (empty($valid_diagnoses)) {
                    flash()->addError('There is no recommendation for this age range');
                    return;
                }
                $this->diagnoses_per_cc = $valid_diagnoses;
            }
            $this->manageFirstLookAssessmentStep($json_data);
        }

        if ($step === 'consultation') {
            $this->manageConsultationStep($json_data);
        }

        if ($step === 'tests') {
            $this->manageTestStep($json_data);
        }

        if ($step === 'diagnoses') {
            $this->manageDiagnosesStep($json_data);
        }

        $this->current_step = $step;

        //quick and dirty fix for training mode
        //todo actually calculate it and change from int to string and
        //search for the index in the array
        if ($this->algorithm_type === 'training' && $step === 'diagnoses') {
            $this->saved_step = 2;
            $this->completion_per_step[1] = 100;
        }

        //Need to be on the future validateStep function, not here and remove the max
        $this->saved_step = max($this->saved_step, array_search($this->current_step, array_keys($this->steps[$this->algorithm_type])) + 1);

        $this->dispatch('scrollTop');
    }

    public function goToSubStep(string $step, string $substep): void
    {
        $this->current_sub_step = $substep;
        $this->goToStep($step);
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
        $this->dispatch('scrollTop');

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
        $this->dispatch('scrollTop');

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
        $this->dispatch('scrollTop');

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

        $json_data = Cache::get($this->cache_key);
        $algorithm = $json_data['algorithm'];

        $nodes = $algorithm['nodes'];

        foreach (array_filter($this->diagnoses_status) as $diagnose_id => $accepted) {
            $response = Http::acceptJson()
                ->withToken('354d0b462045526:7b87001c6800153', 'token')
                ->post("http://development.localhost:8000/api/resource/Diagnosis", [
                    'diagnosis' => $nodes[$diagnose_id]['label']['en'],
                    'estimated_duration' => 259200
                ])
                ->throwUnlessStatus(409);

            $data["diagnosis"][] = [
                "docstatus" => 1,
                "diagnosis" => $nodes[$diagnose_id]['label']['en'],
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
                'item_code' => $nodes[$drug_id]['label']['en'],
                'item_name' => $nodes[$drug_id]['label']['en'],
                "item_group" => "Drug",
                "stock_uom" => "Gram",
            ];

            $drugs[$drug_id] = [
                "docstatus" => 1,
                "generic_name" => $nodes[$drug_id]['label']['en'],
                "medication_class" => "Generic",
                "strength" => 1.0,
                "strength_uom" => "Gram",
                "default_interval" => 0,
                "default_interval_uom" => "Hour",
                "change_in_item" => 0
            ];

            $data["drug_prescription"][] = [
                "docstatus" => 1,
                "medication" => $nodes[$drug_id]['label']['en'],
                "drug_code" => $nodes[$drug_id]['label']['en'],
                "drug_name" => $nodes[$drug_id]['label']['en'],
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
            // 'nodes_to_save' => $this->nodes_to_save,
            'df' => $this->df_to_display,
            'df_status' => $this->diagnoses_status,
            'drugs_status' => $this->drugs_status,
            // 'drugs_formulation' => $this->drugs_formulation,
            'complaint_categories' => $this->chosen_complaint_categories,
            'patient_id' => $this->patient_id,
            'version_id' => $this->id,
        ];

        dd($data);
        $json = $this->jsonExportService->prepareJsonData($data);

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
            // 'nodes_to_save' => $this->nodes_to_save,
            'df' => $this->df_to_display,
            'df_status' => $this->diagnoses_status,
            'drugs_status' => $this->drugs_status,
            // 'drugs_formulation' => $this->drugs_formulation,
            'complaint_categories' => $this->chosen_complaint_categories,
            'patient_id' => $this->patient_id,
            'version_id' => $this->id,
        ];

        $json = $this->jsonExportService->prepareJsonData($data);
        dd($json);

        if (!$this->patient_id) {
            return flash()->addError('No current patient');
        }

        $json_data = Cache::get($this->cache_key);
        $df = $json_data['algorithm']['nodes'];

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

    public function debugUpdatingCurrentNodesRegistrationBirthDate($birth_date)
    {
        return $this->updatingCurrentNodesRegistrationBirthDate($birth_date);
    }

    public function debugUpdatingCurrentNodes($key, $value)
    {
        return $this->updatingCurrentNodes($value, $key);
    }

    public function debugUpdatedDiagnosesStatus($value, $key)
    {
        return $this->updatedDiagnosesStatus($value, $key);
    }
}
