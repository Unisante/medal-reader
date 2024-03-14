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
    $villages = $cache['villages'];
  @endphp
  {{-- @dump($current_nodes) --}}
  {{-- Registration --}}

  {{-- @dump($current_nodes["registration"]) --}}
  {{-- @dump($current_nodes["first_look_assessment"]) --}}
  {{-- @dump($current_nodes["consultation"]) --}}
  {{-- @dump($current_nodes["registration"]["others"]) --}}

  <x-navigation.dynamic-navsteps :$current_step :$saved_step :$completion_per_step />

  <div class="row g-3">
    <div class="col-10">
      <ul class="nav nav-tabs mb-3" id="myTab" role="tablist">
        @foreach ($steps[$algorithm_type][$current_step] as $index => $title)
          <li class="nav-item" role="presentation">
            <button class="nav-link @if ($current_sub_step === $title) active @endif" id="{{ Str::slug($title) }}-tab"
              data-bs-toggle="tab" data-bs-target="#{{ Str::slug($title) }}" type="button" role="tab"
              aria-controls="{{ Str::slug($title) }}" aria-selected="{{ $current_sub_step === $index }}"
              wire:click="goToSubStep('{{ $current_step }}','{{ $title }}')">{{ ucwords(str_replace('_', ' ', $title)) }}
            </button>
          </li>
        @endforeach
      </ul>
      <div class="tab-content" id="myTabContent">
        @foreach ($steps[$algorithm_type][$current_step] as $index => $substep_title)
          <div wire:key="{{ 'consultation-' . $substep_title }}"
            class="tab-pane fade @if ($current_sub_step === $substep_title) show active @endif"
            id="{{ Str::slug($substep_title) }}" role="tabpanel" aria-labelledby="{{ Str::slug($substep_title) }}-tab">
            @if ($substep_title === 'medical_history')
              <x-step.consultation :nodes="$current_nodes['consultation']['medical_history']" substep="medical_history" :$nodes_to_save :$full_nodes :$villages
                :$debug_mode />
              <div class="d-flex justify-content-end">
                <button class="btn button-unisante m-1"
                  wire:click="goToSubStep('consultation', 'physical_exam')">Physical exam</button>
              </div>
            @endif
            @if ($substep_title === 'physical_exam')
              @if (isset($current_nodes['consultation']['physical_exam']))
                <x-step.consultation :nodes="$current_nodes['consultation']['physical_exam']" substep="physical_exam" :$nodes_to_save :$full_nodes :$villages
                  :$debug_mode />
                <div class="d-flex justify-content-end">
                  <button class="btn button-unisante m-1" wire:click="goToStep('tests')">tests</button>
                </div>
              @endif
            @endif
          </div>
        @endforeach
      </div>

      @if ($current_step === 'registration')
        {{-- @dump($current_nodes['registration']) --}}
        <x-step.registration :nodes="$current_nodes['registration']" :$nodes_to_save :$full_nodes :$villages :$algorithm_type :$debug_mode />
      @endif

      {{-- first_look_assessment --}}
      @if ($current_step === 'first_look_assessment')
        <x-step.first_look_assessment :nodes="$current_nodes['first_look_assessment']" :$full_nodes :$nodes_to_save :$debug_mode />
      @endif
      {{-- @dd($full_nodes) --}}

      {{-- Consultation --}}
      {{-- @dump($current_nodes['consultation']) --}}

      {{-- Tests --}}
      @if ($current_step === 'tests')
        @if (isset($this->current_nodes['tests']))
          <x-step.tests :nodes="$current_nodes['tests']" :$nodes_to_save :$full_nodes />
        @else
          <h2 class="fw-normal pb-3">Tests</h2>
          <p>There are no test</p>
          <div class="d-flex justify-content-end">
            <button class="btn button-unisante m-1" wire:click="goToStep('diagnoses')">diagnoses</button>
          </div>
        @endif
      @endif

      {{-- Diagnoses --}}
      @if ($current_step === 'diagnoses')
        <h2 class="fw-normal pb-3">Diagnoses</h2>
        <div class="tab-content" id="myTabContent">
          @foreach ($steps[$algorithm_type][$current_step] as $index => $title)
            <div class="tab-pane fade @if ($current_sub_step === $title) show active @endif"
              id="{{ Str::slug($title) }}" role="tabpanel" aria-labelledby="{{ Str::slug($title) }}-tab">
              @if ($current_sub_step === 'final_diagnoses')
                <table class="table table-striped">
                  <thead>
                    <tr>
                      <th scope="col">Final Diagnoses</th>
                      <th scope="col">Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach ($df_to_display as $diagnosis_id => $drugs)
                      <tr wire:key="{{ 'df-' . $diagnosis_id }}">
                        <td>
                          <label class="form-check-label"
                            for="{{ $diagnosis_id }}">{{ $final_diagnoses[$diagnosis_id]['label']['en'] }}</label>
                        </td>
                        <td><label class="custom-control teleport-switch">
                            <span class="teleport-switch-control-description">Disagree</span>
                            <input type="checkbox" class="teleport-switch-control-input" name="{{ $diagnosis_id }}"
                              id="{{ $diagnosis_id }}" value="{{ $diagnosis_id }}"
                              wire:model.live="diagnoses_status.{{ $diagnosis_id }}">
                            <span class="teleport-switch-control-indicator"></span>
                            <span class="teleport-switch-control-description">Agree</span>
                          </label></td>
                      </tr>
                    @endforeach
                  </tbody>
                </table>
              @endif
              @if ($current_sub_step === 'treatment_questions')
                @php
                  $treatment_questions = isset($current_nodes['diagnoses']['treatment_questions'])
                      ? $current_nodes['diagnoses']['treatment_questions']
                      : null;
                @endphp
                @if (isset($treatment_questions))
                  <table class="table table-striped">
                    <thead>
                      <tr>
                        <th scope="col">Treatment Question</th>
                      </tr>
                    </thead>
                    <tbody>
                      @foreach ($treatment_questions as $node_id => $answer)
                        <tr wire:key="{{ 'treatment-question-' . $node_id }}">
                          @switch($full_nodes[$node_id]['display_format'])
                            @case('RadioButton')
                              <td>
                                <x-inputs.radio step='diagnoses.treatment_questions' :$node_id :$full_nodes />
                              </td>
                            @break

                            @case('DropDownList')
                              <td>
                                <x-inputs.select step='diagnoses.treatment_questions' :$node_id :$full_nodes />
                              </td>
                            @break

                            @default
                          @endswitch
                        </tr>
                      @endforeach
                    </tbody>
                  </table>
                @else
                  <h1>There are no Questions</h1>
                @endif
              @endif
              @if ($current_sub_step === 'medicines')
                @if (isset($diagnoses_status) && count(array_filter($diagnoses_status)))
                  <table class="table table-striped">
                    <thead>
                      <tr>
                        <th scope="col">Proposed Medicines</th>
                        <th scope="col">Formulations</th>
                        <th scope="col">Action</th>
                      </tr>
                    </thead>
                    <tbody>
                      @foreach ($drugs_to_display as $drug_id => $is_displayed)
                        @if ($is_displayed)
                          <tr wire:key="{{ 'drug-' . $drug_id }}">
                            @php $cache_drug=$health_cares[$drug_id] @endphp
                            <td><label class="form-check-label"
                                for="{{ $drug_id }}">{{ $cache_drug['label']['en'] }}
                              </label>
                            </td>
                            <td>
                              <select class="form-select form-select-sm" aria-label=".form-select-sm example"
                                wire:model.live="drugs_formulation.{{ $drug_id }}"
                                id="formultaion-{{ $drug_id }}">
                                <option selected>Please Select a formulation</option>
                                @foreach ($cache_drug['formulations'] as $formulation)
                                  <option value="{{ $formulation['id'] }}">
                                    {{ $formulation['description']['en'] }}
                                  </option>
                                @endforeach
                              </select>
                            </td>
                            <td>
                              <label class="custom-control teleport-switch">
                                <span class="teleport-switch-control-description">Disagree</span>
                                <input type="checkbox" class="teleport-switch-control-input" name="{{ $drug_id }}"
                                  id="{{ $drug_id }}" value="{{ $drug_id }}"
                                  wire:model.live="drugs_status.{{ $drug_id }}">
                                <span class="teleport-switch-control-indicator"></span>
                                <span class="teleport-switch-control-description">Agree</span>
                              </label>
                            </td>
                          </tr>
                        @endif
                      @endforeach
                    </tbody>
                  </table>
                @endif
              @endif
              @if ($current_sub_step === 'summary')
                @if (array_filter($diagnoses_status))
                  <table class="table table-striped">
                    <thead class="table-dark">
                      <tr>
                        <th scope="col">Final Diagnoses</th>
                      </tr>
                    </thead>
                    <tbody>
                      @foreach ($diagnoses_status as $diagnosis_id => $agreed)
                        <tr>
                          <td><b>{{ $final_diagnoses[$diagnosis_id]['label']['en'] }}</b>
                            @if ($final_diagnoses[$diagnosis_id]['description']['en'])
                              <div x-data="{ open: false }">
                                <button class="btn btn-sm btn-outline-secondary m-1" @click="open = ! open">
                                  <i class="bi bi-info-circle"> Description</i>
                                </button>
                                <div x-show="open">
                                  <p>{{ $final_diagnoses[$diagnosis_id]['description']['en'] }}</p>
                                </div>
                              </div>
                            @endif
                          </td>
                        </tr>
                      @endforeach
                    </tbody>
                  </table>
                  <table class="table table-striped">
                    <thead class="table-dark">
                      <tr>
                        <th scope="col">Treatments</th>
                      </tr>
                    </thead>
                    <tbody>
                      @foreach ($formulations_to_display as $drug_id => $formulation)
                        <tr>
                          <td class="d-flex justify-content-between">
                            <div>
                              <b>{{ $formulation['drug_label'] }}</b> <br>
                              <p>
                                <span>{{ $formulation['amountGiven'] }}</span> |
                                <span>{{ $formulation['doses_per_day'] }} time per day</span> |
                                @if (intval($formulation['duration']))
                                  <span> {{ $formulation['duration'] }}
                                    {{ intval($formulation['duration']) > 1 ? 'days' : 'day' }}
                                  </span>
                                @else
                                  <span>{{ $formulation['duration'] }}</span>
                                @endif
                              </p>
                              <div x-data="{ open: false }" @toggle.window="open = ! open">
                                <div x-show="open">
                                  <span><b>Indication:</b> {{ $formulation['indication'] }}</span><br>
                                  <span><b>Dose calculation:</b> {{ $formulation['dose_calculation'] }}</span> <br>
                                  <span><b>Route:</b> {{ $formulation['route'] }}</span> <br>
                                  <span><b>Amount to be given :</b> {{ $formulation['amountGiven'] }}</span> <br>
                                  @if ($formulation['injection_instructions'])
                                    <span>
                                      <b>Preparation instructions :</b>{{ $formulation['injection_instructions'] }}
                                    </span> <br>
                                  @endif
                                  <span>
                                    <b>Frequency :</b> {{ $formulation['doses_per_day'] }} time per day (or
                                    every{{ $formulation['recurrence'] }} hours)
                                  </span> <br>
                                  <span>
                                    <b>Duration :</b>
                                    {{ $formulation['duration'] }}{{ intval($formulation['duration']) > 1 ? 'days' : 'day' }}
                                  </span> <br>
                                  @if ($formulation['dispensing_description'])
                                    <span>
                                      <b>Administration instructions :</b> {{ $formulation['dispensing_description'] }}
                                    </span><br>
                                  @endif
                                </div>
                              </div>
                            </div>
                            <div x-data>
                              <button class="btn btn-sm btn-outline-secondary m-1" @click="$dispatch('toggle')">
                                <i class="bi bi-info-circle"> More</i>
                              </button>
                            </div>
                          </td>
                        </tr>
                      @endforeach
                    </tbody>
                  </table>
                  <table class="table">
                    <thead class="table-dark">
                      <tr>
                        <th scope="col">Managements</th>
                      </tr>
                    </thead>
                    <tbody>
                      @foreach ($managements_to_display as $management_key => $diagnosis_id)
                        @if (isset($diagnoses_status[$diagnosis_id]))
                          <tr wire:key="{{ 'management-' . $management_key }}">
                            <td>
                              <b>{{ $health_cares[$management_key]['label']['en'] }}</b><br>
                              <b>Indication:</b>
                              {{ $final_diagnoses[$diagnosis_id]['label']['en'] }}
                              @if ($health_cares[$management_key]['description']['en'])
                                <div x-data="{ open: false }">
                                  <button class="btn btn-sm btn-outline-secondary m-1" @click="open = ! open">
                                    <i class="bi bi-info-circle"> Description</i>
                                  </button>
                                  <div x-show="open">
                                    <p>{{ $health_cares[$management_key]['description']['en'] }}</p>
                                  </div>
                                </div>
                              @endif
                            </td>
                          </tr>
                        @endif
                      @endforeach
                    </tbody>
                  </table>
                @endif
              @endif

              @if ($current_sub_step === 'referral')
                <h1>Still in progress</h1>
              @endif
            </div>
          @endforeach
        </div>
        <div class="d-flex justify-content-end">
          <button class="btn button-unisante mt-3" wire:click="setConditionsToPatients()">
            Send data to FHIR
          </button>
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
            <button class="btn btn-outline-primary m-1 dropdown-toggle dropdown-toggle-split"
              data-bs-toggle="dropdown" aria-expanded="false"></button>
            <ul class="dropdown-menu">
              @foreach ($substeps as $substep)
                <div wire:key="{{ 'go-sub-step-' . $substep }}">
                  <li><a class="dropdown-item"
                      wire:click="goToSubStep('{{ $key }}','{{ $substep }}')">{{ ucwords(str_replace('_', ' ', $substep)) }}</a>
                  </li>
                </div>
              @endforeach
            </ul>
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

  <x-modals.emergency />

  @script
    <script type="text/javascript">
      let jsComponent = {}
      let lastStartPercentage = [];
      Livewire.hook('morph.updated', ({
        el,
        component,
        toEl,
        skip,
        childrenOnly
      }) => {
        // todo fix when going next step without success icon
        let currentStep = jsComponent.snapshot.data.current_step
        if (el.classList.contains('circle-' + currentStep)) {
          let startPercentage = jsComponent.snapshot.data.completion_per_step[0][currentStep][0].start
          let endPercentage = jsComponent.snapshot.data.completion_per_step[0][currentStep][0].end
          if (lastStartPercentage[currentStep] !== startPercentage) {
            el.setAttribute('stroke-dasharray', endPercentage + ',100');
            el.style.setProperty('--startPercentage', startPercentage);
            var newone = el.cloneNode(true);
            el.nextElementSibling.innerHTML = endPercentage + "%"
            el.parentNode.replaceChild(newone, el);
            lastStartPercentage[currentStep] = startPercentage;
          }
        }
      });
      Livewire.hook('component.init', ({
        component,
        cleanup
      }) => {
        jsComponent = component
      })

      Livewire.on('openEmergencyModal', () => {
        const emergencyModal = document.getElementById('emergencyModal');
        var bootstrapEmergencyModal = new bootstrap.Modal(emergencyModal)
        bootstrapEmergencyModal.show()
      });
      Livewire.on("scrollTop", () => {
        window.scrollTo(0, 0);
      });
      document.addEventListener('livewire:init', () => {});
    </script>
  @endscript
