<section class="list-steps">
  <div class="steps">

    <div name="navstep" style="{{ $savedStep >= 1 ? 'cursor:pointer;' : 'cursor:default;' }}"
      wire:click="{{ $savedStep >= 1 ? '$emit(\'goToStep\',1)' : '' }}" wire:dirty.class.remove="success"
      wire:target="evaluation.specifications"
      class="step{{ $currentStep === 1 ? ' active' : '' }}{{ $savedStep < 1 ? ' empty' : '' }}{{ $completion_per_step[0] >= 98 && $savedStep > 1 ? ' success' : '' }}">
      @if ($savedStep > 1)
        <div class="success-icon">
          <div>
            <img src=" {{ mix('images/icons-done.svg') }}" />
          </div>
        </div>
      @endif

      <div class="single-chart">
        <svg viewBox="0 0 36 36" class="circular-chart">
          <path class="circle-bg"
            d="M18 2.0845
          a 15.9155 15.9155 0 0 1 0 31.831
          a 15.9155 15.9155 0 0 1 0 -31.831" />
          <path class="circle circle-animate" stroke-dasharray="{{ $completion_per_step[0] }}, 100"
            d="M18 2.0845
          a 15.9155 15.9155 0 0 1 0 31.831
          a 15.9155 15.9155 0 0 1 0 -31.831" />
          <text x="18" y="20.35" class="percentage">{{ $completion_per_step[0] }}%</text>
        </svg>
      </div>

      <div class="content">
        <span class="step-name">{{ trans('form.steps_number.0') }}</span>
        <span class="step-category">{{ trans('form.steps.0') }}</span>
      </div>
    </div>

    <div name="navstep" style="{{ $savedStep >= 2 ? 'cursor:pointer;' : 'cursor:default;' }}"
      wire:click="{{ $savedStep >= 2 ? '$emit(\'goToStep\',2)' : '' }}"
      class="step{{ $currentStep === 2 ? ' active' : '' }}{{ $savedStep < 2 ? ' empty' : '' }}{{ $completion_per_step[1] >= 98 && $savedStep > 2 ? ' success' : '' }}">
      @if ($savedStep > 2)
        <div class="success-icon">
          <div>
            <img src=" {{ mix('images/icons-done.svg') }}" />
          </div>
        </div>
      @endif
      <div class="single-chart">
        <svg viewBox="0 0 36 36" class="circular-chart">
          <path class="circle-bg"
            d="M18 2.0845
          a 15.9155 15.9155 0 0 1 0 31.831
          a 15.9155 15.9155 0 0 1 0 -31.831" />
          <path class="circle circle-animate" stroke-dasharray="{{ $completion_per_step[1] }}, 100"
            d="M18 2.0845
          a 15.9155 15.9155 0 0 1 0 31.831
          a 15.9155 15.9155 0 0 1 0 -31.831" />
          <text x="18" y="20.35" class="percentage">{{ $completion_per_step[1] }}%</text>
        </svg>
      </div>
      <div class="content">
        <span class="step-name">{{ trans('form.steps_number.1') }}</span>
        <span class="step-category">{{ trans('form.steps.1') }}</span>
      </div>
    </div>

    <div name="navstep" style="{{ $savedStep >= 3 ? 'cursor:pointer;' : 'cursor:default;' }}"
      wire:click="{{ $savedStep >= 3 ? '$emit(\'goToStep\',3)' : '' }}"
      class="step{{ $currentStep === 3 ? ' active' : '' }}{{ $savedStep < 3 ? ' empty' : '' }}{{ $completion_per_step[2] >= 98 && $savedStep > 3 ? ' success' : '' }}">
      @if ($savedStep > 3)
        <div class="success-icon">
          <div>
            <img src=" {{ mix('images/icons-done.svg') }}" />
          </div>
        </div>
      @endif
      <div class="single-chart">
        <svg viewBox="0 0 36 36" class="circular-chart">
          <path class="circle-bg"
            d="M18 2.0845
          a 15.9155 15.9155 0 0 1 0 31.831
          a 15.9155 15.9155 0 0 1 0 -31.831" />
          <path class="circle circle-animate" stroke-dasharray="{{ $completion_per_step[2] }}, 100"
            d="M18 2.0845
          a 15.9155 15.9155 0 0 1 0 31.831
          a 15.9155 15.9155 0 0 1 0 -31.831" />
          <text x="18" y="20.35" class="percentage">{{ $completion_per_step[2] }}%</text>
        </svg>
      </div>
      <div class="content">
        <span class="step-name">{{ trans('form.steps_number.2') }}</span>
        <span class="step-category">{{ trans('form.steps.2') }}</span>
      </div>
    </div>

    <div name="navstep" style="{{ $savedStep >= 4 ? 'cursor:pointer;' : 'cursor:default;' }}"
      wire:click="{{ $savedStep >= 4 ? '$emit(\'goToStep\',4)' : '' }}"
      class="step{{ $currentStep === 4 ? ' active' : '' }}{{ $savedStep < 4 ? ' empty' : '' }}{{ $completion_per_step[3] >= 98 && $savedStep > 4 ? ' success' : '' }}">

      @if ($savedStep > 4)
        <div class="success-icon">
          <div>
            <img src=" {{ mix('images/icons-done.svg') }}" />
          </div>
        </div>
      @endif
      <div class="single-chart">
        <svg viewBox="0 0 36 36" class="circular-chart">
          <path class="circle-bg"
            d="M18 2.0845
          a 15.9155 15.9155 0 0 1 0 31.831
          a 15.9155 15.9155 0 0 1 0 -31.831" />
          <path class="circle circle-animate" stroke-dasharray="{{ $completion_per_step[3] }}, 100"
            d="M18 2.0845
          a 15.9155 15.9155 0 0 1 0 31.831
          a 15.9155 15.9155 0 0 1 0 -31.831" />
          <text x="18" y="20.35" class="percentage">{{ $completion_per_step[3] }}%</text>
        </svg>
      </div>
      <div class="content">
        <span class="step-name">{{ trans('form.steps_number.3') }}</span>
        <span class="step-category">{{ trans('form.steps.3') }}</span>
      </div>
    </div>

    <div name="navstep" style="{{ $savedStep >= 5 ? 'cursor:pointer;' : 'cursor:default;' }}"
      wire:click="{{ $savedStep >= 5 ? '$emit(\'goToStep\',5)' : '' }}"
      class="step{{ $currentStep === 5 ? ' active' : '' }}{{ $savedStep < 5 ? ' empty' : '' }}{{ $completion_per_step[4] >= 98 && $savedStep > 5 ? ' success' : '' }}">
      @if ($savedStep > 5)
        <div class="success-icon">
          <div>
            <img src=" {{ mix('images/icons-done.svg') }}" />
          </div>
        </div>
      @endif
      <div class="single-chart">
        <svg viewBox="0 0 36 36" class="circular-chart">
          <path class="circle-bg"
            d="M18 2.0845
          a 15.9155 15.9155 0 0 1 0 31.831
          a 15.9155 15.9155 0 0 1 0 -31.831" />
          <path class="circle circle-animate" stroke-dasharray="{{ $completion_per_step[4] }}, 100"
            d="M18 2.0845
          a 15.9155 15.9155 0 0 1 0 31.831
          a 15.9155 15.9155 0 0 1 0 -31.831" />
          <text x="18" y="20.35" class="percentage">{{ $completion_per_step[4] }}%</text>
        </svg>
      </div>
      <div class="content">
        <span class="step-name">{{ trans('form.steps_number.4') }}</span>
        <span class="step-category">{{ trans('form.steps.4') }}</span>
      </div>
    </div>

    <div name="navstep" style="{{ $savedStep >= 6 ? 'cursor:pointer;' : 'cursor:default;' }}"
      wire:click="{{ $savedStep >= 6 ? '$emit(\'goToStep\',6)' : '' }}"
      class="step{{ $currentStep === 6 ? ' active' : '' }}{{ $savedStep < 6 ? ' empty' : '' }}{{ $completion_per_step[5] >= 98 && $savedStep > 6 ? ' success' : '' }}">
      @if ($savedStep > 6)
        <div class="success-icon">
          <div>
            <img src=" {{ mix('images/icons-done.svg') }}" />
          </div>
        </div>
      @endif
      <div class="single-chart">
        <svg viewBox="0 0 36 36" class="circular-chart">
          <path class="circle-bg"
            d="M18 2.0845
          a 15.9155 15.9155 0 0 1 0 31.831
          a 15.9155 15.9155 0 0 1 0 -31.831" />
          <path class="circle circle-animate" stroke-dasharray="{{ $completion_per_step[5] }}, 100"
            d="M18 2.0845
          a 15.9155 15.9155 0 0 1 0 31.831
          a 15.9155 15.9155 0 0 1 0 -31.831" />
          <text x="18" y="20.35" class="percentage">{{ $completion_per_step[5] }}%</text>
        </svg>
      </div>
      <div class="content">
        <span class="step-name">{{ trans('form.steps_number.5') }}</span>
        <span class="step-category">{{ trans('form.steps.5') }}</span>
      </div>
    </div>
  </div>
</section>
