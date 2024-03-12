<?php

namespace App\Services;

use DateTime;
use DCarbone\PHPFHIRGenerated\R4\FHIRResource\FHIRDomainResource\FHIRCondition;
use DCarbone\PHPFHIRGenerated\R4\PHPFHIRResponseParser;
use Exception;
use Illuminate\Support\Facades\Http;

class FHIRService
{

    public function __construct()
    {
    }

    private function getHeaders()
    {
        $headers = [
            'Content-Type: application/fhir+json',

        ];

        // $authorization = $this->getAuthorizationHeader();
        // if ($authorization) {
        //     $headers[] = $authorization;
        // }

        return $headers;
    }

    function getPatientFromRemoteFHIRServer($patient_id)
    {
        // Use preg_replace() to prevent vulernabilities per Psalm.
        $url = rtrim($this->getRemoteFHIRServerUrl(), '/');

        $response = Http::withHeaders($this->getHeaders())->get("$url/Patient", [
            '_id' => $patient_id,
            '_pretty' => true,
        ]);

        return $response;
    }

    function getPatientsFromRemoteFHIRServer()
    {
        return $this->getDataFromRemoteFHIRServer('Patient');
    }

    function setConditionsToPatient($patient_id, $conditions)
    {
        $url = rtrim($this->getRemoteFHIRServerUrl(), '/');
        $headers = [
            'Content-Type: application/fhir+json',
        ];

        foreach ($conditions as $condition) {
            $data = [
                'code' => [
                    'coding' => [
                        [
                            'code' => $condition['medal_c_id'],
                            'display' => $condition['label'],
                        ]
                    ],
                    'text' => $condition['label'],
                ],
                'subject' => [
                    'reference' => "Patient/$patient_id",
                ],
                'clinicalStatus' => [
                    'text' => 'active'
                ],
                'verificationStatus' => [
                    'text' => 'confirmed'
                ],
                'onsetDateTime' => [
                    'value' => today()->format('Y-m-d')
                ],
            ];
            $condition = new FHIRCondition($data);
            if (empty($condition->_getValidationErrors())) {
                $response = Http::withHeaders($headers)->post("$url/Condition", $condition->jsonSerialize());
            }
            if ($response->failed()) {
                return false;
            }
        }

        // $response = Http::withHeaders($headers)->get("$url/Condition", [
        //     'subject' => '181',
        // ]);

        //delete
        // $response = Http::withHeaders($headers)->delete("$url/Condition/222357");

        //for update
        // $response = Http::withHeaders($headers)->put("$url/Condition/222356", $c->jsonSerialize());

        return true;
    }

    function getDataFromRemoteFHIRServer($resource)
    {

        // Use preg_replace() to prevent vulernabilities per Psalm.
        $resource = preg_replace('/[^A-Za-z]/', '', $resource);
        $url = rtrim($this->getRemoteFHIRServerUrl(), '/');

        try {
            $response = Http::withHeaders($this->getHeaders())->get("$url/$resource", [
                '_count' => 138,
                '_pretty' => true,
            ]);
        } catch (Exception $e) {
            return null;
        }
        // $response->throw()->json();
        // dd($response);

        return $response;
    }

    function getRemoteFHIRServerUrl()
    {
        $url = config('fhir.fhir_server');
        if (empty($url)) {
            throw new Exception('A remote FHIR server url must be configured.');
        }

        return $url;
    }

    function jsonSerialize($fhirObjectOrArray)
    {
        $a = $this->toArray($fhirObjectOrArray);
        return json_encode($a, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    function parse($data)
    {
        $parser = new PHPFHIRResponseParser();
        return $parser->parse($data);
    }

    function toArray($fhirObjectOrArray)
    {
        if (is_array($fhirObjectOrArray)) {
            return $fhirObjectOrArray;
        } else if (is_object($fhirObjectOrArray)) {
            $a = $fhirObjectOrArray->jsonSerialize();
            $a = json_decode(json_encode($a), true);

            $handle = function (&$a) use (&$handle) {
                foreach ($a as $key => &$value) {
                    if (gettype($key) === 'string' && $key[0] === '_') {
                        // TODO - Contribute this change back.
                        unset($a[$key]);
                        continue;
                    }

                    if (is_array($value)) {
                        $handle($value);
                    }
                }
            };

            $handle($a);
            return $a;
        } else {
            throw new Exception('A valid FHIR object or array must be specified.');
        }
    }

    function formatFHIRDateTime($timestamp)
    {
        return $this->getDateTime($timestamp)->format('Y-m-d\TH:i:sP');
    }

    private function getDateTime($mixed)
    {
        $type = gettype($mixed);

        if ($type === 'string') {
            return new DateTime($mixed);
        } else if ($type === 'integer') {
            $d = new DateTime();
            $d->setTimestamp($mixed);
            return $d;
        } else {
            // Assume this is already a DateTime object.
            return $mixed;
        }
    }
}
