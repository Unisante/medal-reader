@props(['current_step', 'saved_step', 'completion_per_step'])

<section class="list-steps">
  <div class="steps">
    {{-- Registration --}}
    <div name="navstep" style="{{ $saved_step >= 1 ? 'cursor:pointer;' : 'cursor:default;' }}"
      wire:click="{{ $saved_step >= 1 ? 'goToStep(\'registration\')' : '' }}"
      class="step dynamic-step{{ $current_step === 'registration' ? ' active' : '' }}{{ $saved_step > 1 ? ' success' : '' }}">
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
          a 15.9155 15.9155 0 0 1 0 -31.831" />
          <text x="18" y="20.35" class="percentage">{{ $completion_per_step['registration']['end'] }}%</text>
        </svg>
      </div>

      <div class="content">
        <span class="step-name">Step 1</span>
        <span class="step-category">Registration</span>
      </div>
    </div>

    {{-- First look assessment --}}
    @if ($saved_step >= 2)
      <div name="navstep" style="{{ $saved_step >= 2 ? 'cursor:pointer;' : 'cursor:default;' }}"
        wire:click="goToStep('first_look_assessment')"
        class="step dynamic-step{{ $current_step === 'first_look_assessment' ? ' active' : '' }}{{ $saved_step < 2 ? ' empty' : '' }}{{ $saved_step > 2 ? ' success' : '' }}">
      @else
        <div name="navstep" style="{{ $saved_step >= 2 ? 'cursor:pointer;' : 'cursor:default;' }}"
          class="step dynamic-step{{ $current_step === 'first_look_assessment' ? ' active' : '' }}{{ $saved_step < 2 ? ' empty' : '' }}{{ $saved_step > 2 ? ' success' : '' }}">
    @endif

    @if ($saved_step >= 2)
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
      <span class="step-category">First Look Assessment</span>
    </div>
  </div>

  {{-- Consultation --}}
  @if ($saved_step >= 3)
    <div name="navstep" style="{{ $saved_step >= 3 ? 'cursor:pointer;' : 'cursor:default;' }}"
      wire:click="goToStep('consultation')"
      class="step dynamic-step{{ $current_step === 'consultation' ? ' active' : '' }}{{ $saved_step < 3 ? ' empty' : '' }}{{ $saved_step > 3 ? ' success' : '' }}">
    @else
      <div name="navstep" style="{{ $saved_step >= 3 ? 'cursor:pointer;' : 'cursor:default;' }}"
        class="step dynamic-step{{ $current_step === 'consultation' ? ' active' : '' }}{{ $saved_step < 3 ? ' empty' : '' }}{{ $saved_step > 3 ? ' success' : '' }}">
  @endif

  @if ($saved_step >= 3)
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
    <span class="step-category">Consultation</span>
  </div>
  </div>

  {{-- Tests --}}
  @if ($saved_step >= 4)
    <div name="navstep" style="{{ $saved_step >= 4 ? 'cursor:pointer;' : 'cursor:default;' }}"
      wire:click="goToStep('tests')"
      class="step dynamic-step{{ $current_step === 'tests' ? ' active' : '' }}{{ $saved_step < 4 ? ' empty' : '' }}{{ $saved_step > 4 ? ' success' : '' }}">
    @else
      <div name="navstep" style="{{ $saved_step >= 4 ? 'cursor:pointer;' : 'cursor:default;' }}"
        class="step dynamic-step{{ $current_step === 'tests' ? ' active' : '' }}{{ $saved_step < 4 ? ' empty' : '' }}{{ $saved_step > 4 ? ' success' : '' }}">
  @endif

  @if ($saved_step >= 4)
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
      <path class="circle circle-animate circle-tests"
        stroke-dasharray="{{ $completion_per_step['tests']['end'] }}, 100" d="M18 2.0845
          a 15.9155 15.9155 0 0 1 0 31.831
          a 15.9155 15.9155 0 0 1 0 -31.831" />
      <text x="18" y="20.35" class="percentage">{{ $completion_per_step['tests']['end'] }}%</text>
    </svg>
  </div>
  <div class="content">
    <span class="step-name">Step 4</span>
    <span class="step-category">Tests</span>
  </div>
  </div>

  {{-- Diagnoses --}}
  @if ($saved_step >= 5)
    <div name="navstep" style="{{ $saved_step >= 5 ? 'cursor:pointer;' : 'cursor:default;' }}"
      wire:click="goToStep('diagnoses')"
      class="step dynamic-step{{ $current_step === 'diagnoses' ? ' active' : '' }}{{ $saved_step < 5 ? ' empty' : '' }}{{ $saved_step > 5 ? ' success' : '' }}">
    @else
      <div name="navstep" style="{{ $saved_step >= 5 ? 'cursor:pointer;' : 'cursor:default;' }}"
        class="step dynamic-step{{ $current_step === 'diagnoses' ? ' active' : '' }}{{ $saved_step < 5 ? ' empty' : '' }}{{ $saved_step > 5 ? ' success' : '' }}">
  @endif

  @if ($saved_step >= 5)
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
      <path class="circle circle-animate circle-diagnoses"
        stroke-dasharray="{{ $completion_per_step['diagnoses']['end'] }}, 100" d="M18 2.0845
          a 15.9155 15.9155 0 0 1 0 31.831
          a 15.9155 15.9155 0 0 1 0 -31.831" />
      <text x="18" y="20.35" class="percentage">{{ $completion_per_step['diagnoses']['end'] }}%</text>
    </svg>
  </div>
  <div class="content">
    <span class="step-name">Step 5</span>
    <span class="step-category">Diagnoses</span>
  </div>
  </div>
</section>
