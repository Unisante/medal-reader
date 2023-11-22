<div class="mb-5">
  <div>
    <h1>Title: {{ $title }}</h1>
    <h1>id: {{ $id }}</h1>
  </div>
  <div class="row g-3">
    <div class="col-8">
      @if ($current_step === 'registration')
        <livewire:components.step.registration wire:key="registration" :nodes="$current_nodes" :cache_key="$cache_key" />
        <button wire:click="goToStep('first_look_assessment')">first_look_assessment</button>
      @endif
      @if ($current_step === 'first_look_assessment')
        {{-- todo add vitals --}}
        {{-- <livewire:components.step.vitals wire:key="registration" :nodes="$current_nodes" /> --}}
        {{-- <livewire:components.step.complaint-category wire:key="complaint_categories" :age_key="$age_key"
          :nodes="$complaint_categories_nodes" /> --}}
        @foreach ($current_nodes['complaint_categories_nodes_id'][$age_key] as $node)
          <div class="m-0" wire:key="{{ 'cc-' . $node['id'] }}">
            <label class="form-check-label" for="{{ $node['id'] }}">{{ $node['label'] }}</label>
            <label class="custom-control teleport-switch">
              <span class="teleport-switch-control-description">No</span>
              <input type="checkbox" class="teleport-switch-control-input" name="{{ $node['id'] }}"
                id="{{ $node['id'] }}" value="{{ $node['id'] }}" wire:model.live="chosen_complaint_categories">
              <span class="teleport-switch-control-indicator"></span>
              <span class="teleport-switch-control-description">Yes</span>
            </label>
          </div>
        @endforeach
        <button wire:click="goToStep('consultation')">consultation</button>
        {{-- <button wire:click="submitCC({{ reset($chosen_complaint_categories) }})">Next</button> --}}
      @endif
      @if ($current_step === 'consultation')
        @foreach ($chosen_complaint_categories as $cc)
          {{-- @dump($nodes[$cc]) --}}
          {{-- @if ($this->currentStep === $cc) --}}
          {{-- <livewire:components.step-renderer :key="$cc" :step="$cc" :nodes="$nodes[$cc]" /> --}}
          {{-- <livewire:components.step-renderer :step="$cc" /> --}}
          {{-- @endif --}}
          @if ($current_cc === $cc)
            <div wire:key="{{ 'chosen-cc-' . $cc }}">
              @if (isset($current_nodes[$cc]))
                @foreach ($current_nodes[$cc] as $node)
                  <div wire:key="{{ 'nodes-' . $node['id'] }}">
                    @switch($node['display_format'])
                      @case('RadioButton')
                        <div>
                          <livewire:components.inputs.radio wire:key="{{ $cc . $node['id'] }}" :node="$node" />
                        </div>
                      @break

                      @case('String')
                        <div>
                          <livewire:components.inputs.text wire:key="{{ $cc . $node['id'] }}" :node="$node" />
                        </div>
                      @break

                      @case('DropDownList')
                        <div>
                          <livewire:components.inputs.select wire:key="{{ $cc . $node['id'] }}" :node="$node" />
                        </div>
                      @break

                      @case('Input')
                        <div>
                          <livewire:components.inputs.numeric wire:key="{{ $cc . $node['id'] }}" :node="$node" />
                        </div>
                      @break

                      @case('Formula')
                        <div>
                          <livewire:components.inputs.text :value="$nodes_to_save[$node['id']]" wire:key="{{ $cc . $node['id'] }}"
                            :node="$node" />
                        </div>
                        {{-- <p> {{ $node['label'] }}</p> --}}
                        {{-- <p>{{ $node['formula'] }} => {{ $nodes_to_save[$node['id']] }} </p> --}}
                      @break

                      @default
                    @endswitch
                  </div>
                @endforeach
              @endif
            </div>
            @if (!$loop->first)
              <button wire:click="goToPreviousCc()">Previous CC</button>
            @endif
            @if (!$loop->last)
              <button wire:click="goToNextCc()">Next CC</button>
            @endif
            @if ($loop->last)
              <button wire:click="goToStep('tests')">tests</button>
            @endif
          @endif
        @endforeach
      @endif

      @if ($current_step === 'tests')
      @endif
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
          @if (isset($agreed_diagnoses[$df['id']]))
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
        {{-- @foreach ($df_to_display as $df)
          <div wire:key="{{ 'df-' . $df['id'] }}">

            <p> Final Diagnose :{{ $df['id'] }} </p>
            <p> {{ $df['label'] }}</p>
            <p> {{ $df['description'] }}</p>
          </div>
        @endforeach --}}
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
            <button wire:click="goToStep('{{ $step }}')">{{ $step }}</button>
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
