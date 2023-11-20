<div>
    @dump($nodes)
  @foreach ($nodes as $node)
    @if ($node['display_format'] === 'RadioButton')
      {{-- @dump($node->category) --}}
      {{-- @if ($node->category === 'complaint_category')
        <livewire:components.inputs.toggle :key="$step" :node="$node" />
        <div class="form-check">
          <label class="form-check-label" for="{{ $node['id'] }}">{{ $node->label->en }}</label>
          <label class="custom-control teleport-switch">
            <span class="teleport-switch-control-description">No</span>
            <input type="checkbox" class="teleport-switch-control-input" name="{{ $node['id'] }}" id="{{ $node['id'] }}"
              value="{{ $node['id'] }}" wire:model.live="chosen">
            <span class="teleport-switch-control-indicator"></span>
            <span class="teleport-switch-control-description">Yes</span>
          </label>
        </div>
      @else --}}
      <livewire:components.inputs.radio :key="$node['id']" :node="$node" />
      {{-- @endif --}}
    @elseif ($node['display_format'] === 'Input' || $node['display_format'] === 'Numeric')
      <livewire:components.inputs.text :key="$node['id']" :node="$node" />
    @elseif ($node['display_format'] === 'DropDownList')
      <livewire:components.inputs.select :key="$node['id']" :node="$node" />
    @endif
  @endforeach
  {{-- <button wire:click="goToNextStep({{ json_encode($chosen) }})">Next</button> --}}
</div>
