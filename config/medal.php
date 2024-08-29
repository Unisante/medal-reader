<?php

return [

    'projects' => [
        'dynamic' => [
            'ePOCT',
            'Dynamic',
            'TIMCI',
        ],
        'prevention' => [
            'EviPrev'
        ],
    ],

    'authentication' => [
        'hub_callback_url' => env('HUB_CALLBACK_URL', 'http://127.0.0.1:5555'),
        'reader_callback_url' => env('READER_CALLBACK_URL', 'aaa://callback'),
        'token_lifetime_days' => env('OAUTH_TOKEN_LIFETIME_DAYS', 1),
        'refresh_token_lifetime_days' => env('OAUTH_REFRESH_TOKEN_LIFETIME_DAYS', 30),
    ],

    'algorithm_info' => ['version_name', 'json_version', 'version_id', 'updated_at'],

    'categories' => [
        'assessment' => 'assessment_test',
        'chronic_condition' => 'chronic_condition',
        'basic_measurement' => 'basic_measurement',
        'basic_demographic' => 'basic_demographic',
        'exposure' => 'exposure',
        'physical_exam' => 'physical_exam',
        'symptom' => 'symptom',
        'demographic' => 'demographic',
        'comorbidity' => 'comorbidity',
        'complaint_category' => 'complaint_category',
        'predefined_syndrome' => 'predefined_syndrome',
        'triage' => 'triage',
        'vaccine' => 'vaccine',
        'scored' => 'scored',
        'drug' => 'drug',
        'management' => 'management',
        'treatment_question' => 'treatment_question',
        'background_calculation' => 'background_calculation',
        'vital_sign_anthropometric' => 'vital_sign_anthropometric',
        'observed_physical_sign' => 'observed_physical_sign',
        'consultation_related' => 'consultation_related',
        'unique_triage_physical_sign' => 'unique_triage_physical_sign',
        'unique_triage_question' => 'unique_triage_question',
        'referral' => 'referral',
    ],

    'days_in_month' => 30.4166667,

    'database_interface' => [
        'local' => 'local',
        'remote' => 'remote',
    ],

    'display_format' => [
        'radio_button' => 'RadioButton',
        'input' => 'Input',
        'drop_down_list' => 'DropDownList',
        'formula' => 'Formula',
        'reference' => 'Reference',
        'string' => 'String',
        'autocomplete' => 'Autocomplete',
        'date' => 'Date',
    ],

    'element_per_page' => 5,

    'environments' => [
        ['label' => 'Test', 'value' => 'test'],
        ['label' => 'Staging', 'value' => 'staging'],
        ['label' => 'Production', 'value' => 'production'],
    ],

    'health_facility_info' => [
        'id',
        'architecture',
        'area',
        'country',
        'name',
        'main_data_ip',
        'local_data_ip',
    ],

    'languages' => [
        ['label' => 'English', 'value' => 'en'],
        ['label' => 'Swahili', 'value' => 'sw'],
        ['label' => 'Hindi', 'value' => 'hi'],
        ['label' => 'Français', 'value' => 'fr'],
    ],

    'medical_case_status' => [
        'in_creation' => ['label' => 'in_creation'],
        'triage' => ['label' => 'triage'],
        'consultation' => ['label' => 'consultation'],
        'tests' => ['label' => 'tests'],
        'final_diagnosis' => ['label' => 'final_diagnosis'],
        'close' => ['label' => 'close'],
    ],

    'node_types' => [
        'diagnosis' => 'Diagnosis',
        'final_diagnosis' => 'FinalDiagnosis',
        'health_care' => 'HealthCare',
        'question' => 'Question',
        'questions_sequence' => 'QuestionsSequence',
    ],

    'ping_interval' => 5000,

    'timeout' => 3500,

    'timeout_axios' => 3000,

    'movies_extension' => ['mp4', 'mov', 'avi'],

    'audios_extension' => ['mp3', 'ogg'],

    'pictures_extension' => ['jpg', 'jpeg', 'gif', 'png', 'tiff'],

    'value_formats' => [
        'array' => 'Array',
        'int' => 'Integer',
        'float' => 'Float',
        'bool' => 'Boolean',
        'string' => 'String',
        'date' => 'Date',
        'present' => 'Present',
        'positive' => 'Positive',
    ],

    'administration_route_categories' => [
        'Enteral',
        'Parenteral injectable',
        'Mucocutaneous',
    ],

    'medication_forms' => [
        'tablet' => 'tablet',
        'dispersible_tablet' => 'dispersible_tablet',
        'capsule' => 'capsule',
        'syrup' => 'syrup',
        'suspension' => 'suspension',
        'suppository' => 'suppository',
        'drops' => 'drops',
        'solution' => 'solution',
        'powder_for_injection' => 'powder_for_injection',
        'patch' => 'patch',
        'lotion' => 'lotion',
        'cream' => 'cream',
        'pessary' => 'pessary',
        'ointment' => 'ointment',
        'gel' => 'gel',
        'spray' => 'spray',
        'inhaler' => 'inhaler',
    ],

    'step_orders' => [
        'basic_measurements' => 'basic_measurements',
        'complaint_categories' => 'complaint_categories',
        'first_look_assessment' => 'first_look_assessment',
        'physical_exam' => 'physical_exam',
        'referral_step' => 'referral_step',
        'registration_step' => 'registration_step',
    ],

    'oauth' => [
        'auth_endpoint' => '/oauth/authorize',
        'token_endpoint' => '/oauth/token',
    ],

    'creator' => [
        'url' => env('CREATOR_URL', 'https://liwi-test.wavelab.top'),
        'algorithms_endpoint' => env('CREATOR_ALGORITHM_ENDPOINT', '/api/v1/algorithms'),
        'health_facility_endpoint' => env('CREATOR_HEALTH_FACILITY_ENDPOINT', '/api/v1/health_facilities'),
        'medal_data_config_endpoint' => env('CREATOR_MEDAL_DATA_CONFIG_ENDPOINT', '/api/v1/versions/medal_data_config?version_id='),
        'versions_endpoint' => env('CREATOR_VERSIONS_ENDPOINT', '/api/v1/versions'),
        'get_from_study' => env('CREATOR_GET_FROM_STUDY', '/api/v1/health_facilities/get_from_study?study_label='),
        'study_id' => env('STUDY_ID', 'Dynamic Tanzania'),
        'language' => env('LANGUAGE', 'en'),
    ],
    'urls' => [
        'creator_algorithm_url' => env('CREATOR_ALGORITHM_URL'),
        'creator_health_facility_url' => env('CREATOR_HEALTH_FACILITY_URL'),
        'creator_patient_url' => env('CREATOR_PATIENT_URL'),
    ],

    'global' => [
        'study_id' => env('STUDY_ID'),
        'language' => env('JSON_LANGUAGE'),
        'local_health_facility_management' => env('LOCAL_HEALTH_FACILITY_MANAGEMENT', true),
    ],

    'storage' => [
        'cases_zip_dir' => env('CASES_ZIP_DIR'),
        'json_extract_dir' => env('JSON_EXTRACT_DIR'),
        'json_success_dir' => env('JSON_SUCCESS_DIR'),
        'json_failure_dir' => env('JSON_FAILURE_DIR'),
        'consent_img_dir' => env('CONSENT_IMG_DIR'),
        'json_diag_dir' => env('JSON_DIAG_DIR') ?? 'json_diag',
    ],

    'case_json_properties' => [
        'algorithm' => [
            'keys' => [
                'name' => 'algorithm_name',
                'medal_c_id' => 'algorithm_id',
            ],
        ],
        'activities' => [
            'keys' => [
                'medal_c_id' => 'id',
            ],
            'values' => [
                'step' => 'step',
                'clinician' => 'clinician',
                'mac_address' => [
                    'key' => 'mac_address',
                    'modifiers' => ['optional'],
                ],
                'device_id' => [
                    'key' => 'device_id',
                    'modifiers' => ['optional'],
                ],
            ],
        ],
        'version' => [
            'keys' => [
                'name' => 'version_name',
                'medal_c_id' => 'version_id',
            ],
        ],
        'node' => [
            'keys' => [
                'medal_c_id' => 'id',
            ],
            'values' => [
                'label' => [
                    'key' => 'label',
                    'modifiers' => ['language'],
                ],
                'type' => 'type',
                'category' => 'category',
                'priority' => 'is_mandatory',
                'reference' => [
                    'key' => 'reference',
                    'modifiers' => ['optional'],
                    'type' => 'string',
                ],
                'display_format' => [
                    'key' => 'display_format',
                    'modifiers' => ['optional'],
                    'type' => 'string',
                ],
                'stage' => [
                    'key' => 'stage',
                    'modifiers' => ['optional'],
                    'type' => 'string',
                ],
                'description' => [
                    'key' => 'description',
                    'modifiers' => ['language', 'optional'],
                    'type' => 'string',
                ],
                'formula' => [
                    'key' => 'formula',
                    'modifiers' => ['optional'],
                    'type' => 'string',
                ],
                'is_identifiable' => 'is_identifiable',
            ],
        ],
        'question_sequences' => [
            'keys' => [
                'medal_c_id' => 'id',
            ],
            'values' => [
                'label' => [
                    'key' => 'label',
                    'modifiers' => ['language'],
                ],
                'type' => 'type',
                'category' => 'category',
                'description' => [
                    'key' => 'description',
                    'modifiers' => ['language', 'optional'],
                    'type' => 'string',
                ],
            ],
        ],
        'answer_question_sequences' => [
            'keys' => [
                'medal_c_id' => 'id',
            ],
            'values' => [
                'label' => 'label',
            ],
        ],
        'answer_type' => [
            'keys' => [
                'value' => 'value_format',
            ],
        ],
        'answer' => [
            'keys' => [
                'medal_c_id' => 'id',
            ],
            'values' => [
                'label' => [
                    'key' => 'label',
                    'modifiers' => ['language'],
                ],
            ],
        ],
        'patient_config' => [],
        'health_facility' => [
            'keys' => [
                'name' => 'name',
            ],
            'values' => [
                'group_id' => 'id',
                'long' => 'longitude',
                'lat' => 'latitude',
                'hf_mode' => 'architecture',
            ],
        ],
        'patient' => [
            'keys' => [
                'local_patient_id' => 'uid',
            ],
            'values' => [
                'first_name' => 'first_name',
                'last_name' => 'last_name',
                'birthdate' => [
                    'key' => 'birth_date',
                    'modifiers' => ['datetime-epoch'],
                ],
                'other_group_id' => 'other_group_id',
                'other_study_id' => 'other_study_id',
                'other_uid' => 'other_uid',
                'created_at' => [
                    'key' => 'createdAt',
                    'modifiers' => ['datetime-epoch'],
                ],
                'updated_at' => [
                    'key' => 'updatedAt',
                    'modifiers' => ['datetime-epoch'],
                ],
            ],
        ],
        'diagnosis' => [
            'keys' => [
                'medal_c_id' => 'id',
                'diagnostic_id' => 'diagnosis_id',
            ],
            'values' => [
                'label' => [
                    'key' => 'label',
                    'modifiers' => ['language'],
                ],
                'type' => 'type',
            ],
        ],
        'drug' => [
            'keys' => [
                'medal_c_id' => 'id',
            ],
            'values' => [
                'type' => [
                    'key' => 'type',
                    'modifiers' => ['optional'],
                    'type' => 'string',
                ],
                'label' => [
                    'key' => 'label',
                    'modifiers' => ['language'],
                ],
                'description' => [
                    'key' => 'description',
                    'modifiers' => ['language', 'optional'],
                    'type' => 'string',
                ],
                'is_antibiotic' => [
                    'key' => 'is_antibiotic',
                    'modifiers' => ['optional'],
                    'type' => 'object',
                ],
                'is_anti_malarial' => [
                    'key' => 'is_anti_malarial',
                    'modifiers' => ['optional'],
                    'type' => 'object',
                ],
            ],
        ],
        'management' => [
            'keys' => [
                'medal_c_id' => 'id',
            ],
            'values' => [
                'type' => [
                    'key' => 'type',
                    'modifiers' => ['optional'],
                    'type' => 'string',
                ],
                'label' => [
                    'key' => 'label',
                    'modifiers' => ['language'],
                ],
                'description' => [
                    'key' => 'description',
                    'modifiers' => ['language', 'optional'],
                    'type' => 'string',
                ],
            ],
        ],
        'formulation' => [
            'keys' => [
                'medal_c_id' => 'id',
            ],
            'values' => [
                'medication_form' => 'medication_form',
                'administration_route_category' => 'administration_route_category',
                'administration_route_name' => 'administration_route_name',
                'liquid_concentration' => 'liquid_concentration',
                'dose_form' => 'dose_form',
                'unique_dose' => 'unique_dose',
                'by_age' => 'by_age',
                'minimal_dose_per_kg' => 'minimal_dose_per_kg',
                'maximal_dose_per_kg' => 'maximal_dose_per_kg',
                'maximal_dose' => 'maximal_dose',
                'doses_per_day' => 'doses_per_day',
                'description' => [
                    'key' => 'description',
                    'modifiers' => ['language', 'optional'],
                    'type' => 'string',
                ],
            ],
        ],
        'medical_case' => [
            'keys' => [
                'local_medical_case_id' => 'id',
            ],
            'values' => [
                // 'consent' => 'consent',
            ],
        ],
        'medical_case_answer' => [],
        'diagnosis_reference' => [],
        'excluded_diagnoses_list' => [],
        'drug_reference' => [
            'values' => [
                'duration' => [
                    'key' => 'duration',
                    'modifiers' => ['optional'],
                ],
            ],
        ],
        'management_reference' => [],
        'custom_diagnosis' => [
            'keys' => [
                'label' => 'name',
            ],
        ],
        'custom_drug' => [
            'keys' => [
                'name' => 'name',
            ],
            'values' => [
                'duration' => 'duration',
            ],
        ],
    ],
];
