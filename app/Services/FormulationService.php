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
use Illuminate\Support\Facades\Log;

class FormulationService
{

    public array $agreed_diagnoses;
    public array $json_data;
    public array $drugs_duration;
    public array $current_drug;
    public array $current_formulation;
    public float $patient_weight;
    public array $drug_indications;


    public function __construct(array $json_data, array $agreed_diagnoses, float $weight)
    {
        $this->json_data = $json_data;
        $this->agreed_diagnoses = $agreed_diagnoses;
        $this->patient_weight = $weight;
        $this->calculateDrugDuration();
        $this->getDiagnosisLabel($agreed_diagnoses);
    }

    public function calculateDrugDuration()
    {
        foreach ($this->agreed_diagnoses as $diagnosis_id => $diagnosis) {
            foreach ($diagnosis['drugs']['proposed'] as $k => $drug_id) {
                $drug_instance = $this->json_data['algorithm']['nodes'][$diagnosis_id]['drugs'][$drug_id];
                if (boolval($drug_instance['is_pre_referral'])) {
                    $this->drugs_duration[$drug_id] = 'While arranging referral';
                    continue;
                }
                $duration = $drug_instance['duration']['en'];
                $this->drugs_duration[$drug_id] =  0;
                $parsedInteger = intval($duration, 10);
                if (is_string($this->drugs_duration[$drug_id])) {
                    $this->drugs_duration[$drug_id] = $parsedInteger;
                    continue;
                }
                if ($this->drugs_duration[$drug_id] < $parsedInteger) {
                    $this->drugs_duration[$drug_id] = $parsedInteger;
                }
            }
        }
    }

    public function getDiagnosisLabel($agreed_diagnoses)
    {
        foreach ($agreed_diagnoses as $diag_id => $diagnosis) {
            foreach ($diagnosis['drugs']['agreed'] ?? [] as $drug_id => $drug) {
                $indication = $this->json_data['algorithm']['nodes'][$diag_id]['label']['en'];
                if (!isset($this->drug_indications[$drug_id])) {
                    $this->drug_indications[$drug_id] = $indication;
                    continue;
                }
                $this->drug_indications[$drug_id] = $this->drug_indications[$drug_id] . ' | ' . $indication;
            }
        }
    }

    private function roundSup($n)
    {
        return round($n * 10) / 10;
    }

    public function doseCalculationString(string $keyString, $args = [])
    {
        $unit = 'ml';
        $value = floatval($this->current_formulation['unique_dose']) . $unit;
        if (isset($args) && array_key_exists('medication_form', $args)) {
            $unit = $this->current_formulation['medication_form'];
            $value = floatval($this->current_formulation['unique_dose']) . ' ' . $unit;
        }
        $string_0 = "Fixed dose " . $value . " per administration";
        $string_1 = "Fixed dose $value application per administration";

        $callStrings = [
            "fixed_dose_indication_administration" => $string_0,
            "fixed_dose_indication_application" => $string_1,
            "dose_indication" => isset($args['dosage']) && isset($args['patient_weight']) && isset($args['total'])
                ? "Dose range " . $args['dosage'] . " mg/kg X " . $args['patient_weight'] . " kg = " . $args['total'] . " mg"
                : null,
        ];
        return $callStrings[$keyString];
    }

