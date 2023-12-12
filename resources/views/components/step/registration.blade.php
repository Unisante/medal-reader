@props(['nodes', 'cache_key', 'nodes_to_save'])

@php
  $cache_data = Cache::get($cache_key);
  $full_nodes = $cache_data['full_nodes'];
  $villages = $cache_data['villages'];
@endphp

{{-- @dump($nodes) --}}

@foreach ($nodes as $node_id => $answer_id)
  <div wire:key="{{ 'registration-' . $node_id }}" class="mb-2">
    @if ($node_id === 'birth_date')
      <label class="form-label" for="birth_date">Date of birth</label>
      <input class="form-control" wire:model.live="current_nodes.registration.birth_date" type="date"
        pattern="\d{4}-\d{2}-\d{2}" id="birth_date" name="birth_date">
    @elseif ($node_id === 'first_name')
      <div>
        <label class="form-label" for="first_name">First name</label>
        <input class="form-control" wire:model.live="current_nodes.registration.first_name" type="text"
          id="first_name" name="birth_date">
      </div>
    @elseif ($node_id === 'last_name')
      <label class="form-label" for="last_name">Last name</label>
      <input class="form-control" wire:model.live="current_nodes.registration.last_name" type="text" id="last_name"
        name="last_name">
    @else
      @switch($full_nodes[$node_id]['display_format'])
        @case('RadioButton')
          <x-inputs.radio step="registration" :node_id="$node_id" :cache_key="$cache_key" />
        @break

        @case('DropDownList')
          <x-inputs.select step="registration" :node_id="$node_id" :cache_key="$cache_key" />
        @break

        @case('Input')
          <x-inputs.numeric step="registration" :node_id="$node_id" :cache_key="$cache_key" />
        @break

        @case('String')
          <x-inputs.text step="registration" :node_id="$node_id" :cache_key="$cache_key" :is_background_calc="false" />
        @break

        @case('Autocomplete')
          <x-inputs.datalist step="registration" :node_id="$node_id" :villages="$villages" :cache_key="$cache_key" />
        @break

        @case('Formula')
          <x-inputs.text step="registration" :node_id="$node_id" :cache_key="$cache_key" :is_background_calc="true" />
        @break

        @default
      @endswitch
    @endif

  </div>
@endforeach

<button class="btn btn-sm btn-outline-primary m-1"
  wire:click="goToStep('first_look_assessment')">first_look_assessment</button>
