@props(['current_step', 'saved_step', 'completion_per_step', 'chosen_complaint_categories'])

<section class="list-steps">
  <div class="steps">
    {{-- Registration --}}
    <div name="navstep" style="{{ $saved_step >= 1 ? 'cursor:pointer;' : 'cursor:default;' }}"
      wire:click="{{ $saved_step >= 1 ? 'goToStep(\'registration\')' : '' }}"
      class="step prevention-step{{ $current_step === 'registration' ? ' active' : '' }}{{ $saved_step > 1 ? ' success' : '' }}">
      @if ($saved_step > 1)
        <div class="success-icon">
          <div>
            <img src=" {{ mix('images/icons-done.svg') }}" />
          </div>
        </div>
      @endif

      <div class="single-chart">
        <svg viewBox="0 0 36 36" class="circular-chart">
          <path class="circle-bg" d="M18 2.0845
          a 15.9155 15.9155 0 0 1 0 31.831
          a 15.9155 15.9155 0 0 1 0 -31.831" />
          <path class="circle circle-animate circle-registration"
            stroke-dasharray="{{ $completion_per_step['registration']['end'] }}, 100" d="M18 2.0845
          a 15.9155 15.9155 0 0 1 0 31.831
          a 15.9155 15.9155 0
           0 1 0 -31.831" />
          <text x="18" y="20.35" class="percentage">{{ $completion_per_step['registration']['end'] }}%</text>
        </svg>
      </div>

      <div class="content">
        <span class="step-name">Step 1</span>
        <span class="step-category">Registration</span>
      </div>
    </div>

    {{-- CC but using first_look_assessment step --}}
    @if ($saved_step >= 2)
      <div name="navstep" style="{{ $saved_step >= 2 ? 'cursor:pointer;' : 'cursor:default;' }}"
        wire:click="goToStep('first_look_assessment')"
        class="step prevention-step{{ $current_step === 'first_look_assessment' ? ' active' : '' }}{{ $saved_step < 2 ? ' empty' : '' }}{{ $completion_per_step['first_look_assessment']['end'] >= 100 && $saved_step > 2 ? ' success' : '' }}">
      @else
        <div name="navstep" style="{{ $saved_step >= 2 ? 'cursor:pointer;' : 'cursor:default;' }}"
          class="step prevention-step{{ $current_step === 'first_look_assessment' ? ' active' : '' }}{{ $saved_step < 2 ? ' empty' : '' }}{{ $completion_per_step['first_look_assessment']['end'] >= 100 && $saved_step > 2 ? ' success' : '' }}">
    @endif

    @if ($saved_step > 2)
      <div class="success-icon">
        <div>
          <img src=" {{ mix('images/icons-done.svg') }}" />
        </div>
      </div>
    @endif
    <div class="single-chart">
      <svg viewBox="0 0 36 36" class="circular-chart">
        <path class="circle-bg" d="M18 2.0845
          a 15.9155 15.9155 0 0 1 0 31.831
          a 15.9155 15.9155 0 0 1 0 -31.831" />
        <path class="circle circle-animate circle-first_look_assessment"
          stroke-dasharray="{{ $completion_per_step['first_look_assessment']['end'] }}, 100" d="M18 2.0845
          a 15.9155 15.9155 0 0 1 0 31.831
          a 15.9155 15.9155 0 0 1 0 -31.831" />
        <text x="18" y="20.35" class="percentage">{{ $completion_per_step['first_look_assessment']['end'] }}%</text>
      </svg>
    </div>
    <div class="content">
      <span class="step-name">Step 2</span>
      <span class="step-category">Survey selection</span>
    </div>
  </div>

  {{-- Consultation --}}
  @if ($saved_step >= 3 && !empty(array_filter($chosen_complaint_categories)))
    <div name="navstep" style="{{ $saved_step >= 3 ? 'cursor:pointer;' : 'cursor:default;' }}"
      wire:click="goToSubStep('consultation', 'medical_history')"
      class="step prevention-step{{ $current_step === 'consultation' ? ' active' : '' }}{{ $saved_step < 3 ? ' empty' : '' }}{{ $completion_per_step['consultation']['end'] >= 100 && $saved_step > 3 ? ' success' : '' }}">
    @else
      <div name="navstep"
        style="{{ $saved_step >= 3 && !empty(array_filter($chosen_complaint_categories)) ? 'cursor:pointer;' : 'cursor:default;' }}"
        class="step prevention-step{{ $current_step === 'consultation' ? ' active' : '' }}{{ $saved_step < 3 ? ' empty' : '' }}{{ $completion_per_step['consultation']['end'] >= 100 && $saved_step > 3 ? ' success' : '' }}">
  @endif

  @if ($saved_step > 3)
    <div class="success-icon">
      <div>
        <img src=" {{ mix('images/icons-done.svg') }}" />
      </div>
    </div>
  @endif
  <div class="single-chart">
    <svg viewBox="0 0 36 36" class="circular-chart">
      <path class="circle-bg" d="M18 2.0845
          a 15.9155 15.9155 0 0 1 0 31.831
          a 15.9155 15.9155 0 0 1 0 -31.831" />
      <path class="circle circle-animate circle-consultation"
        stroke-dasharray="{{ $completion_per_step['consultation']['end'] }}, 100" d="M18 2.0845
          a 15.9155 15.9155 0 0 1 0 31.831
          a 15.9155 15.9155 0 0 1 0 -31.831" />
      <text x="18" y="20.35" class="percentage">{{ $completion_per_step['consultation']['end'] }}%</text>
    </svg>
  </div>
  <div class="content">
    <span class="step-name">Step 3</span>
    <span class="step-category">Surveys</span>
  </div>
  </div>

  {{-- Results but using diagnoses step --}}
  @if ($saved_step >= 4)
    <div name="navstep" style="{{ $saved_step >= 4 ? 'cursor:pointer;' : 'cursor:default;' }}"
      wire:click="goToSubStep('diagnoses', 'final_diagnoses')"
      class="step prevention-step{{ $current_step === 'diagnoses' ? ' active' : '' }}{{ $saved_step < 4 ? ' empty' : '' }}{{ $completion_per_step['diagnoses']['end'] >= 100 && $saved_step === 4 ? ' success' : '' }}">
    @else
      <div name="navstep" style="{{ $saved_step >= 4 ? 'cursor:pointer;' : 'cursor:default;' }}"
        class="step prevention-step{{ $current_step === 'diagnoses' ? ' active' : '' }}{{ $saved_step < 4 ? ' empty' : '' }}{{ $completion_per_step['diagnoses']['end'] >= 100 && $saved_step === 4 ? ' success' : '' }}">
  @endif

  @if ($saved_step === 4)
    <div class="success-icon">
      <div>
        <img src=" {{ mix('images/icons-done.svg') }}" />
      </div>
    </div>
  @endif
  <div class="single-chart">
    <svg viewBox="0 0 36 36" class="circular-chart">
      <path class="circle-bg" d="M18 2.0845
          a 15.9155 15.9155 0 0 1 0 31.831
          a 15.9155 15.9155 0 0 1 0 -31.831" />
      <path class="circle circle-animate circle-consultation"
        stroke-dasharray="{{ $completion_per_step['diagnoses']['end'] }}, 100" d="M18 2.0845
          a 15.9155 15.9155 0 0 1 0 31.831
          a 15.9155 15.9155 0 0 1 0 -31.831" />
      <text x="18" y="20.35" class="percentage">{{ $completion_per_step['diagnoses']['end'] }}%</text>
    </svg>
  </div>
  <div class="content">
    <span class="step-name">Step 4</span>
    <span class="step-category">Results</span>
  </div>
  </div>
</section>