    public function doseCalculation($drug_dose)
    {
        return match ($this->current_formulation['medication_form']) {
            'solution', 'suspension', 'syrup', 'powder_for_injection' => call_user_func(function () use ($drug_dose) {
                if ($drug_dose['uniqDose']) {
                    return $this->doseCalculationString("fixed_dose_indication_administration");
                }
                $dosage = number_format(($drug_dose['doseResultMg'] / $this->patient_weight), 1, '.', '');
                $total = number_format($drug_dose['doseResultMg'], 1, '.', '');
                $args = ['dosage' => $dosage, 'patient_weight' => $this->patient_weight, 'total' => $total];
                return $this->doseCalculationString("dose_indication", $args);
            }),
            'gel', 'ointment', 'cream', 'lotion', 'patch' => $this->doseCalculationString("fixed_dose_indication_application"),
            'drops', 'spray', 'suppository', 'pessary', 'inhaler' => $this->doseCalculationString("fixed_dose_indication_administration", ['medication_form' => 'medication_form']),
            'capsule', 'tablet', 'dispersible_tablet' => call_user_func(function () use ($drug_dose) {
                if ($drug_dose['uniqDose']) {
                    return $this->doseCalculationString("fixed_dose_indication_administration");
                }
                if ($this->current_formulation['medication_form'] == 'capsule') {
                    $currentDosage = $this->roundSup(
                        ($drug_dose['doseResultNotRounded'] * $drug_dose . ['dose_form']) / $this->patient_weight,
                    );
                } else {
                    $currentDosage = $this->roundSup(
                        ($drug_dose['doseResultNotRounded'] * ($this->current_formulation['dose_form'] / $this->current_formulation['breakable'])) /
                            $this->patient_weight,
                    );
                }
                $args = ['dosage' => $currentDosage, 'patient_weight' => $this->patient_weight, 'total' => $currentDosage * $this->patient_weight];
                return $this->doseCalculationString("dose_indication", $args);
            }),

            default => 'Please select a formulation'
        };
    }

    public function makeDrugDose()
    {
        $uniqDose = false;
        $recurrence = 24 / $this->current_formulation['doses_per_day'];
        $minimal_dose_per_kg = $this->current_formulation['minimal_dose_per_kg'];
        $doses_per_day = $this->current_formulation['doses_per_day'];
        $dose_form = $this->current_formulation['dose_form'];
        $liquid_concentration = $this->current_formulation['liquid_concentration'];
        $minDoseMg = $this->roundSup(($this->patient_weight * $minimal_dose_per_kg) / $doses_per_day);
        $maximal_dose_per_kg = $this->current_formulation['maximal_dose_per_kg'];
        $maxDoseMg = $this->roundSup(($this->patient_weight * $maximal_dose_per_kg) / $doses_per_day);

        if (!$this->current_formulation['by_age']) {
            return match ($this->current_formulation['medication_form']) {
                'suspension', 'syrup', 'powder_for_injection', 'ointment', 'solution' => call_user_func(function () use ($recurrence, $minDoseMg, $maxDoseMg, $dose_form, $liquid_concentration, $doses_per_day, $uniqDose) {
                    // Second calculate min and max dose (cap)
                    $minDoseMl = ($minDoseMg * $dose_form) / $liquid_concentration;
                    $maxDoseMl = ($maxDoseMg * $dose_form) / $liquid_concentration;
                    // Round
                    $doseResult = (($minDoseMl + $maxDoseMl) / 2);
                    $doseResult = ($doseResult > $maxDoseMl) ? ($doseResult - 1) : $doseResult;
                    $doseResultMg = ($doseResult * $liquid_concentration) / $dose_form;
                    // If we reach the limit / day
                    if ($doseResultMg * $doses_per_day > $this->current_formulation['maximal_dose']) {
                        $doseResultMg = $this->current_formulation['maximal_dose'] / $doses_per_day;
                        $doseResult = ($doseResultMg * $dose_form) / $liquid_concentration;
                    }
                    return  [
                        'minDoseMg' => $minDoseMg,
                        'maxDoseMg' => $maxDoseMg,
                        'minDoseMl' => $minDoseMl,
                        'maxDoseMl' => $maxDoseMl,
                        'doseResult' => $doseResult,
                        'doseResultMg' => $doseResultMg,
                        'recurrence' => $recurrence,
                        'uniqDose' => $uniqDose
                    ];
                }),
                'capsule', 'dispersible_tablet', 'tablet' => call_user_func(function () use ($recurrence, $minDoseMg, $maxDoseMg, $dose_form, $uniqDose) {
                    // First calculate min and max dose (mg/Kg)
                    $breakable = $this->current_formulation['breakable'];
                    $pillSize = $dose_form;
                    if ($breakable !== null) $pillSize /= $this->current_formulation['breakable'];
                    // Second calculate min and max dose (cap)
                    $minDoseCap = (1 / $pillSize) * $minDoseMg;
                    $maxDoseCap = (1 / $pillSize) * $maxDoseMg;
                    // Define Dose Result
                    $doseResult = ($minDoseCap + $maxDoseCap) / 2;

                    if ($maxDoseCap < 1) {
                        return [
                            'uniqDose' => $uniqDose,
                            'doseResult' => null,
                        ];
                    }
                    $doseResultNotRounded = $doseResult;
                    if (ceil($doseResult) <= $maxDoseCap) {
                        // Viable Solution
                        $doseResult = ceil($doseResult);
                    } elseif (floor($doseResult) >= $minDoseCap) {
                        // Other viable solution
                        $doseResult = floor($doseResult);
                    } else {
                        // Out of possibility
                        // Request on 09.02.2021 if no option available we give the min dose cap LIWI-1150
                        $doseResult = floor($minDoseCap);
                    }
                    return [
                        'minDoseMg' => $minDoseMg,
                        'maxDoseMg' => $maxDoseMg,
                        'minDoseCap' => $minDoseCap,
                        'maxDoseCap' => $maxDoseCap,
                        'doseResult' => $doseResult,
                        'doseResultNotRounded' => $doseResultNotRounded,
                        'recurrence' => $recurrence,
                        'uniqDose' => $uniqDose
                    ];
                }),
                default => [
                    'doseResult' => null,
                    'uniqDose' => true,
                    'recurrence' => $recurrence
                ],
            };
        }
        return [
            'doseResult' => null,
            'uniqDose' => true,
            'recurrence' => $recurrence
        ];
    }

