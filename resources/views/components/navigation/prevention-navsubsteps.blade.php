@props(['current_step', 'saved_step', 'completion_per_step'])

<section class="list-steps">
  <div class="steps">
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
      <span class="step-category">Choix des questionnaires</span>
    </div>
  </div>
</section>
