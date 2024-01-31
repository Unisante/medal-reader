<?php

namespace App\Services;

use DateTime;
use DCarbone\PHPFHIRGenerated\R4\FHIRElement\FHIRBackboneElement\FHIRBundle\FHIRBundleEntry;
use DCarbone\PHPFHIRGenerated\R4\FHIRResource\FHIRBundle;
use DCarbone\PHPFHIRGenerated\R4\FHIRResource\FHIRDomainResource\FHIRPatient;
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

    function getPatientsFromRemoteFHIRServer()
    {
        return $this->getDataFromRemoteFHIRServer('Patient');
    }

    function getDataFromRemoteFHIRServer($resource)
    {

        // Use preg_replace() to prevent vulernabilities per Psalm.
        $resource = preg_replace('/[^A-Za-z]/', '', $resource);
        $url = rtrim($this->getRemoteFHIRServerUrl(), '/');

        $response = Http::withHeaders($this->getHeaders())->get("$url/$resource", [
            '_count' => 1000,
            '_pretty' => true,
        ]);
        $response->throw();

        return $response->json();
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