    public function liquidAmountGiven()
    {
        if ($this->current_formulation['unique_dose']) {
            return 'Give ' . floatval($this->current_formulation['unique_dose']) . 'ml';
        }
        $administrationRouteName = strtolower($this->current_formulation['administration_route_name']);
        $substrings = ['im', 'iv', 'sc'];
        $medication_form = $this->current_formulation['medication_form'];
        foreach ($substrings as $substring) {
            if (strpos($administrationRouteName, $substring) !== false) {
                $drugDose = $this->makeDrugDose();
                $liquid_concentration = $this->current_formulation['liquid_concentration'];
                $dose_form = $this->current_formulation['dose_form'];
                return 'Give ' . $drugDose['doseResult'] . 'ml of ' . $this->roundSup($liquid_concentration) . 'mg/' . $dose_form . 'ml';
            }
        }
        return 'Give ' . $medication_form;
    }

    public function formatReadableFraction($fractionObject, $isImproper = false)
    {
        $denominator = $fractionObject['denominator'];
        $numerator = $fractionObject['numerator'];

        // When the numerator is 0, return an empty string instead of '0/denominator'.
        if ($numerator === 0) {
            return '';
        }

        // If the fraction is improper or the numerator is less than the denominator,
        // then we can do the easy thing and return numerator/denominator.
        if ($isImproper || $numerator < $denominator) {
            return "{$numerator}/{$denominator}";
        }

        // Grab the whole number.
        $wholeNumber = floor($numerator / $denominator);
        // Grab the remainder which will be the numerator in the remainder fraction.
        $remainder = $numerator % $denominator;
        // Same concept as above, don't show the remainder if the numerator is 0.
        $isRemainderShown = $remainder !== 0;

        return "{$wholeNumber}" . ($isRemainderShown ? " {$remainder}/{$denominator}" : '');
    }


