@props(['nodes', 'cache_key'])

@php
  $cache_data = Cache::get($cache_key);
  $full_nodes = $cache_data['full_nodes'];
  $villages = $cache_data['villages'];
@endphp
{{-- add first and last name inputs --}}
<label class="form-label" for="birth_date">Date of birth</label>
<input class="form-control" wire:model.live="date_of_birth" type="date" pattern="\d{4}-\d{2}-\d{2}" id="birth_date"
  name="birth_date">
@foreach ($nodes as $node_id => $answer_id)
  <div wire:key="{{ 'registration-' . $node_id }}" class="mb-2">
    @if (isset($full_nodes[$node_id]))
      @switch($full_nodes[$node_id]['display_format'])
        @case('RadioButton')
          <div>
            <x-inputs.radio :node_id="$node_id" :cache_key="$cache_key" />
          </div>
        @break

        @case('DropDownList')
          <div>
            <x-inputs.select :node_id="$node_id" :cache_key="$cache_key" />
          </div>
        @break

        @case('Input')
          <div>
            <x-inputs.numeric :node_id="$node_id" :cache_key="$cache_key" />
          </div>
        @break

        @case('String')
          <div>
            <x-inputs.text :node_id="$node_id" :cache_key="$cache_key" />
          </div>
        @break

        @case('Autocomplete')
          {{-- @php $node['cache_key']=$cache_key @endphp --}}
          <x-inputs.datalist :node_id="$node_id" :villages="$villages" :cache_key="$cache_key" />
        @break

        @default
      @endswitch
    @endif

  </div>
@endforeach
