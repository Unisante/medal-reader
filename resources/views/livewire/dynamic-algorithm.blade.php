<div class="mb-5">
  <div>
    <h1>Title: {{ $title }}</h1>
    <h1>id: {{ $id }}</h1>
  </div>
  <div class="row g-3">
    <div class="col-8">
      @php
        $cache = Cache::get($cache_key);
        $full_nodes = $cache['full_nodes'];
        $final_diagnoses = $cache['final_diagnoses'];
        $health_cares = $cache['health_cares'];
      @endphp
      @dump($current_nodes)
      {{-- Registration --}}
      @if ($current_step === 'registration')
        <x-step.registration :nodes="$current_nodes['registration']" :cache_key="$cache_key" />
      @endif

      {{-- first_look_assessment --}}
      @if ($current_step === 'first_look_assessment')
        <x-step.first_look_assessment :nodes="$current_nodes['first_look_assessment']" :cache_key="$cache_key" />
      @endif

      {{-- Consultation --}}
      @if ($current_step === 'consultation')
        <x-step.consultation :nodes="$current_nodes['consultation']" :cache_key="$cache_key" />
      @endif

      {{-- Tests --}}
      @if ($current_step === 'tests')
      @endif

      {{-- Diagnoses --}}
      @if ($current_step === 'diagnoses')
        <h1 class="bg-dark text-light ">{{ strtoupper($current_step) }}</h1>
        <ul class="nav nav-tabs" id="myTab" role="tablist">
          @foreach ($steps[$current_step] as $index => $title)
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
          @foreach ($steps[$current_step] as $index => $title)
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
                    @foreach ($df_to_display as $diagnosis_id=>$drugs)
                      <tr wire:key="{{ 'df-' . $diagnosis_id }}">
                        <td>
                          <label class="form-check-label" for="{{ $diagnosis_id }}">{{ $final_diagnoses[$diagnosis_id]['label']['en'] }}</label>
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
                <h1>Still in progress</h1>
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
                        @foreach ($drugs_to_display as $drug_id => $drug_id)
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
                        @endforeach
                    </tbody>
                  </table>
                @endif
              @endif
              @if ($current_sub_step === 'summary')
                @php
                  $steps[$current_step][] = 'managements';
                  $steps[$current_step] = array_unique($steps[$current_step]);
                @endphp
                <div class="accordion" id="accordionExample">
                  @foreach ($steps[$current_step] as $index => $substep)
                    @if ($substep === 'managements')
                      <div class="accordion-item">
                        <h2 class="accordion-header" id="heading{{ $index }}">
                          <button class="accordion-button {{ $index === 0 ? '' : 'collapsed' }}" type="button"
                            data-bs-toggle="collapse" data-bs-target="#collapse{{ $index }}"
                            aria-expanded="{{ $index === 0 ? 'true' : 'false' }}"
                            aria-controls="collapse{{ $index }}">
                            {{ ucwords(str_replace('_', ' ', $substep)) }}
                          </button>
                        </h2>
                        <div id="collapse{{ $index }}"
                          class="accordion-collapse collapse {{ $index === 0 ? 'show' : '' }}"
                          aria-labelledby="heading{{ $index }}" data-bs-parent="#accordionExample">
                          <div class="accordion-body">
                            <table class="table">
                              <thead class="table-dark">
                                <tr>
                                  <th scope="col">{{ ucwords(str_replace('_', ' ', $substep)) }}</th>
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
                          </div>
                        </div>
                      </div>
                    @endif
                    @if ($substep === 'medicines')
                      <div class="accordion-item">
                        <h2 class="accordion-header" id="heading{{ $index }}">
                          <button class="accordion-button {{ $index === 0 ? '' : 'collapsed' }}" type="button"
                            data-bs-toggle="collapse" data-bs-target="#collapse{{ $index }}"
                            aria-expanded="{{ $index === 0 ? 'true' : 'false' }}"
                            aria-controls="collapse{{ $index }}">
                            {{ ucwords(str_replace('_', ' ', $substep)) }}
                          </button>
                        </h2>
                        <div id="collapse{{ $index }}"
                          class="accordion-collapse collapse {{ $index === 0 ? 'show' : '' }}"
                          aria-labelledby="heading{{ $index }}" data-bs-parent="#accordionExample">
                          <div class="accordion-body">
                            <table class="table">
                              <thead>
                                <tr>
                                  <th scope="col">Treatments</th>
                                </tr>
                              </thead>
                              <tbody>
                                @foreach ($formulations_to_display as $drug_id => $formulation)
                                  <tr>
                                    <td>
                                      <b>{{ $formulation['drug_label'] }}</b><br>
                                      <b>{{ $formulation['description'] }}</b><br>
                                      <b>Indication:</b> {{ $formulation['indication'] }}<br>
                                      <b>Route:</b> {{ $formulation['route'] }}<br>

                                      {{-- @foreach ($health_cares[$drug_id]['formulations'] as $formulation)
                                        @if ($formulation['id'] == $drugs_formulation[$drug_id])
                                        <b>Dose calculation:</b><br>
                                        <b>Route:</b> {{ $formulation['administration_route_name'] }} <br>
                                        <b>Formulation:</b> {{ $formulation['description']['en'] }} <br>
                                        @endif
                                      @endforeach --}}
                                    </td>
                                  </tr>
                                @endforeach
                              </tbody>
                            </table>
                          </div>
                        </div>
                      </div>
                    @endif
                  @endforeach
                </div>
              @endif
              @if ($current_sub_step === 'referral')
                <h1>Still in progress</h1>
              @endif
            </div>
          @endforeach
        </div>
      @endif
    </div>
    <div class="col-4">
      <div class="container">
        Steps
        @foreach ($steps as $key => $substeps)
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
          @foreach ($chosen_complaint_categories as $cc)
            <div wire:key="{{ 'edit-cc-' . $cc }}">
              <p class="mb-0">{{ $cc }}</p>
            </div>
          @endforeach
        </div>
      </div>
    </div>
  </div>
