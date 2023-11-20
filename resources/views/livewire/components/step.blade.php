<div>
  @foreach ($steps as $step)
    @if ($nodes->$step->display_format === 'RadioButton')
      {{-- @dump($nodes->$step->category) --}}
      @if ($nodes->$step->category === 'complaint_category')
        {{-- <livewire:components.inputs.toggle :key="$step" :node="$nodes->$step" /> --}}
        <div class="form-check">
          <label class="form-check-label" for="{{ $nodes->$step->id }}">{{ $nodes->$step->label->en }}</label>
          <label class="custom-control teleport-switch">
            <span class="teleport-switch-control-description">No</span>
            <input type="checkbox" class="teleport-switch-control-input" name="{{ $nodes->$step->id }}"
              id="{{ $nodes->$step->id }}" value="{{ $nodes->$step->id }}" wire:model.live="chosen">
            <span class="teleport-switch-control-indicator"></span>
            <span class="teleport-switch-control-description">Yes</span>
          </label>
        </div>
      @else
        <livewire:components.inputs.radio :key="$step" :node="$nodes->$step" />
      @endif
    @elseif ($nodes->$step->display_format === 'Input' || $nodes->$step->display_format === 'Numeric')
      <livewire:components.inputs.text :key="$step" :node="$nodes->$step" />
    @elseif ($nodes->$step->display_format === 'DropDownList')
      <livewire:components.inputs.select :key="$step" :node="$nodes->$step" />
    @endif
  @endforeach
  <button wire:click="$parent.next({{ json_encode($chosen) }})">Next</button>
</div>
