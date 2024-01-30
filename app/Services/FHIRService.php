<?php

namespace App\Services;

use DateTime;
use DCarbone\PHPFHIRGenerated\R4\FHIRElement\FHIRBackboneElement\FHIRBundle\FHIRBundleEntry;
use DCarbone\PHPFHIRGenerated\R4\FHIRResource\FHIRBundle;
use DCarbone\PHPFHIRGenerated\R4\FHIRResource\FHIRDomainResource\FHIRPatient;
use DCarbone\PHPFHIRGenerated\R4\PHPFHIRResponseParser;
use Exception;

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

        return implode("\r\n", $headers);
    }

    function sendToRemoteFHIRServer($resource)
    {

        $type = 'collection';

        $bundle = $this->createResource('Patient', [
            'type' => $type,
        ]);
        dump($bundle);

        // $patient = $this->createResource('Patient', $patientObj);
        $resource['resourceType'] = 'Patient';

        // $bundle = new FHIRPatient([
        //     'timestamp' => $this->formatFHIRDateTime(time()),
        //     'type' => [
        //         'value' => 'document'
        //     ],
        //     // 'meta' => self::createMeta('Determination'),
        // ]);

        // $bundle->addEntry(new FHIRBundleEntry([
        //     // force Patient for now
        //     'resource' => 'Patient'
        // ]));

        // Use preg_replace() to prevent vulernabilities per Psalm.
        $resourceType = preg_replace('/[^A-Za-z]/', '', $resource['resourceType']);

        $url = rtrim($this->getRemoteFHIRServerUrl(), '/');

        $response = file_get_contents("$url/Patient", false, stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => $this->getHeaders(),
                'content' => $this->jsonSerialize($bundle),
                'ignore_errors' => true
            ]
        ]));

        $o = $this->parse($response);
        dump($o);

        // $input = file_get_contents('php://input');
        // $o = $this->parse($input);
        // $type = $o->_getFHIRTypeName();

        dd($response);

        $handleError = function ($errorToWrap = null) use ($resourceType, $response) {
            $parsedResponse = json_decode($response, true);
            $issues = $parsedResponse['issue'] ?? null;
            if ($issues && $errorToWrap === null) {
                $message = "The request failed with the following errors:\n";
                foreach ($issues as $issue) {
                    $phrase = 'invalid JSON: ';
                    $parts = explode($phrase, $issue['diagnostics']);
                    $parts = array_map(function (&$part) {
                        return trim($part);
                    }, $parts);

                    $message .= "- " . implode($phrase, $parts);

                    $expression = implode(', ', $issue['expression'] ?? []);
                    if (!empty($expression)) {
                        $message .= " for $expression";
                    }

                    $message .= "\n";
                }
            } else {
                $message = "A $resourceType response was expected, but ";

                if (empty($response)) {
                    $message .= "an empty response with an unsuccessful HTTP response status code was received.";
                } else {
                    $message .= "the following was received instead: $response";
                }

                if ($errorToWrap) {
                    $message .= "\n\nThe following Exception occurred while processing this response: " . $errorToWrap->__toString();
                }
            }

            throw new \Exception($message);
        };

        if (empty($response)) {
            // parse for response HTTP status code
            $http_response_line = $http_response_header[0];
            // look for "HTTP/1.1 [[STATUS_CODE]] OK"
            if (preg_match("/\s([0-9]+)\s/", $http_response_line, $match)) {
                $http_response_status_code = intval($match[0]);
                if ($http_response_status_code >= 200 && $http_response_status_code < 300) {
                    // got empty response but status code indicate success
                    // -> no further validation
                    return null;
                }
            }
        }

        try {
            $responseResource = $this->parse($response);
            $responseResourceType = $responseResource->_getFHIRTypeName();
        } catch (\Throwable $t) {
            $handleError($t);
        }

        if ($responseResourceType === $resourceType) {
            return $responseResource;
        } else {
            $handleError();
        }
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

    private function createResource($type, $args)
    {
        return array_merge([
            'resourceType' => $type,
        ], $args);
    }
}
