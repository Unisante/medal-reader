<div>
  @php $full_nodes=Cache::get($cache_key)["full_nodes"] @endphp
  {{-- add first and last name inputs --}}
  <label class="form-label" for="birth_date">Date of birth</label>
  <input class="form-control" wire:model.live="date_of_birth" type="date" pattern="\d{4}-\d{2}-\d{2}" id="birth_date"
    name="birth_date">
  @foreach ($nodes as $node_id)
    <div wire:key="{{ 'registration-' . $node_id }}" class="mb-2">
      @switch($full_nodes[$node_id]['display_format'])
        @case('RadioButton')
          <div>
            <livewire:components.inputs.radio wire:key="{{ 'registration-node-' . $node_id }}" :node_id="$node_id"
              :cache_key="$cache_key" />
          </div>
        @break

        @case('DropDownList')
          <div>
            <livewire:components.inputs.select wire:key="{{ 'registration-node-' . $node_id }}" :node_id="$node_id"
              :cache_key="$cache_key" />
          </div>
        @break

        @case('Input')
          <div>
            <livewire:components.inputs.numeric wire:key="{{ 'registration-node-' . $node_id }}" :node_id="$node_id"
              :cache_key="$cache_key" />
          </div>
        @break

        @case('String')
          <div>
            <livewire:components.inputs.text wire:key="{{ 'registration-node-' . $node_id }}" :value=null :node_id="$node_id"
              :cache_key="$cache_key" />
          </div>
        @break

        @case('Autocomplete')
          {{-- @php $node['cache_key']=$cache_key @endphp --}}
          <x-inputs.datalist :node_id="$node_id" :villages="$villages" :cache_key="$cache_key" />
        @break

        @default
      @endswitch
    </div>
  @endforeach
</div>
