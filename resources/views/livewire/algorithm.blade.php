<div class="mb-5">
  <div>
    <h1>Title: {{ $title }}</h1>
    <h1>id: {{ $id }}</h1>
  </div>
  <div class="row g-3">
    <div class="col-8">
      {{-- @dump(Cache::get($cache_key)["final_diagnoses"]) --}}
      @php
        $cache = Cache::get($cache_key);
        $full_nodes = $cache['full_nodes'];
        $final_diagnoses = $cache['final_diagnoses'];
        $health_cares = $cache['health_cares'];
      @endphp
      {{-- Registration --}}
      @if ($current_step === 'registration')
        {{-- //todo do not send anything more than ID to any nested component ! --}}
        <livewire:components.step.registration wire:key="registration" :nodes="$current_nodes" :cache_key="$cache_key" />
        <button class="btn btn-sm btn-outline-primary m-1"
          wire:click="goToStep('first_look_assessment')">first_look_assessment</button>
      @endif
      @if ($current_step === 'first_look_assessment')

        {{-- Vitals --}}
        @foreach ($current_nodes['first_look_nodes_id'] ?? [] as $node_id)
          <div class="m-0" wire:key="{{ 'first-look-' . $node_id }}">
            <label class="form-check-label" for="{{ $node_id }}">{{ $full_nodes[$node_id]['label']['en'] }}</label>
            <label class="custom-control teleport-switch">
              <span class="teleport-switch-control-description">No</span>
              <input type="checkbox" class="teleport-switch-control-input" name="{{ $node_id }}"
                id="{{ $node_id }}" value="{{ $node_id }}" wire:model.live="chosen_complaint_categories">
              <span class="teleport-switch-control-indicator"></span>
              <span class="teleport-switch-control-description">Yes</span>
            </label>
          </div>
        @endforeach

        {{-- Complaint categories --}}
        @foreach ($current_nodes['complaint_categories_nodes_id'][$age_key] as $node_id)
          <div class="m-0" wire:key="{{ 'cc-' . $node_id }}">
            <label class="form-check-label"
              for="{{ $node_id }}">{{ $full_nodes[$node_id]['label']['en'] }}</label>
            <label class="custom-control teleport-switch">
              <span class="teleport-switch-control-description">No</span>
              <input type="checkbox" class="teleport-switch-control-input" name="{{ $node_id }}"
                id="{{ $node_id }}" value="{{ $node_id }}" wire:model.live="chosen_complaint_categories">
              <span class="teleport-switch-control-indicator"></span>
              <span class="teleport-switch-control-description">Yes</span>
            </label>
          </div>
        @endforeach

        {{-- Basic measurement --}}
        @foreach ($current_nodes['basic_measurements_nodes_id'] ?? [] as $node_id)
          <div class="m-0" wire:key="{{ 'cc-' . $node_id }}">
            @switch($full_nodes[$node_id]['display_format'])
              @case('RadioButton')
                <div>
                  <livewire:components.inputs.radio wire:key="{{ 'basic-measurement-' . $node_id }}" :node_id="$node_id" />
                </div>
              @break

              @case('String')
                <div>
                  <livewire:components.inputs.text wire:key="{{ 'basic-measurement-' . $node_id }}" :node_id="$node_id" />
                </div>
              @break

              @case('DropDownList')
                <div>
                  <livewire:components.inputs.select wire:key="{{ 'basic-measurement-' . $node_id }}" :node_id="$node_id" />
                </div>
              @break

              @case('Input')
                <div>
                  <livewire:components.inputs.numeric wire:key="{{ 'basic-measurement-' . $node_id }}" :node_id="$node_id"
                    :cache_key="$cache_key" />
                </div>
              @break

              @case('Formula')
                <div>
                  <livewire:components.inputs.text :value="$nodes_to_save[$node_id]" wire:key="{{ $cc . $node_id }}" :node_id="$node_id" />
                </div>
              @break

              @default
            @endswitch
          </div>
        @endforeach
        <button class="btn btn-sm btn-outline-primary m-1" wire:click="goToStep('consultation')">consultation</button>
        {{-- <button class="btn btn-sm btn-outline-secondary m-1" wire:click="submitCC({{ reset($chosen_complaint_categories) }})">Next</button> --}}
      @endif

      {{-- Consultation --}}
      @if ($current_step === 'consultation')
        @foreach ($chosen_complaint_categories as $cc)
          @if ($current_cc === $cc)
            <div wire:key="{{ 'chosen-cc-' . $cc }}">
              @foreach ($current_nodes as $title => $system)
                @if (isset($system[$cc]))
                  {{-- System container --}}
                  <div wire:key="{{ 'system-' . $title }}">
                    <h4>{{ $title }}</h4>
                    @foreach ($system[$cc] as $node_id)
                      <div wire:key="{{ 'nodes-' . $node_id }}">
                        @switch($full_nodes[$node_id]['display_format'])
                          @case('RadioButton')
                            <div>
                              <livewire:components.inputs.radio wire:key="{{ $cc . $node_id }}" :node_id="$node_id"
                                :cache_key="$cache_key" />
                            </div>
                          @break

                          @case('String')
                            <div>
                              <livewire:components.inputs.text wire:key="{{ $cc . $node_id }}" :node_id="$node_id"
                                :cache_key="$cache_key" />
                            </div>
                          @break

                          @case('DropDownList')
                            <div>
                              <livewire:components.inputs.select wire:key="{{ $cc . $node_id }}" :node_id="$node_id"
                                :cache_key="$cache_key" />
                            </div>
                          @break

                          @case('Input')
                            <div>
                              <livewire:components.inputs.numeric wire:key="{{ $cc . $node_id }}" :node_id="$node_id"
                                :cache_key="$cache_key" />
                            </div>
                          @break

                          @case('Formula')
                            <div>
                              <livewire:components.inputs.text :value="$nodes_to_save[$node_id]" wire:key="{{ $cc . $node_id }}"
                                :node_id="$node_id" :cache_key="$cache_key" />
                            </div>
                          @break

                          @default
                        @endswitch
                      </div>
                    @endforeach
                  </div>
                @endif
              @endforeach
            </div>
            @if (!$loop->first)
              <button class="btn btn-sm btn-outline-primary m-1" wire:click="goToPreviousCc()">Previous CC</button>
            @endif
            @if (!$loop->last)
              <button class="btn btn-sm btn-outline-primary m-1" wire:click="goToNextCc()">Next CC</button>
            @endif
            @if ($loop->last)
              <button class="btn btn-sm btn-outline-primary m-1" wire:click="goToStep('tests')">tests</button>
            @endif
          @endif
        @endforeach
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
              <button class="nav-link @if ($current_sub_step === $title) active @endif"
                id="{{ Str::slug($title) }}-tab" data-bs-toggle="tab" data-bs-target="#{{ Str::slug($title) }}"
                type="button" role="tab" aria-controls="{{ Str::slug($title) }}"
                aria-selected="{{ $current_sub_step === $index }}"
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
                    @foreach ($df_to_display as $df)
                      <tr wire:key="{{ 'df-' . $df['id'] }}">
                        <td>
                          <label class="form-check-label" for="{{ $df['id'] }}">{{ $df['label'] }}</label>
                        </td>
                        <td><label class="custom-control teleport-switch">
                            <span class="teleport-switch-control-description">Disagree</span>
                            <input type="checkbox" class="teleport-switch-control-input" name="{{ $df['id'] }}"
                              id="{{ $df['id'] }}" value="{{ $df['id'] }}"
                              wire:model.live="diagnoses_status.{{ $df['id'] }}">
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
                      @foreach (array_filter($diagnoses_status) as $diagnosis_id => $value)
                        @foreach ($df_to_display[$diagnosis_id]['drugs'] as $drug_id => $drug)
                          <tr wire:key="{{ 'drug-' . $drug_id }}">
                            @php $cache_drug=$health_cares[$drug_id] @endphp
                            <td><label class="form-check-label"
                                for="{{ $drug_id }}">{{ $cache_drug['label']['en'] }}</label></td>
                            <td>
                              <select class="form-select form-select-sm" aria-label=".form-select-sm example"
                                wire:model.live="drugs_formulation.{{ $drug_id }}"
                                id="formultaion-{{ $drug_id }}">
                                <option selected>Please Select a formulation</option>
                                @foreach ($cache_drug['formulations'] as $formulation)
                                  <option @if ($loop->first) selected @endif
                                    value="{{ intval(strval($formulation['id'])) }}">
                                    {{ $formulation['description']['en'] }}
                                  </option>
                                @endforeach
                              </select>
                            </td>
                            <td><label class="custom-control teleport-switch">
                                <span class="teleport-switch-control-description">Disagree</span>
                                <input type="checkbox" class="teleport-switch-control-input"
                                  name="{{ $drug_id }}" id="{{ $drug_id }}" value="{{ $drug_id }}"
                                  wire:model.live="drugs_status.{{ $drug_id }}">
                                <span class="teleport-switch-control-indicator"></span>
                                <span class="teleport-switch-control-description">Agree</span>
                              </label></td>
                          </tr>
                        @endforeach
                      @endforeach
                    </tbody>
                  </table>
                @endif
              @endif
              @if ($current_sub_step === 'summary')
                {{-- referral and treatment questions are no needed --}}
                @php
                  $steps[$current_step][] = 'managements';
                  $steps[$current_step] = array_unique($steps[$current_step]);
                @endphp
                <div class="accordion" id="accordionExample">
                  @foreach ($steps[$current_step] as $index => $substep)
                    {{-- @if (!in_array($substep, ['treatment_questions', 'referral', $current_sub_step])) --}}
                    @if ($substep === 'managements')
                      <div class="accordion-item">
                        <h2 class="accordion-header" id="heading{{ $index }}">
                          <button class="accordion-button {{ $index === 0 ? '' : 'collapsed' }}" type="button"
                            data-bs-toggle="collapse" data-bs-target="#collapse{{ $index }}"
                            aria-expanded="{{ $index === 0 ? 'true' : 'false' }}"
                            aria-controls="collapse{{ $index }}">
                            {{-- {{ $substep }} --}}
                            {{ ucwords(str_replace('_', ' ', $substep)) }}
                          </button>
                        </h2>
                        <div id="collapse{{ $index }}"
                          class="accordion-collapse collapse {{ $index === 0 ? 'show' : '' }}"
                          aria-labelledby="heading{{ $index }}" data-bs-parent="#accordionExample">
                          <div class="accordion-body">
                            {{-- {!! $substep !!} --}}
                            <table class="table">
                              <thead class="table-dark">
                                <tr>
                                  <th scope="col">{{ ucwords(str_replace('_', ' ', $substep)) }}git pull</th>
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
                                            <button class="btn btn-sm btn-outline-secondary m-1"
                                              @click="open = ! open">
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
                            {{-- {{ $substep }} --}}
                            {{ ucwords(str_replace('_', ' ', $substep)) }}
                          </button>
                        </h2>
                        <div id="collapse{{ $index }}"
                          class="accordion-collapse collapse {{ $index === 0 ? 'show' : '' }}"
                          aria-labelledby="heading{{ $index }}" data-bs-parent="#accordionExample">
                          <div class="accordion-body">
                            {!! $substep !!} will come here
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
                                      <b>Indication:</b> {{$formulation['indication']}}<br>
                                      <b>Route:</b> {{$formulation['route']}}<br>

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

        {{-- <ul class="nav nav-tabs" id="myTab" role="tablist">
        @foreach ($steps[$current_step] as $substep)
            <li class="nav-item" role="presentation">
                <button class="nav-link  @if ($current_sub_step) active @endif" id="final_diagnoses-tab" data-bs-toggle="tab"
                data-bs-target="#{{$substep}}" type="button" role="tab" aria-controls="{{$substep}}"
                aria-selected="true" wire:click="goToSubStep('diagnoses','final_diagnoses')">{{$substep}}</button>
            </li>
        @endforeach
        </ul> --}}
        {{-- <ul class="nav nav-tabs" id="myTab" role="tablist">
          <li class="nav-item" role="presentation">
            <button class="nav-link  @if ($current_sub_step === 'final_diagnoses') active @endif" id="final_diagnoses-tab" data-bs-toggle="tab"
              data-bs-target="#final_diagnoses" type="button" role="tab" aria-controls="final_diagnoses"
              aria-selected="true" wire:click="goToSubStep('diagnoses','final_diagnoses')">Final diagnoses</button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="treatment-tab" data-bs-toggle="tab" data-bs-target="#treatment" type="button"
              role="tab" aria-controls="treatment" aria-selected="false">Treatment Questions</button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link @if ($current_sub_step === 'drugs') active @endif" id="drugs-tab" data-bs-toggle="tab" data-bs-target="#drugs" type="button"
              role="tab" aria-controls="drugs" aria-selected="false" wire:click="goToSubStep('diagnoses','drugs')">Medicines</button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link @if ($current_sub_step === 'summary') active @endif" id="summary-tab" data-bs-toggle="tab" data-bs-target="#summary" type="button"
              role="tab" aria-controls="summary" aria-selected="false" wire:click="goToSubStep('diagnoses','summary')">Summary</button>
          </li>
          <li class="nav-item @if ($current_sub_step === 'referral') active @endif" role="presentation">
            <button class="nav-link" id="referral-tab" data-bs-toggle="tab" data-bs-target="#referral"
              type="button" role="tab" aria-controls="referral" aria-selected="false" wire:click="goToSubStep('diagnoses','referral')">Referral</button>
          </li>
        </ul> --}}
        {{-- <div class="tab-content" id="myTabContent">
          <div class="tab-pane fade show active" id="final_diagnoses" role="tabpanel"
            aria-labelledby="final_diagnoses-tab">
              @foreach ($df_to_display as $df)
                <div wire:key="{{ 'df-' . $df['id'] }}" class="d-flex ">
                  <label class="form-check-label col-md-8" for="{{ $df['id'] }}">{{ $df['label'] }}</label>
                  <label class="custom-control teleport-switch col-md-4">
                    <span class="teleport-switch-control-description">Disagree</span>
                    <input type="checkbox" class="teleport-switch-control-input" name="{{ $df['id'] }}"
                      id="{{ $df['id'] }}" value="{{ $df['id'] }}"
                      wire:model.live="diagnoses_status.{{ $df['id'] }}">
                    <span class="teleport-switch-control-indicator"></span>
                    <span class="teleport-switch-control-description">Agree</span>
                  </label>
                </div>
                <hr class="my-4">
              @endforeach
          </div>
          <div class="tab-pane fade" id="treatment" role="tabpanel" aria-labelledby="treatment-tab">Treatment</div>
          <div class="tab-pane fade" id="drugs" role="tabpanel" aria-labelledby="drugs-tab">
            @if (isset($diagnoses_status) && count(array_filter($diagnoses_status)))
              @php $unique_ids=[] @endphp
              @foreach (array_filter($diagnoses_status) as $diagnosis_id => $value)
                @forelse ($df_to_display[$diagnosis_id]['drugs'] as $drug)
                  @if (!in_array($drug['id'], $unique_ids))
                    @php $unique_ids[]=$drug['id'] @endphp
                    <div wire:key="{{ 'drug-' . $drug['id'] }}" class="d-flex ">
                      <label class="form-check-label col-md-8" for="{{ $drug['id'] }}">{{ $drug['label'] }}</label>
                      <label class="custom-control teleport-switch col-md-4">
                        <span class="teleport-switch-control-description">Disagree</span>
                        <input type="checkbox" class="teleport-switch-control-input" name="{{ $drug['id'] }}"
                          id="{{ $drug['id'] }}" value="{{ $drug['id'] }}"
                          wire:model.live="drugs_status.{{ $drug['id'] }}">
                        <span class="teleport-switch-control-indicator"></span>
                        <span class="teleport-switch-control-description">Agree</span>
                      </label>
                    </div>
                    <hr class="my-4">
                  @endif
                @endforeach
              @endforeach
            @endif
          </div>
          <div class="tab-pane fade" id="summary" role="tabpanel" aria-labelledby="summary-tab">
            <div class="accordion" id="accordionExample">
              <div class="accordion-item">
                <h2 class="accordion-header" id="headingOne">
                  <button class="accordion-button" type="button" data-bs-toggle="collapse"
                    data-bs-target="#collapseOne" aria-expanded="true" aria-controls="collapseOne">
                    FINAL DIAGNOSES
                  </button>
                </h2>
                <div id="collapseOne" class="accordion-collapse collapse show" aria-labelledby="headingOne"
                  data-bs-parent="#accordionExample">
                  <div class="accordion-body">
                    <strong>This is the first item's accordion body.</strong> It is shown by default, until the collapse
                    plugin adds the appropriate classes that we use to style each element. These classes control the
                    overall appearance, as well as the showing and hiding via CSS transitions. You can modify any of
                    this with custom CSS or overriding our default variables. It's also worth noting that just about any
                    HTML can go within the <code>.accordion-body</code>, though the transition does limit overflow.
                  </div>
                </div>
              </div>
              <div class="accordion-item">
                <h2 class="accordion-header" id="headingTwo">
                  <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                    data-bs-target="#collapseTwo" aria-expanded="false" aria-controls="collapseTwo">
                    TREATMENTS
                  </button>
                </h2>
                <div id="collapseTwo" class="accordion-collapse collapse" aria-labelledby="headingTwo"
                  data-bs-parent="#accordionExample">
                  <div class="accordion-body">
                    <strong>This is the second item's accordion body.</strong> It is hidden by default, until the
                    collapse plugin adds the appropriate classes that we use to style each element. These classes
                    control the overall appearance, as well as the showing and hiding via CSS transitions. You can
                    modify any of this with custom CSS or overriding our default variables. It's also worth noting that
                    just about any HTML can go within the <code>.accordion-body</code>, though the transition does limit
                    overflow.
                  </div>
                </div>
              </div>
              <div class="accordion-item">
                <h2 class="accordion-header" id="headingThree">
                  <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                    data-bs-target="#collapseThree" aria-expanded="false" aria-controls="collapseThree">
                    MANAGEMENT
                  </button>
                </h2>
                <div id="collapseThree" class="accordion-collapse collapse" aria-labelledby="headingThree"
                  data-bs-parent="#accordionExample">
                  <div class="accordion-body">
                    @if (isset($diagnoses_status) && count(array_filter($diagnoses_status)))
                        @foreach ($managements_to_display as $management_key => $diagnosis_id)
                          @if (isset($diagnoses_status[$diagnosis_id]))
                            <div wire:key="{{ 'management-' . $management_key }}">
                              <b>{{ $health_cares[$management_key]['label']['en'] }}</b><br> <b>Indication:</b>
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
                            </div>
                          @endif
                        @endforeach
                    @endif
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="tab-pane fade" id="referral" role="tabpanel" aria-labelledby="referral-tab">Referral</div>
        </div> --}}
        {{-- we need to check the sub steps --}}
        {{-- @if ($current_sub_step === 'final_diagnoses')
          <table class="table">
            <thead class="table-dark">
              <tr>
                <th scope="col">Final Diagnoses</th>
                <th scope="col">Action</th>
              </tr>
            </thead>
            <tbody>
              @foreach ($df_to_display as $df)
                <tr wire:key="{{ 'df-' . $df['id'] }}">
                  <td>
                    <label class="form-check-label" for="{{ $df['id'] }}">{{ $df['label'] }}</label>
                  </td>
                  <td>
                    <label class="custom-control teleport-switch">
                      <span class="teleport-switch-control-description">Disagree</span>
                      <input type="checkbox" class="teleport-switch-control-input" name="{{ $df['id'] }}"
                        id="{{ $df['id'] }}" value="{{ $df['id'] }}"
                        wire:model.live="diagnoses_status.{{ $df['id'] }}">
                      <span class="teleport-switch-control-indicator"></span>
                      <span class="teleport-switch-control-description">Agree</span>
                    </label>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        @endif
        @if (isset($diagnoses_status) && count(array_filter($diagnoses_status)))
          @if ($current_sub_step === 'drugs')
            <table class="table">
              <thead class="table-dark">
                <tr>
                  <th scope="col">Drugs</th>
                  <th scope="col">Action</th>
                </tr>
              </thead>
              <tbody>
                @php $unique_ids=[] @endphp
                @foreach (array_filter($diagnoses_status) as $diagnosis_id => $value)
                  @foreach ($df_to_display[$diagnosis_id]['drugs'] as $drug)
                    @if (!in_array($drug['id'], $unique_ids))
                      @php $unique_ids[]=$drug['id'] @endphp
                      <tr wire:key="{{ 'drug-' . $drug['id'] }}">
                        <td>
                          <label class="form-check-label" for="{{ $drug['id'] }}">{{ $drug['label'] }}</label>
                        </td>
                        <td>
                          <label class="custom-control teleport-switch">
                            <span class="teleport-switch-control-description">Disagree</span>
                            <input type="checkbox" class="teleport-switch-control-input" name="{{ $drug['id'] }}"
                              id="{{ $drug['id'] }}" value="{{ $drug['id'] }}"
                              wire:model.live="drugs_status.{{ $drug['id'] }}">
                            <span class="teleport-switch-control-indicator"></span>
                            <span class="teleport-switch-control-description">Agree</span>
                          </label>
                        </td>
                      </tr>
                    @endif
                  @endforeach
                @endforeach
              </tbody>
            </table>
          @endif
          @if ($current_sub_step === 'managements')
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
                        <b>{{ $health_cares[$management_key]['label']['en'] }}</b><br> <b>Indication:</b>
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
        @endif --}}
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