    public function toReadableFraction($decimal, $shouldFormat = false)
    {
        // The decimal to convert.
        $startx = $decimal;
        // The maximum denominator.
        $maxDenominator = 10;
        $sign = 1;

        // Only work with positive numbers.
        if ($decimal < 0) {
            $sign = -1;
            $decimal *= -1;
        }

        // Create a matrix.
        // The numerator and denominator of the final fraction will be the
        // first column of the matrix ($matrix[0][0] and $matrix[1][0]).
        $matrix = [
            [1, 0],
            [0, 1],
        ];

        $x = $decimal;
        // $ai;
        $count = 0;

        while ($matrix[1][0] * ($ai = floor($x)) + $matrix[1][1] <= $maxDenominator) {
            // Don't let it loop too long.
            if (++$count > 50) {
                break;
            }

            $term = $matrix[0][0] * $ai + $matrix[0][1];
            $matrix[0][1] = $matrix[0][0];
            $matrix[0][0] = $term;
            $term = $matrix[1][0] * $ai + $matrix[1][1];
            $matrix[1][1] = $matrix[1][0];
            $matrix[1][0] = $term;

            // Don't divide by zero.
            if ($x === $ai) {
                break;
            }
            $x = 1 / abs($x - $ai);
        }

        $numerator = $matrix[0][0];
        // If the decimal argument was negative, make sure we return a negative fraction.
        $numerator *= $sign;
        $denominator = $matrix[1][0];
        $error = $startx - $matrix[0][0] / $matrix[1][0];

        $fractionObject = [
            'denominator' => $denominator,
            'error' => $error,
            'numerator' => $numerator,
        ];

        if ($shouldFormat) {
            return $this->formatReadableFraction($fractionObject);
        }

        return $fractionObject;
    }

    public function fractionUnicode($numerator, $denominator)
    {
        // Define Unicode fraction characters for 1 to 9
        $unicodeFractions = ['¹', '²', '³', '⁴', '⁵', '⁶', '⁷', '⁸', '⁹'];

        // Convert numerator digits to Unicode
        $unicodeNumerator = '';
        foreach (str_split($numerator) as $digit) {
            // $unicodeNumerator .= $unicodeFractions[$digit - 1];
            if ($digit == 0) {
                // Use a different character (e.g., '⁰') to represent zero
                $unicodeNumerator .= '⁰';
            } else {
                $unicodeNumerator .= $unicodeFractions[$digit - 1];
            }
        }

        // Get Unicode character for denominator
        $unicodeDenominator = $unicodeFractions[$denominator - 1];

        // Return the fraction in Unicode form
        return "$unicodeNumerator/$unicodeDenominator";
    }

    public function breakableFraction($drugDose)
    {
        $result = '';
        $readableFraction = '';
        $humanReadableFraction = '';
        $numberOfFullSolid = 0;

        if ($drugDose['doseResult'] !== null) {
            // Avoid everything for capsule
            if ($this->current_formulation['medication_form']  === 'capsule') {
                return [
                    'fractionString' => $drugDose['doseResult'],
                    'numberOfFullSolid' => $drugDose['doseResult'],
                ];
            }

            $numberOfFullSolid = floor($drugDose['doseResult'] / $this->current_formulation['breakable']);

            // Less than one solid
            if ($numberOfFullSolid === 0) {
                $readableFraction = $this->toReadableFraction($drugDose['doseResult'] / $this->current_formulation['breakable']);
            } else {
                $result = $numberOfFullSolid;

                // More than one solid
                $readableFraction = $this->toReadableFraction(
                    ($drugDose['doseResult'] - $numberOfFullSolid * $this->current_formulation['breakable']) / $this->current_formulation['breakable']
                );
            }

            // Generate human readable fraction
            $humanReadableFraction = $this->fractionUnicode(
                $readableFraction['numerator'],
                $readableFraction['denominator']
            );

            if ($readableFraction['numerator'] == 0 || $readableFraction['denominator'] == 0) {
                $result .= '';
            } elseif ($readableFraction['denominator'] == 1) {
                $result .= $readableFraction['numerator'];
            } else {
                $result .= $humanReadableFraction;
            }
        }
        return ['fractionString' => $result, 'numberOfFullSolid' => $numberOfFullSolid];
    }

