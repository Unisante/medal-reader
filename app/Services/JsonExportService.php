<?php

namespace App\Services;

use App\Http\Resources\JsonExportResource;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;


class JsonExportService
{
    /**
     * Prepare the consultation data for JSON export.
     *
     * @param array $data
     * @return string
     */
    public function prepareJsonData(array $data): string
    {
        $nodes_to_save = $data['nodes_to_save'] ?? [];

        //We remove first_name, last_name and birthdate from the nodes
        $current_nodes = array_filter($data['nodes'], fn ($k) => is_numeric($k), ARRAY_FILTER_USE_KEY);

        // Merge nodes with nodes_to_save values
        foreach ($current_nodes as $key => $value) {
            if (isset($nodes_to_save[$key])) {
                if ($nodes_to_save[$key]['value'] !== '' && $nodes_to_save[$key]['answer_id'] !== '') {
                    $nodes[$key] = [
                        'id' => $key,
                        'value' => (string) $nodes_to_save[$key]['value'],
                        'answer' => $nodes_to_save[$key]['answer_id'],
                    ];
                }
            } else {
                if ($value !== '') {
                    //quick fix for the village
                    if (is_int($value)) {
                        $nodes[$key] = [
                            'id' => $key,
                            'value' => null,
                            'answer' => (int) $value,
                        ];
                    } else {
                        $nodes[$key] = [
                            'id' => $key,
                            'value' => (string) $value,
                            'answer' => null,
                        ];
                    }
                }
            }
        }

        $nodes[7521] = [
            'id' => 7521,
            'answer' => 5606,
        ];

        foreach (array_filter($data['df_status']) as $df_id => $df) {
            $agreed_diagnoses[$df_id] = [
                'id' => $df_id,
                'drugs' => [
                    'proposed' => array_keys($data['df'][$df_id]),
                    'agreed' => array_filter(
                        Arr::map($data['drugs_status'], function ($value, $key) use ($data, $df_id) {
                            if (array_key_exists($key, $data['df'][$df_id])) {
                                return [
                                    'id' => $key,
                                    'formulation_id' => $data['drugs_formulation'][$key],
                                ];
                            }
                        })
                    ),
                    //To specify when done
                    'refused' => [],
                    'additional' => [],
                    'custom' => [],
                ],
                'managements' => [],
            ];
        }

        $data = (object) [
            // 'version_id' => $data['version_id'],
            'id' => Str::uuid(),
            'version_id' => 96,
            'nodes' => $nodes,
            'diagnosis' => [
                'proposed' => array_keys($data['df']),
                'agreed' => $agreed_diagnoses,
                //To specify when done
                'additional' => [],
                'excluded' => [],
                'refused' => [],
                'custom' => [],
            ],
            'patient' => [
                'id' => Str::uuid(),
                'uid' => Str::uuid(),
                'first_name' => $data['nodes']['first_name'],
                'last_name' => $data['nodes']['last_name'],
                'birth_date' => $data['nodes']['birth_date'],
                //To specify when done
                'group_id' => 149,
                "consent_file" => "",
                "other_group_id" => null,
                "other_study_id" => null,
                "other_uid" => null,
                "createdAt" => 1640160680000,
                "updatedAt" => 1682410950000
            ],
            //to specify when done
            "createdAt" => 1640160680000,
            "updatedAt" => 1682410950000,
            "closedAt" => 1682410950000,
            'activities' => [],
        ];
        dd(json_encode($data));
        // Use the resource to structure the JSON response
        $resource = new JsonExportResource($data);
        return $resource->toJson();
    }
}
