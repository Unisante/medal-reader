<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Refacto;
use Carbon\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class SetDiagnosesTest extends TestCase
{
    /** @test */
    public function it_should_include_uncomplicated_lymphadenopathy_most()
    {
        $today = Carbon::now()->addMinute();
        $date_to_check = $today->subYears(7);

        Livewire::test(Refacto::class, [
            'id' => 0,
            'patient_id' => null,
            'data' => [],
        ])
            ->call('debugUpdatingCurrentNodesRegistrationBirthDate', $date_to_check)
            ->set('current_nodes.registration.214', 394)  // I'm a male
            ->call('debugUpdatingCurrentNodes', 'medical_case.nodes.214', 394)
            ->set('current_nodes.first_look_assessment.basic_measurements_nodes_id.3', 10)  // Weight
            ->call('debugUpdatingCurrentNodes', 'medical_case.nodes.3', 10)

            ->call('debugUpdatingCurrentNodes', 'medical_case.nodes.12', 20) // CC Ear / Throat / Mouth => Yes
            ->call('debugUpdatingCurrentNodes', 'medical_case.nodes.203', 381) // Neck mass => Yes
            ->call('debugUpdatingCurrentNodes', 'medical_case.nodes.1703', 764) // Duration of neck mass >= 4 weeks => No
            ->call('debugUpdatingCurrentNodes', 'medical_case.nodes.204', 2) // Size of neck mass => < 3 cm
            ->call('debugUpdatingCurrentNodes', 'medical_case.nodes.205', 386) // Local tenderness of neck mass => No
            ->call('goToSubStep', 'diagnoses', 'final_diagnoses')

            ->assertSet('current_nodes.diagnoses.excluded.208', 208) // Uncomplicated infectious lymphadenopathy
            ->assertSee('Uncomplicated lymphadenopathy');
    }

    /** @test */
    public function it_should_not_include_uncomplicated_lymphadenopathy_if_cc_not_checked()
    {
        $today = Carbon::now()->addMinute();
        $date_to_check = $today->subYears(7);

        Livewire::test(Refacto::class, [
            'id' => 0,
            'patient_id' => null,
            'data' => [],
        ])
            ->call('debugUpdatingCurrentNodesRegistrationBirthDate', $date_to_check)
            ->set('current_nodes.registration.214', 394)  // I'm a male
            ->call('debugUpdatingCurrentNodes', 'medical_case.nodes.214', 394)
            ->set('current_nodes.first_look_assessment.basic_measurements_nodes_id.3', 10)  // Weight
            ->call('debugUpdatingCurrentNodes', 'medical_case.nodes.3', 10)

            ->call('debugUpdatingCurrentNodes', 'medical_case.nodes.203', 381) // Neck mass => Yes
            ->call('debugUpdatingCurrentNodes', 'medical_case.nodes.1703', 764) // Duration of neck mass >= 4 weeks => No
            ->call('debugUpdatingCurrentNodes', 'medical_case.nodes.204', 2) // Size of neck mass => < 3 cm
            ->call('debugUpdatingCurrentNodes', 'medical_case.nodes.205', 386) // Local tenderness of neck mass => No
            ->call('goToSubStep', 'diagnoses', 'final_diagnoses')

            ->assertDontSee('Uncomplicated infectious lymphadenopathy') // 208
            ->assertDontSee('Uncomplicated lymphadenopathy'); // 209
    }

    public function test_includes_severe_pneumonia_in_basic_diagram_with_qs()
    {
        $today = Carbon::now()->addMinute();
        $date_to_check = $today->subYears(7);

        Livewire::test(Refacto::class, [
            'id' => 0,
            'patient_id' => null,
            'data' => [],
        ])
            ->call('debugUpdatingCurrentNodesRegistrationBirthDate', $date_to_check)
            ->set('current_nodes.registration.214', 394)  // I'm a male
            ->call('debugUpdatingCurrentNodes', 'medical_case.nodes.214', 394)
            ->set('current_nodes.first_look_assessment.basic_measurements_nodes_id.3', 10)  // Weight
            ->call('debugUpdatingCurrentNodes', 'medical_case.nodes.3', 10)

            ->call('debugUpdatingCurrentNodes', 'medical_case.nodes.39', 74) // Cough => Yes
            ->call('debugUpdatingCurrentNodes', 'medical_case.nodes.40', 76) // Difficulty Breathing => Yes
            ->call('debugUpdatingCurrentNodes', 'medical_case.nodes.2974', 2254) // Grunting => Yes
            ->call('debugUpdatingCurrentNodes', 'medical_case.nodes.86', 145) // Unconscious => Yes
            ->call('goToSubStep', 'diagnoses', 'final_diagnoses')

            ->assertSee('Severe pneumonia'); // 60
    }

    public function test_excludes_severe_pneumonia_in_top_level_exclusion()
    {
        $today = Carbon::now()->addMinute();
        $date_to_check = $today->subYears(7);

        Livewire::test(Refacto::class, [
            'id' => 0,
            'patient_id' => null,
            'data' => [],
        ])
            ->call('debugUpdatingCurrentNodesRegistrationBirthDate', $date_to_check)
            ->set('current_nodes.registration.214', 394)  // I'm a male
            ->call('debugUpdatingCurrentNodes', 'medical_case.nodes.214', 394)
            ->set('current_nodes.first_look_assessment.basic_measurements_nodes_id.3', 10)  // Weight
            ->call('debugUpdatingCurrentNodes', 'medical_case.nodes.3', 10)

            ->call('debugUpdatingCurrentNodes', 'medical_case.nodes.39', 75) // Cough => No
            ->call('debugUpdatingCurrentNodes', 'medical_case.nodes.40', 77) // Difficulty Breathing => No
            ->call('goToSubStep', 'diagnoses', 'final_diagnoses')

            ->assertDontSee('Severe pneumonia'); // 60
    }

    public function test_includes_common_cold_when_one_branch_is_true_and_other_is_false()
    {
        $today = Carbon::now()->addMinute();
        $date_to_check = $today->subYears(7);

        Livewire::test(Refacto::class, [
            'id' => 0,
            'patient_id' => null,
            'data' => [],
        ])
            ->call('debugUpdatingCurrentNodesRegistrationBirthDate', $date_to_check)
            ->set('current_nodes.registration.214', 394)  // I'm a male
            ->call('debugUpdatingCurrentNodes', 'medical_case.nodes.214', 394)
            ->set('current_nodes.first_look_assessment.basic_measurements_nodes_id.3', 10)  // Weight
            ->call('debugUpdatingCurrentNodes', 'medical_case.nodes.3', 10)
            ->call('debugUpdatingCurrentNodes', 'medical_case.nodes.39', 74) // Cough => Yes
            ->call('debugUpdatingCurrentNodes', 'medical_case.nodes.40', 77) // Difficulty Breathing => No
            ->call('debugUpdatingCurrentNodes', 'medical_case.nodes.1685', 752) // Convulsing now => No
            ->call('debugUpdatingCurrentNodes', 'medical_case.nodes.88', 150) // Convulsion in present illness => No
            ->call('debugUpdatingCurrentNodes', 'medical_case.nodes.86', 146) // Unconscious or Lethargic (Unusually sleepy) => No
            ->call('debugUpdatingCurrentNodes', 'medical_case.nodes.3184', 2342) // Vomiting everything => No
            ->call('debugUpdatingCurrentNodes', 'medical_case.nodes.278', 491) // Unable to drink or breastfeed => No
            ->call('debugUpdatingCurrentNodes', 'medical_case.nodes.199', 373) // Runny or blocked nose => No
            ->call('goToSubStep', 'diagnoses', 'final_diagnoses')
            ->call('debugUpdatedDiagnosesStatus', false, 60)
            ->call('debugUpdatedDiagnosesStatus', false, 83)
            ->call('debugUpdatedDiagnosesStatus', false, 116)
            ->call('debugUpdatedDiagnosesStatus', false, 123)
            ->call('debugUpdatedDiagnosesStatus', false, 3186)
            ->call('goToSubStep', 'diagnoses', 'final_diagnoses')

            ->assertSee('Common Cold (URTI)'); // 128
    }

    public function test_includes_bacterial_pneumonia_and_imci_pneumonia_with_two_final_diagnoses()
    {
        $today = Carbon::now()->addMinute();
        $date_to_check = $today->subYears(7);

        Livewire::test(Refacto::class, [
            'id' => 0,
            'patient_id' => null,
            'data' => [],
        ])
            ->call('debugUpdatingCurrentNodesRegistrationBirthDate', $date_to_check)
            ->set('current_nodes.registration.214', 394)  // I'm a male
            ->call('debugUpdatingCurrentNodes', 'medical_case.nodes.214', 394)
            ->set('current_nodes.first_look_assessment.basic_measurements_nodes_id.3', 10)  // Weight
            ->call('debugUpdatingCurrentNodes', 'medical_case.nodes.3', 10)
            ->call('debugUpdatingCurrentNodes', 'medical_case.nodes.39', 74) // Cough => Yes
            ->call('debugUpdatingCurrentNodes', 'medical_case.nodes.18', 26) // Chest indrawing => YES
            ->call('debugUpdatingCurrentNodes', 'medical_case.nodes.50', 39) // Axillary temperature => > 38
            ->call('debugUpdatingCurrentNodes', 'medical_case.nodes.1685', 752) // Convulsing now => No
            ->call('debugUpdatingCurrentNodes', 'medical_case.nodes.88', 150) // Convulsion in present illness => No
            ->call('debugUpdatingCurrentNodes', 'medical_case.nodes.86', 146) // Unconscious or Lethargic (Unusually sleepy) => No
            ->call('debugUpdatingCurrentNodes', 'medical_case.nodes.3184', 2342) // Vomiting everything => No
            ->call('debugUpdatingCurrentNodes', 'medical_case.nodes.278', 491) // Unable to drink or breastfeed => No
            ->call('debugUpdatingCurrentNodes', 'medical_case.nodes.122', 229) // CRP => Unavailable
            ->call('debugUpdatingCurrentNodes', 'medical_case.nodes.3545', 55) // Respiratory rate => Unavailable
            ->call('goToSubStep', 'diagnoses', 'final_diagnoses')

            ->call('debugUpdatedDiagnosesStatus', false, 60)
            ->call('goToSubStep', 'diagnoses', 'final_diagnoses')

            ->assertSet('medical_case.diagnoses.proposed.116', '')
            ->assertSet('medical_case.diagnoses.proposed.123', '');
        // ->assertSee('IMCI/IMAI pneumonia')
        // ->assertSee('Bacterial pneumonia');
    }

    public function test_does_not_propose_diagnosis_that_can_be_excluded()
    {
        $today = Carbon::now()->addMinute();
        $date_to_check = $today->subYears(7);

        Livewire::test(Refacto::class, [
            'id' => 0,
            'patient_id' => null,
            'data' => [],
        ])
            ->call('debugUpdatingCurrentNodesRegistrationBirthDate', $date_to_check)
            ->set('current_nodes.registration.214', 394)  // I'm a male
            ->call('debugUpdatingCurrentNodes', 'medical_case.nodes.214', 394)
            ->set('current_nodes.first_look_assessment.basic_measurements_nodes_id.3', 10)  // Weight
            ->call('debugUpdatingCurrentNodes', 'medical_case.nodes.3', 10)

            ->call('debugUpdatingCurrentNodes', 'medical_case.nodes.39', 74) // Cough => Yes
            ->call('debugUpdatingCurrentNodes', 'medical_case.nodes.18', 26) // Chest indrawing => YES
            ->call('debugUpdatingCurrentNodes', 'medical_case.nodes.50', 38) // Axillary temperature => > 38
            ->call('debugUpdatingCurrentNodes', 'medical_case.nodes.1685', 752) // Convulsing now => No
            ->call('debugUpdatingCurrentNodes', 'medical_case.nodes.88', 150) // Convulsion in present illness => No
            ->call('debugUpdatingCurrentNodes', 'medical_case.nodes.86', 146) // Unconscious or Lethargic (Unusually sleepy) => No
            ->call('debugUpdatingCurrentNodes', 'medical_case.nodes.3184', 2342) // Vomiting everything => No
            ->call('debugUpdatingCurrentNodes', 'medical_case.nodes.278', 491) // Unable to drink or breastfeed => No
            ->call('debugUpdatingCurrentNodes', 'medical_case.nodes.122', 230) // CRP => < 10
            ->call('goToSubStep', 'diagnoses', 'final_diagnoses')
            ->assertDontSee('Viral pneumonia') // 116

            ->call('debugUpdatedDiagnosesStatus', false, 60)
            ->call('goToSubStep', 'diagnoses', 'final_diagnoses')
            ->assertSee('Bacterial pneumonia') // 83

            ->call('debugUpdatedDiagnosesStatus', false, 83)
            ->call('debugUpdatedDiagnosesStatus', false, 123)
            ->call('goToSubStep', 'diagnoses', 'final_diagnoses')
            // ->assertDontSee('Bacterial pneumonia') // 83
            // ->assertDontSee('IMCI/IMAI pneumonia') // 123
            ->assertSet('medical_case.diagnoses.refused.83', '')
            ->assertSet('medical_case.diagnoses.refused.123', '')
            ->assertSet('medical_case.diagnoses.proposed.116', '');
        // ->assertSee('Viral pneumonia'); // 116
    }

    public function test_does_not_propose_excluded_final_diagnosis_when_agreeing_excluding_final_diagnosis()
    {
        $today = Carbon::now()->addMinute();
        $date_to_check = $today->subYears(7);

        Livewire::test(Refacto::class, [
            'id' => 0,
            'patient_id' => null,
            'data' => [],
        ])
            ->call('debugUpdatingCurrentNodesRegistrationBirthDate', $date_to_check)
            ->set('current_nodes.registration.214', 394)  // I'm a male
            ->call('debugUpdatingCurrentNodes', 'medical_case.nodes.214', 394)
            ->set('current_nodes.first_look_assessment.basic_measurements_nodes_id.3', 10)  // Weight
            ->call('debugUpdatingCurrentNodes', 'medical_case.nodes.3', 10)

            ->call('debugUpdatingCurrentNodes', 'medical_case.nodes.39', 74) // Cough => Yes
            ->call('debugUpdatingCurrentNodes', 'medical_case.nodes.18', 26) // Chest indrawing => YES
            ->call('debugUpdatingCurrentNodes', 'medical_case.nodes.50', 39) // Axillary temperature => > 38
            ->call('debugUpdatingCurrentNodes', 'medical_case.nodes.1685', 752) // Convulsing now => No
            ->call('debugUpdatingCurrentNodes', 'medical_case.nodes.88', 150) // Convulsion in present illness => No
            ->call('debugUpdatingCurrentNodes', 'medical_case.nodes.86', 146) // Unconscious or Lethargic (Unusually sleepy) => No
            ->call('debugUpdatingCurrentNodes', 'medical_case.nodes.3184', 2342) // Vomiting everything => No
            ->call('debugUpdatingCurrentNodes', 'medical_case.nodes.278', 491) // Unable to drink or breastfeed => No
            ->call('debugUpdatingCurrentNodes', 'medical_case.nodes.122', 230) // CRP => < 10
            ->call('goToSubStep', 'diagnoses', 'final_diagnoses')
            ->assertDontSee('Viral pneumonia') //116

            ->call('debugUpdatedDiagnosesStatus', false, 60)
            ->call('goToSubStep', 'diagnoses', 'final_diagnoses')
            ->assertSee('Bacterial pneumonia') // 83

            ->call('debugUpdatedDiagnosesStatus', true, 83)
            ->call('debugUpdatedDiagnosesStatus', false, 123)
            ->call('goToSubStep', 'diagnoses', 'final_diagnoses')

            ->assertSet('medical_case.diagnoses.proposed.116', '');
    }

    public function test_includes_complicated_severe_acute_malnutrition_in_reference_table()
    {
        $today = Carbon::now()->addMinute();
        $date_to_check = $today->subYears(7);

        Livewire::test(Refacto::class, [
            'id' => 0,
            'patient_id' => null,
            'data' => [],
        ])
            ->call('debugUpdatingCurrentNodesRegistrationBirthDate', $date_to_check)
            ->set('current_nodes.registration.214', 394)  // I'm a male
            ->call('debugUpdatingCurrentNodes', 'medical_case.nodes.214', 394)
            ->set('current_nodes.first_look_assessment.basic_measurements_nodes_id.3', 10)  // Weight
            ->call('debugUpdatingCurrentNodes', 'medical_case.nodes.3', 10)

            ->call('debugUpdatingCurrentNodes', 'medical_case.nodes.97', 10) // Muac => 10
            ->call('debugUpdatingCurrentNodes', 'medical_case.nodes.461', 725) // Cc General 2mois-5ans => Yes
            ->call('debugUpdatingCurrentNodes', 'medical_case.nodes.3184', 2341) // Vomiting everything => Yes
            ->call('debugUpdatingCurrentNodes', 'medical_case.nodes.86', 146) // Unconscious or Lethargic (Unusually sleepy) => No
            ->call('debugUpdatingCurrentNodes', 'medical_case.nodes.1685', 752) // Convulsing now => No
            ->call('debugUpdatingCurrentNodes', 'medical_case.nodes.22742', 26878) // Oral fluid test => Completely unable to drink
            ->call('goToSubStep', 'diagnoses', 'final_diagnoses')

            ->assertSee('Complicated severe acute malnutrition'); //3806
    }

    public function test_no_final_diagnosis_should_be_proposed_if_nothing_is_answered()
    {
        $today = Carbon::now()->addMinute();
        $date_to_check = $today->subYears(7);

        Livewire::test(Refacto::class, [
            'id' => 0,
            'patient_id' => null,
            'data' => [],
        ])
            ->call('debugUpdatingCurrentNodesRegistrationBirthDate', $date_to_check)
            ->set('current_nodes.registration.214', 394)  // I'm a male
            ->call('debugUpdatingCurrentNodes', 'medical_case.nodes.214', 394)
            ->set('current_nodes.first_look_assessment.basic_measurements_nodes_id.3', 10)  // Weight
            ->call('debugUpdatingCurrentNodes', 'medical_case.nodes.3', 10)
            ->call('goToSubStep', 'diagnoses', 'final_diagnoses')

            ->assertSee('Prevention and Screening') // 8781
            ->assertSee('Very low weight for age'); // 1667
    }

    public function test_should_propose_mci_imai_pneumonia_bug_on_issue_95()
    {
        $today = Carbon::now()->addMinute();
        $date_to_check = $today->copy()->subDays(1486);

        Livewire::test(Refacto::class, [
            'id' => 0,
            'patient_id' => null,
            'data' => [],
        ])
            ->call('debugUpdatingCurrentNodesRegistrationBirthDate', $date_to_check)
            ->set('current_nodes.registration.214', 394)  // I'm a male
            ->call('debugUpdatingCurrentNodes', 'medical_case.nodes.214', 394)
            ->set('current_nodes.first_look_assessment.basic_measurements_nodes_id.3', 10)  // Weight
            ->call('debugUpdatingCurrentNodes', 'medical_case.nodes.3', 10)

            ->call('debugUpdatingCurrentNodes', 'medical_case.nodes.40', 76) // Difficulty breathing => yes
            ->call('debugUpdatingCurrentNodes', 'medical_case.nodes.25', 36) // Fever within the last 2 days => yes
            ->call('debugUpdatingCurrentNodes', 'medical_case.nodes.42', 5) // Duration of fever => yes

            ->assertSet('medical_case.nodes.38.answer', 72)

            ->call('debugUpdatingCurrentNodes', 'medical_case.nodes.1685', 752) // Convulsing now => No
            ->call('debugUpdatingCurrentNodes', 'medical_case.nodes.88', 150) // Convulsion in present illness => No
            ->call('debugUpdatingCurrentNodes', 'medical_case.nodes.86', 146) // Unconscious or Lethargic (Unusually sleepy) => No
            ->call('debugUpdatingCurrentNodes', 'medical_case.nodes.3184', 2342) // Vomiting everything => No
            ->call('debugUpdatingCurrentNodes', 'medical_case.nodes.278', 491) // Unable to drink or breastfeed => No

            ->assertSet('medical_case.nodes.6494.answer', 5180)

            ->call('debugUpdatingCurrentNodes', 'medical_case.nodes.122', 229) // CRP => Unavailable
            ->call('debugUpdatingCurrentNodes', 'medical_case.nodes.3545', 3155) // Respiratory rate => Unavailable
            ->call('debugUpdatingCurrentNodes', 'medical_case.nodes.4530', 3182) // Visible respiratory rate => Visibly fast
            ->call('goToSubStep', 'diagnoses', 'final_diagnoses')

            ->assertSee('IMCI/IMAI pneumonia'); // 123
    }

    public function test_should_propose_viral_acute_pharyngitis_with_qss_to_false()
    {
        $today = Carbon::now()->addMinute();
        $date_to_check = $today->copy()->subDays(2337);

        Livewire::test(Refacto::class, [
            'id' => 0,
            'patient_id' => null,
            'data' => [],
        ])
            ->call('debugUpdatingCurrentNodesRegistrationBirthDate', $date_to_check)
            ->set('current_nodes.registration.214', 394)  // I'm a male
            ->call('debugUpdatingCurrentNodes', 'medical_case.nodes.214', 394)
            ->set('current_nodes.first_look_assessment.basic_measurements_nodes_id.3', 10)  // Weight
            ->call('debugUpdatingCurrentNodes', 'medical_case.nodes.3', 10)

            ->call('debugUpdatingCurrentNodes', 'medical_case.nodes.12', 20) // CC Ear / Throat / Mouth => Yes
            ->call('debugUpdatingCurrentNodes', 'medical_case.nodes.39', 74) // Cough To Yes
            ->call('debugUpdatingCurrentNodes', 'medical_case.nodes.183', 345) // Sore Throat => Yes
            ->call('debugUpdatingCurrentNodes', 'medical_case.nodes.199', 372) // Runny or Blocked nose => Yes
            ->call('debugUpdatingCurrentNodes', 'medical_case.nodes.1751', 807) // Tonsillar swelling => Present
            ->call('debugUpdatingCurrentNodes', 'medical_case.nodes.1752', 810) // Tonsillar exudate => Absent
            ->call('goToSubStep', 'diagnoses', 'final_diagnoses')

            ->assertSee('Viral acute pharyngitis'); // 191
    }
}
