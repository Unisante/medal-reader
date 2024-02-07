@props(['current_step', 'saved_step', 'completion_per_step'])

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
        <span class="step-category">Informations générales</span>
      </div>
    </div>

    {{-- CC but using first_look_assessment step --}}
    @if ($saved_step >= 2)
      <div name="navstep" style="{{ $saved_step >= 2 ? 'cursor:pointer;' : 'cursor:default;' }}"
        wire:click="goToStep('first_look_assessment')"
        class="step prevention-step{{ $current_step === 'first_look_assessment' ? ' active' : '' }}{{ $saved_step < 2 ? ' empty' : '' }}{{ $completion_per_step['first_look_assessment']['end'] >= 98 && $saved_step >= 2 ? ' success' : '' }}">
      @else
        <div name="navstep" style="{{ $saved_step >= 2 ? 'cursor:pointer;' : 'cursor:default;' }}"
          class="step prevention-step{{ $current_step === 'first_look_assessment' ? ' active' : '' }}{{ $saved_step < 2 ? ' empty' : '' }}{{ $completion_per_step['first_look_assessment']['end'] >= 98 && $saved_step >= 2 ? ' success' : '' }}">
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
      <span class="step-category">Choix des questionnaires</span>
    </div>
  </div>

  {{-- Consultation --}}
  @if ($saved_step >= 3)
    <div name="navstep" style="{{ $saved_step >= 3 ? 'cursor:pointer;' : 'cursor:default;' }}"
      wire:click="goToStep('consultation')"
      class="step prevention-step{{ $current_step === 'consultation' ? ' active' : '' }}{{ $saved_step < 3 ? ' empty' : '' }}{{ $completion_per_step['consultation']['end'] >= 98 && $saved_step >= 3 ? ' success' : '' }}">
    @else
      <div name="navstep" style="{{ $saved_step >= 3 ? 'cursor:pointer;' : 'cursor:default;' }}"
        class="step prevention-step{{ $current_step === 'consultation' ? ' active' : '' }}{{ $saved_step < 3 ? ' empty' : '' }}{{ $completion_per_step['consultation']['end'] >= 98 && $saved_step >= 3 ? ' success' : '' }}">
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
    <span class="step-category">Questionnaires</span>
  </div>
  </div>

  {{-- Results but using diagnoses step --}}
  @if ($saved_step >= 4)
    <div name="navstep" style="{{ $saved_step >= 4 ? 'cursor:pointer;' : 'cursor:default;' }}"
      wire:click="goToStep('diagnoses')"
      class="step prevention-step{{ $current_step === 'diagnoses' ? ' active' : '' }}{{ $saved_step < 4 ? ' empty' : '' }}{{ $completion_per_step['diagnoses']['end'] >= 100 && $saved_step >= 4 ? ' success' : '' }}">
    @else
      <div name="navstep" style="{{ $saved_step >= 4 ? 'cursor:pointer;' : 'cursor:default;' }}"
        class="step prevention-step{{ $current_step === 'diagnoses' ? ' active' : '' }}{{ $saved_step < 4 ? ' empty' : '' }}{{ $completion_per_step['diagnoses']['end'] >= 100 && $saved_step >= 4 ? ' success' : '' }}">
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
      <path class="circle circle-animate circle-diagnoses" stroke-dasharray="100, 100" d="M18 2.0845
          a 15.9155 15.9155 0 0 1 0 31.831
          a 15.9155 15.9155 0 0 1 0 -31.831" />
      <text x="18" y="20.35" class="percentage">100%</text>
    </svg>
  </div>
  <div class="content">
    <span class="step-name">Step 4</span>
    <span class="step-category">Résultat</span>
  </div>
  </div>
</section>
