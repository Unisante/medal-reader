@props(['current_cc', 'completion_per_substep', 'cc', 'full_nodes', 'index'])

<section class="list-steps">
  <div class="steps">
    <div name="navstep" style="cursor:pointer;" wire:click="goToCc({{ $cc }})"
      class="step prevention-substep{{ $cc === $current_cc ? ' active' : '' }}{{ $completion_per_substep[$cc]['end'] >= 100 ? ' success' : '' }}">

      @if ($completion_per_substep[$cc] >= 100)
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
          <path class="circle circle-animate circle-substep-consultation-{{ $cc }}"
            stroke-dasharray="{{ $completion_per_substep[$cc]['end'] }}, 100" d="M18 2.0845
          a 15.9155 15.9155 0 0 1 0 31.831
          a 15.9155 15.9155 0 0 1 0 -31.831" />
          <text x="18" y="20.35" class="percentage">{{ $completion_per_substep[$cc]['end'] }}%</text>
        </svg>
      </div>
      <div class="content">
        <span class="step-name">Questionnaire {{ $index + 1 }}</span>
        <span class="step-category" style="overflow: hidden; white-space: nowrap; text-overflow: ellipsis;">
          {{ $full_nodes[$cc]['label']['en'] }}
        </span>
      </div>
    </div>
</section>
