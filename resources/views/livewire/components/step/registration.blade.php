<div>
  {{-- add first and last name inputs --}}
  <label class="form-label" for="birth_date">Date of birth</label>
  <input class="form-control" wire:model.live="date_of_birth" type="date" pattern="\d{4}-\d{2}-\d{2}" id="birth_date"
    name="birth_date">
  @foreach ($nodes as $node)
    <div wire:key="{{ 'registration-' . $node['id'] }}" class="mb-2">
      @switch($node['display_format'])
        @case('RadioButton')
          <div>
            <livewire:components.inputs.radio wire:key="{{ 'registration-node-' . $node['id'] }}" :node="$node"  :cache_key="$cache_key"  />
          </div>
        @break

        @case('DropDownList')
          <div>
            <livewire:components.inputs.select wire:key="{{ 'registration-node-' . $node['id'] }}" :node="$node" :cache_key="$cache_key"  />
          </div>
        @break

        @case('Input')
          <div>
            <livewire:components.inputs.numeric wire:key="{{ 'registration-node-' . $node['id'] }}" :node="$node" :cache_key="$cache_key" />
          </div>
        @break

        @case('String')
          <div>
            <livewire:components.inputs.numeric wire:key="{{ 'registration-node-' . $node['id'] }}" :node="$node" :cache_key="$cache_key" />
          </div>
        @break

        @case('Autocomplete')
          <x-inputs.datalist :node="$node" :villages="$villages" />
        @break

        @default
      @endswitch
    </div>
  @endforeach
</div>