    public function oralAmountGiven()
    {
        $drugDose = $this->makeDrugDose();
        $unique_dose = $this->current_formulation['unique_dose'];
        $medication_form = $this->current_formulation['medication_form'];
        if ($unique_dose) {
            if ($unique_dose > 1) {
                return 'Give ' . floatval($this->current_formulation['unique_dose']) . ' ' . $medication_form . 's';
            }
            return 'Give ' . floatval($this->current_formulation['unique_dose']) . ' ' . $medication_form;
        }
        $fractionString = $this->breakableFraction($drugDose)['fractionString'];
        $numberOfFullSolid = $this->breakableFraction($drugDose)['numberOfFullSolid'];
        $amount_give = $medication_form === 'capsule' ? $numberOfFullSolid : $fractionString;
        return 'Give ' . $amount_give . ' ' . $medication_form;
    }

    public function amountGivenSyrup()
    {
        $drugDose = $this->makeDrugDose();
        $unique_dose = $this->current_formulation['unique_dose'];
        $medication_form = $this->current_formulation['medication_form'];
        if ($unique_dose) {
            return 'Give ' . floatval($this->current_formulation['unique_dose']) . 'ml ';
        }
        return 'Give ' . $this->roundSup($drugDose['doseResult']) . ' ml';
    }

    public function getAmountGiven()
    {
        return match ($this->current_formulation['medication_form']) {
            'gel', 'ointment', 'cream', 'lotion', 'patch' => "Give " . floatval($this->current_formulation['unique_dose']) . " application per administration",
            'suppository', 'drops' => 'Give ' . floatval($this->current_formulation['unique_dose']) . ' ' . $this->current_formulation['medication_form'],
            'pessary', 'spray' => 'Give ' . floatval($this->current_formulation['unique_dose']) . '' . $this->current_formulation['medication_form'] . ' per administration',
            'inhaler' => 'Give ' . floatval($this->current_formulation['unique_dose']) . ' inhalation per administration',
            'suspension', 'powder_for_injection', 'solution' => $this->liquidAmountGiven(),
            'capsule', 'tablet', 'dispersible_tablet' => $this->oralAmountGiven(),
            'syrup' => $this->amountGivenSyrup(),
            default => 'Please select a formulation',
        };
    }

    public function formatFormulation(int $drug_id, int $formulation_id)
    {

        $health_cares = $this->json_data['algorithm']['nodes'];
        $this->current_drug = $health_cares[$drug_id];
        foreach ($this->current_drug['formulations'] as $formulation) {
            if ($formulation['id'] === $formulation_id) {
                $this->current_formulation = $formulation;
                $drug_dose = $this->makeDrugDose();
                return [
                    "drug_label" => $this->current_drug['label']['en'],
                    "description" => $this->current_drug['description']['en'],
                    "indication" => $this->drug_indications[$drug_id],
                    "route" => $formulation['administration_route_name'],
                    "amountGiven" => $this->getAmountGiven(),
                    "duration" => $this->drugs_duration[$drug_id],
                    "doses_per_day" => $this->current_formulation["doses_per_day"],
                    "dose_calculation" => $this->doseCalculation($drug_dose),
                    "injection_instructions" => $this->current_formulation["injection_instructions"]['en'],
                    "dispensing_description" => $this->current_formulation["dispensing_description"]['en'],
                    "recurrence" => $drug_dose["recurrence"]
                ];
            }
        }
    }

