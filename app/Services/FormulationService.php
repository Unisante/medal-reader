<?php

namespace App\Services;
// food for thought
// get the formulation
/**
 * drugdose service receive the formulation id and drug id
 * finds the weight of the medical case instance
 * finds
 */

use Illuminate\Support\Facades\Cache;

class FormulationService
{

    public array $drugs_formulation;
    public array $agreed_diagnoses;
    public array $cached_data;
    public array $drugs_duration;


    public function __construct(array $drugs_formulation, array $agreed_diagnoses, string $cache_key)
    {
        $this->drugs_formulation = $drugs_formulation;
        $this->agreed_diagnoses = $agreed_diagnoses;
        $this->cached_data = Cache::get($cache_key);
        $this->calculateDrugDuration();
    }


    public function calculateDrugDuration()
    {
        // key is drug_id and value is duration

        foreach($this->agreed_diagnoses as $diagnosis_id=> $diagnosis){
            foreach($diagnosis['drugs'] as $drug_id=>$drug_id){
                // $this->drugs_duration[$drug_id]=$this->cached_data['final_diagnoses'][$diagnosis_id]['drugs'][$drug_id]['duration']['en'];
                // dd($this->cached_data['final_diagnoses'][$diagnosis_id]['drugs'][$drug_id]);
                if (isset($this->drugs_duration[$drug_id])){
                    // find the finaldiagnosis and find the duration
                    // check if the dureation is larger and replace.
                    // dd($this->cached_data['final_diagnoses'][$diagnosis_id]['drugs'][$drug_id]);
                    continue;
                }
                // dd($this->cached_data['final_diagnoses'][$diagnosis_id]['drugs'][$drug_id]);
            }
        }
    }

    // public function calculateHighestDuration(){

    // }
    // public function calculateFormulationDurations(array $agreed_diagnoses){
    //     $drugs_duration=[];
    //     foreach($agreed_diagnoses as $diagnosis_id=> $diagnosis){
    //         // if(array_key_exists())
    //         foreach($diagnosis['drugs'] as $key=>$drug){
    //             if(array_key_exists($key,$drugs_duration)){
    //                 // if it exist find if the value is the largest
    //                 continue;
    //             }
    //             // it doesn't exist, then we add it to the array
    //             $drugs_duration[$key]='value of duration';
    //         }
    //     }
    // }
    // public function extractDuration(array $drug)
    // {
    //     // $pattern = '/^\d{1,2}$/';
    //     // if (boolval($drug_instance['is_pre_referral'])){
    //     //     return 'While arranging referral';
    //     // }
    //     // if(preg_match($pattern, $yourInput))

    // }

    public function getDiagnosisLabel($drug_id)
    {
        $cached_data = Cache::get($this->cache_key);
        foreach($this->agreed_diagnoses as $diag_id=>$diagnosis){
            if (array_key_exists($drug_id, $diagnosis['drugs'])){
                // dd($cached_data['final_diagnoses'][$diagnosis['id']]['drugs'][$drug_id]);
                $drug_instance=$cached_data['final_diagnoses'][$diagnosis['id']]['drugs'][$drug_id];

                return $diagnosis['label'];
            }
        }
    }

    public function formatFormulation(int $drug_id, int $formulation_id)
    {
        // duration_per_drugs= fina diagnosis
        // pre_referral_duration= fina diagnosis
        // dd();

        $cached_data = Cache::get($this->cache_key);
        // dd();
        $health_cares = $cached_data['health_cares'];
        $drug = $health_cares[$drug_id];
        foreach($drug['formulations'] as $formulation){
            if($formulation['id'] === $formulation_id){
                return [
                    "drug_label"=>$drug['label']['en'],
                    "description"=>$drug['description']['en'],
                    "indication"=>$this->getDiagnosisLabel($drug_id),
                    "route"=>$formulation['administration_route_name']
                ];
                break;
            }
        }
        // return [
        //     "drug_label"=>$drug['label']['en'],
        //     "indication"=>$this->getDiagnosisLabel($drug_id)
        // ];
    }

    public function getFormulations()
    {
        $formulations=[];
        foreach ($this->drugs_formulation as $drug_id=>$formulation_id){
            $formulations[$drug_id]=$this->formatFormulation($drug_id,$formulation_id);
        }
        return $formulations;
    }

}
