<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Refacto;
use Carbon\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class RelatedQuestionsTest extends TestCase
{
    /** @test */
    public function can_set_id()
    {
        Livewire::test(Refacto::class, [
            'id' => 0,
            'patient_id' => null,
            'data' => [],
        ])->assertSet('id', 0);
    }

    /** @test */
    public function it_should_return_minus_one_for_muac_z_score()
    {
        $today = Carbon::today();
        $date_to_check = $today->subMonths(129);

        Livewire::test(Refacto::class, [
            'id' => 0,
            'patient_id' => null,
            'data' => [],
        ])
            ->call('debugUpdatingCurrentNodesRegistrationBirthDate', $date_to_check)
            ->set('current_nodes.registration.214', 393)  // I'm a female
            ->call('debugUpdatingCurrentNodes', 'current_nodes.registration.214', 393)
            ->call('debugUpdatingCurrentNodes', 'medical_case.nodes.97', 16.9) // my Muac 16.9 cm
            ->assertSet('medical_case.nodes.99.value', -1);
    }

    /** @test */
    public function it_should_update_date_formulas_on_set_birthday()
    {
        $today = Carbon::now()->addMinute();
        $date_to_check = $today->copy()->subDays(129);

        Livewire::test(Refacto::class, [
            'id' => 0,
            'patient_id' => null,
            'data' => [],
        ])
            ->call('debugUpdatingCurrentNodesRegistrationBirthDate', $date_to_check)
            ->assertSet('medical_case.nodes.2.value', '128');
    }

    /** @test */
    public function it_should_return_zero_on_weight_for_height_when_below_zero()
    {
        $today = Carbon::today();
        $date_to_check = $today->subMonths(129);

        Livewire::test(Refacto::class, [
            'id' => 0,
            'patient_id' => null,
            'data' => [],
        ])
            ->call('debugUpdatingCurrentNodesRegistrationBirthDate', $date_to_check)
            ->set('current_nodes.registration.214', 394) // I'm a male
            ->call('debugUpdatingCurrentNodes', 'medical_case.nodes.214', 394)
            ->call('debugUpdatingCurrentNodes', 'medical_case.nodes.2096', 66.4) // Height
            ->call('debugUpdatingCurrentNodes', 'medical_case.nodes.3', 7.3) // Weight
            ->assertSet('medical_case.nodes.2101.value', 0);
    }

    /** @test */
    public function it_should_return_zero_on_weight_for_height_above_zero()
    {
        Livewire::test(Refacto::class, [
            'id' => 0,
            'patient_id' => null,
            'data' => [],
        ])
            ->set('current_nodes.registration.214', 394) // I'm a male
            ->call('debugUpdatingCurrentNodes', 'medical_case.nodes.214', 394)
            ->call('debugUpdatingCurrentNodes', 'medical_case.nodes.2096', 66.4) // Height
            ->call('debugUpdatingCurrentNodes', 'medical_case.nodes.3', 7.9) // Weight
            ->assertSet('medical_case.nodes.2101.value', 0);
    }

    /** @test */
    public function it_should_return_minus_three_when_value_is_too_low_on_weight_for_height()
    {
        Livewire::test(Refacto::class, [
            'id' => 0,
            'patient_id' => null,
            'data' => [],
        ])
            ->set('current_nodes.registration.214', 393)  // I'm a female
            ->call('debugUpdatingCurrentNodes', 'medical_case.nodes.214', 393)
            ->call('debugUpdatingCurrentNodes', 'medical_case.nodes.2096', 47) // Height
            ->call('debugUpdatingCurrentNodes', 'medical_case.nodes.3', 2) // Weight
            ->assertSet('medical_case.nodes.2101.value', -3);
    }

    /** @test */
    public function it_should_return_three_when_value_is_too_high_on_weight_for_height()
    {
        Livewire::test(Refacto::class, [
            'id' => 0,
            'patient_id' => null,
            'data' => [],
        ])
            ->set('current_nodes.registration.214', 393)  // I'm a female
            ->call('debugUpdatingCurrentNodes', 'medical_case.nodes.214', 393)
            ->call('debugUpdatingCurrentNodes', 'medical_case.nodes.2096', 47) // Height
            ->call('debugUpdatingCurrentNodes', 'medical_case.nodes.3', 5) // Weight
            ->assertSet('medical_case.nodes.2101.value', 3);
    }
}