    public function getFormulations()
    {
        foreach ($this->agreed_diagnoses as $dd_id => $agreed_diagnosis) {
            foreach ($agreed_diagnosis['drugs']['agreed'] ?? [] as $drug_id => $drug) {
                $this->agreed_diagnoses[$dd_id]['drugs']['agreed'][$drug_id] = array_replace(
                    $this->agreed_diagnoses[$dd_id]['drugs']['agreed'][$drug_id],
                    $this->formatFormulation($drug_id, $drug['formulation_id'])
                );
            }
        }
        return $this->agreed_diagnoses;
    }

    /**
     * Formulation display in medication selection
     * @param array $drug_dose
     * @return string
     */
    public function formulationLabel($drug_dose)
    {
        // Early return if there are no options available
        if ($drug_dose['doseResult'] === null && !$drug_dose['uniqDose']) {
            return "{$drug_dose['to_display']}: " . __('reader.formulations.drug.no_options');
        }

        // Normal behavior
        switch ($drug_dose['medication_form']) {
            case config('medal.medication_forms.gel'):
            case config('medal.medication_forms.ointment'):
            case config('medal.medication_forms.cream'):
            case config('medal.medication_forms.lotion'):
            case config('medal.medication_forms.spray'):
            case config('medal.medication_forms.patch'):
                return "{$drug_dose['to_display']}: " . __('reader.formulations.drug.per_application', ['count' => floatval($drug_dose['unique_dose'])]);

            case config('medal.medication_forms.suppository'):
            case config('medal.medication_forms.drops'):
            case config('medal.medication_forms.pessary'):
                return "{$drug_dose['to_display']}: " . __('reader.formulations.medication_form.' . $drug_dose['medication_form'], ['count' => floatval($drug_dose['unique_dose'])]);

            case config('medal.medication_forms.inhaler'):
                return "{$drug_dose['to_display']}: " . __('reader.formulations.drug.per_administration', [
                    'suffix' => __('reader.formulations.medication_form.' . $drug_dose['medication_form'], ['count' => floatval($drug_dose['unique_dose'])])
                ]);

            case config('medal.medication_forms.solution'):
            case config('medal.medication_forms.suspension'):
                if ($drug_dose['uniqDose']) {
                    return "{$drug_dose['to_display']}: " . floatval($drug_dose['unique_dose']) . "ml";
                }

                if (in_array(strtolower($drug_dose['administration_route_name']), ['im', 'iv', 'sc'])) {
                    return $drug_dose['to_display'];
                }

                return "{$drug_dose['to_display']}: " . $this->roundSup($drug_dose['doseResult']) . "ml";

            case config('medal.medication_forms.capsule'):
            case config('medal.medication_forms.dispersible_tablet'):
            case config('medal.medication_forms.tablet'):
                if ($drug_dose['uniqDose']) {
                    return "{$drug_dose['to_display']}: " . __('reader.formulations.medication_form.' . $drug_dose['medication_form'], [
                        'count' => floatval($drug_dose['unique_dose']),
                        'fraction' => floatval($drug_dose['unique_dose'])
                    ]);
                }

                $breakable_result = $this->breakableFraction($drug_dose);
                $fraction_string = $breakable_result['fractionString'];
                $number_of_full_solid = $breakable_result['numberOfFullSolid'];

                // Handle translation for fraction
                $fraction_translated = __('reader.formulations.medication_form.' . $drug_dose['medication_form'], [
                    'count' => $number_of_full_solid,
                    'fraction' => $fraction_string,
                    'context' => (string)$number_of_full_solid,
                ]);

                return "{$drug_dose['to_display']}: " . $fraction_translated;

            case config('medal.medication_forms.powder_for_injection'):
                return $drug_dose['to_display'];

            case config('medal.medication_forms.syrup'):
                $dose = $drug_dose['uniqDose'] ? $drug_dose['unique_dose'] : $drug_dose['doseResult'];
                return "{$drug_dose['to_display']}: " . $this->roundSup($dose) . "ml";

            default:
                return "({$drug_dose['medication_form']}) " . __('reader.formulations.drug.medication_form_not_handled');
        }
    }
}
