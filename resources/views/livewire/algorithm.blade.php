<div class="mb-5">
  <div>
    <h1>Title: {{ $title }}</h1>
    <h1>id: {{ $id }}</h1>
  </div>
  <div class="row g-3">
    <div class="col-8">
      @if ($current_step === 'registration')
        {{-- add first and last name inputs --}}
        <label class="form-label" for="birth_date">Date of birth</label>
        <input class="form-control" wire:model.live="date_of_birth" type="date" pattern="\d{4}-\d{2}-\d{2}"
          id="birth_date" name="birth_date">
        @foreach ($registration_nodes as $node)
          <div wire:key="{{ 'registration-' . $node['id'] }}" class="mb-2">
            @if ($node['id'] === 42321)
              <label class="form-label" for="{{ $node['id'] }}">
                {{ $node['label'] }}
              </label>
              <select wire:model.live="nodes_to_save.{{ $node['id'] }}" id="{{ $node['id'] }}" class="form-select">
                <option selected>Select an answer</option>
                @foreach ($node['answers'] as $answer)
                  <option value="{{ $answer['id'] }}">{{ $answer['label'] }}</option>
                @endforeach
              </select>
            @else
              <label class="form-label" for="{{ $node['id'] }}">{{ $node['label'] }}</label>
              <input class="form-control @error('value') is-invalid @enderror" type="text"
                wire:model.live="nodes_to_save.{{ $node['id'] }}" name="{{ $node['id'] }}"
                id="{{ $node['id'] }}" @if ($node['display_format'] === 'Formula') disabled @endif>
              @error('value')
                <div class="invalid-feedback" role="alert">{{ $message }}</div>
              @enderror
            @endif
          </div>
        @endforeach
        <button wire:click="goToStep('complaint_categories')">complaint_categories</button>
      @endif
      @if ($current_step === 'complaint_categories')
        <div class="mb-3">
          {{-- <livewire:components.step-renderer :nodes="$complaint_categories_nodes" /> --}}
          {{-- <livewire:components.step-renderer :steps="$registration_steps" :nodes="$nodes" />
        <livewire:components.step-renderer :steps="$medical_history_steps" :nodes="$nodes" /> --}}
          @foreach ($complaint_categories_nodes[$age_key] as $node)
            {{-- @if ($node->display_format === 'RadioButton') --}}
            {{-- @dump($node->category) --}}
            {{-- @if ($node->category === 'complaint_category') --}}
            {{-- <livewire:components.inputs.toggle :key="$step" :node="$node" /> --}}
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
            {{-- @else
              <livewire:components.inputs.radio :key="$node['id']" :node="$node" />
            @endif
          @elseif ($node->display_format === 'Input' || $node->display_format === 'Numeric')
            <livewire:components.inputs.text :key="$node['id']" :node="$node" />
          @elseif ($node->display_format === 'DropDownList')
            <livewire:components.inputs.select :key="$node['id']" :node="$node" />
          @endif --}}
          @endforeach
          <button wire:click="goToStep('medical_history')">medical_history</button>
          {{-- <button wire:click="submitCC({{ reset($chosen_complaint_categories) }})">Next</button> --}}
        </div>
      @endif
      @if ($current_step === 'medical_history')
        @foreach ($chosen_complaint_categories as $cc)
          {{-- @dump($nodes[$cc]) --}}
          {{-- @if ($this->currentStep === $cc) --}}
          {{-- <livewire:components.step-renderer :key="$cc" :step="$cc" :nodes="$nodes[$cc]" /> --}}
          {{-- <livewire:components.step-renderer :step="$cc" /> --}}
          {{-- @endif --}}
          @if ($current_cc === $cc)
            <div wire:key="{{ 'chosen-cc-' . $cc }}">
              @if (isset($nodes[$age_key][$cc]))
                @dump($nodes_to_save)
                @dump($nodes[$age_key][$cc])
                @foreach ($nodes[$age_key][$cc] as $node)
                  <div wire:key="{{ 'nodes-' . $node['id'] }}">
                    @if (in_array($node, $nodes[$age_key][$cc]))
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
                    @endif
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
              <button wire:click="goToStep('health_care_questions')">health_care_questions</button>
            @endif
          @endif
        @endforeach
      @endif

      @if ($current_step === 'health_care_questions')
        @foreach ($df_to_display as $df)
            <div class="m-0" wire:key="{{ 'df-' . $df['id'] }}">
                <label class="form-check-label" for="{{ $df['id'] }}">{{ $df['label'] }}</label>
                <label class="custom-control teleport-switch">
                <span class="teleport-switch-control-description">Disagree</span>
                <input type="checkbox" class="teleport-switch-control-input" name="{{ $df['id'] }}"
                    id="{{ $df['id'] }}" value="{{ $df['id'] }}" wire:model.live="agreed_diagnoses.{{ $df['id'] }}">
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
                            id="{{ $drug['id'] }}" value="{{ $drug['id'] }}" wire:model.live="agreed_drugs.{{ $drug['id'] }}">
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
        @foreach ($steps as $step)
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
