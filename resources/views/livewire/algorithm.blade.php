<div class="mb-5">
  <div>
    <h1>Title: {{ $title }}</h1>
    <h1>id: {{ $id }}</h1>
  </div>
  <div class="row g-3">
    <div class="col-8">
      @php $full_nodes=Cache::get($cache_key)["full_nodes"] @endphp
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
        @foreach ($df_to_display as $df)
          <div class="m-0" wire:key="{{ 'df-' . $df['id'] }}">
            <label class="form-check-label" for="{{ $df['id'] }}">{{ $df['label'] }}</label>
            <label class="custom-control teleport-switch">
              <span class="teleport-switch-control-description">Disagree</span>
              <input type="checkbox" class="teleport-switch-control-input" name="{{ $df['id'] }}"
                id="{{ $df['id'] }}" value="{{ $df['id'] }}"
                wire:model.live="agreed_diagnoses.{{ $df['id'] }}">
              <span class="teleport-switch-control-indicator"></span>
              <span class="teleport-switch-control-description">Agree</span>
            </label>
          </div>
          @if (isset($diagnoses_status[$df['id']]))
            @foreach ($df['drugs'] as $drug)
              <div class="m-0" wire:key="{{ 'drug-' . $drug['id'] }}">
                <label class="form-check-label" for="{{ $drug['id'] }}">{{ $drug['label'] }}</label>
                <label class="custom-control teleport-switch">
                  <span class="teleport-switch-control-description">Disagree</span>
                  <input type="checkbox" class="teleport-switch-control-input" name="{{ $drug['id'] }}"
                    id="{{ $drug['id'] }}" value="{{ $drug['id'] }}"
                    wire:model.live="agreed_drugs.{{ $drug['id'] }}">
                  <span class="teleport-switch-control-indicator"></span>
                  <span class="teleport-switch-control-description">Agree</span>
                </label>
              </div>
            @endforeach
          @endif
        @endforeach

        {{-- Managements --}}
        @foreach ($managements_to_display as $management)
          <div wire:key="{{ 'management-' . $management['id'] }}">
            <p> Management : {{ $management['id'] }} </p>
            <p> {{ $management['label'] }}</p>
            <p> {{ $management['description'] }}</p>
          </div>
        @endforeach
      @endif

    </div>
    <div class="col-4">
      <div class="container">
        Steps
        @foreach (array_keys($steps) as $step)
          <div wire:key="{{ 'go-step-' . $step }}">
            <button class="btn btn-sm btn-outline-primary m-1"
              wire:click="goToStep('{{ $step }}')">{{ $step }}</button>
          </div>
        @endforeach
      </div>
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
