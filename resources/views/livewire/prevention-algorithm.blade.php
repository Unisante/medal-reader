<div class="mb-5">
  <div class="d-flex justify-content-between">
    <div>
      <h2 class="fw-normal">{{ $title }}</h2>
    </div>
    <div class="form-check">
      <input class="form-check-input" type="checkbox" wire:model.live="debug_mode" value="" id="enable_debug" checked>
      <label class="form-check-label" for="enable_debug">
        Enable debug mode
      </label>
    </div>
  </div>
  @php
    $cache = Cache::get($cache_key);
    $full_nodes = $cache['full_nodes'];
    $final_diagnoses = $cache['final_diagnoses'];
    $health_cares = $cache['health_cares'];
  @endphp

  <x-navigation.prevention-navsteps :$current_step :$saved_step :$completion_per_step :$chosen_complaint_categories />

  {{-- Registration --}}
  @if (array_key_exists('first_look_assessment', $current_nodes))
    @if ($current_step === 'registration')
      <div class="row g-3 mt-3">
        <div class="col-8">
          <x-step.registration :nodes="$current_nodes['registration']" :$nodes_to_save :$full_nodes :$cache_key :$algorithm_type
            :$debug_mode />
        </div>
      </div>
    @endif
  @endif

  {{-- first_look_assessment --}}
  @if ($current_step === 'first_look_assessment')
    <div class="row g-3 mt-3">
      <div class="col-8 pe-5">
        <h2 class="fw-normal pb-3">Choix des Questionnaires</h2>
        @foreach ($current_nodes['first_look_assessment']['complaint_categories_nodes_id'] as $node_id => $node_value)
          <div wire:key="{{ 'cc-' . $node_id }}">
            <x-inputs.checkbox step="complaint_categories_nodes_id" :$full_nodes :$node_id :$cache_key />
          </div>
        @endforeach
        <div class="d-flex justify-content-end pe-3">
          <button class="btn button-unisante mt-3" @if (empty(array_filter($chosen_complaint_categories))) disabled @endif
            wire:click="goToStep('consultation')">Questionnaires</button>
        </div>
      </div>
      <div class="col-4">
        @foreach ($diagnoses_per_cc as $cc => $diagnoses)
          @foreach ($diagnoses as $diagnose)
            <div wire:key="{{ $diagnose }}">
              @if (array_key_exists($cc, array_filter($chosen_complaint_categories)))
                <p class="text-success mb-0">{{ $diagnose }}</p>
              @else
                <p class="mb-0">{{ $diagnose }}</p>
              @endif
            </div>
          @endforeach
        @endforeach
      </div>
    </div>
  @endif

  {{-- Consultation --}}
  @if ($current_step === 'consultation')
    <div class="row g-3 mt-3">
      <div class="col-8 border-end">
        <x-step.questionnaire :nodes="$current_nodes['consultation']" :$chosen_complaint_categories :$full_nodes :$nodes_to_save :$current_cc
          :$cache_key :$debug_mode />
      </div>
      <div class="col-4">
        <div style="position: -webkit-sticky; position: sticky; top: 128px;">
          @foreach (array_keys(array_filter($chosen_complaint_categories)) as $index => $cc)
            <div wire:key="{{ 'step-' . $cc }}">
              <x-navigation.prevention-navsubsteps :$current_cc :$completion_per_substep :$cc :$full_nodes :$index />
            </div>
          @endforeach
        </div>
      </div>
    </div>
  @endif

  {{-- Diagnoses --}}
  @if ($current_step === 'diagnoses')
    <div class="row g-3 mt-3">
      <div class="col-12">
        <x-step.prevention-results :$df_to_display :$final_diagnoses />
      </div>
    </div>
  @endif

</div>

{{-- <div class="col-3">
      <div class="container">
        Steps
        @foreach ($steps[$algorithm_type] as $key => $substeps)
          <div wire:key="{{ 'go-step-' . $key }}">
            <button class="btn btn-outline-primary m-1"
              wire:click="goToStep('{{ $key }}')">{{ $key }}</button>
          </div>
        @endforeach
        <div class="container">
          CCs chosen :
          @foreach ($chosen_complaint_categories as $cc => $chosen)
            <div wire:key="{{ 'edit-cc-' . $cc }}">
              @if ($chosen)
                <p class="mb-0">{{ $cc }}</p>
              @endif
            </div>
          @endforeach
        </div>
      </div>
    </div> --}}
</div>

@script
  <script type="text/javascript">
    let jsComponent = {}
    let lastStartPercentage = [];
    let lastSubStepStartPercentage = {};
    Livewire.hook('morph.updated', ({
      el,
      component,
      toEl,
      skip,
      childrenOnly
    }) => {
      // todo fix when going next step without success icon
      let currentStep = jsComponent.snapshot.data.current_step
      let startPercentage = 0;
      let endPercentage = 0;
      let currentCc = jsComponent.snapshot.data.current_cc
      if (el.classList.contains('circle-substep-' + currentStep + '-' + currentCc)) {
        startPercentage = jsComponent.snapshot.data.completion_per_substep[0][currentCc][0].start
        endPercentage = jsComponent.snapshot.data.completion_per_substep[0][currentCc][0].end
        if (!lastSubStepStartPercentage.hasOwnProperty(currentCc)) {
          lastSubStepStartPercentage[currentCc] = [];
        }
        if (startPercentage === 0 || lastSubStepStartPercentage[currentCc] !== startPercentage) {
          el.setAttribute('stroke-dasharray', endPercentage + ',100');
          el.style.setProperty('--startPercentage', startPercentage);
          var newone = el.cloneNode(true);
          el.nextElementSibling.innerHTML = endPercentage + "%"
          el.parentNode.replaceChild(newone, el);
          lastSubStepStartPercentage[currentCc] = startPercentage;
        }
      }
      if (el.classList.contains('circle-' + currentStep)) {
        startPercentage = jsComponent.snapshot.data.completion_per_step[0][currentStep][0].start
        endPercentage = jsComponent.snapshot.data.completion_per_step[0][currentStep][0].end
        if (lastStartPercentage[currentStep] !== endPercentage) {
          el.setAttribute('stroke-dasharray', endPercentage + ',100');
          el.style.setProperty('--startPercentage', startPercentage);
          var newone = el.cloneNode(true);
          el.nextElementSibling.innerHTML = endPercentage + "%"
          el.parentNode.replaceChild(newone, el);
          lastStartPercentage[currentStep] = endPercentage;
        }
      }
    });
    Livewire.hook('component.init', ({
      component,
      cleanup
    }) => {
      jsComponent = component
    })
    Livewire.on("scrollTop", () => {
      window.scrollTo(0, 0);
    });
  </script>
@endscript
