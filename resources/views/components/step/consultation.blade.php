@props(['nodes', 'cache_key', 'nodes_to_save'])

@php
  $cache = Cache::get($cache_key);
  $full_nodes = $cache['full_nodes'];
@endphp

{{-- @dump($nodes['priority_sign']) --}}
{{-- @dump($nodes['general']) --}}

@foreach ($nodes as $title => $system)
  {{-- System container --}}
  <div wire:key="{{ 'system-' . $title }}">
    @if (count($system))
      <h4>{{ $title }}</h4>
      @foreach ($system as $node_id => $answer_id)
        <div wire:key="{{ 'nodes-' . $node_id }}">
          @if (isset($full_nodes[$node_id]['display_format']))
            @switch($full_nodes[$node_id]['display_format'])
              @case('RadioButton')
                <x-inputs.radio step="consultation.{{ $title }}" :node_id="$node_id" :cache_key="$cache_key" />
              @break

              @case('String')
                <x-inputs.text step="consultation.{{ $title }}" :node_id="$node_id" :cache_key="$cache_key"
                  :is_background_calc="false" />
              @break

              @case('DropDownList')
                <x-inputs.select step="consultation.{{ $title }}" :node_id="$node_id" :cache_key="$cache_key" />
              @break

              @case('Input')
                <x-inputs.numeric step="consultation.{{ $title }}" :node_id="$node_id" :cache_key="$cache_key" />
              @break

              @case('Formula')
                <x-inputs.text step="consultation.{{ $title }}" :node_id="$node_id" :value="$nodes_to_save[$node_id]"
                  :cache_key="$cache_key" :is_background_calc="true" />
              @break

              @case('Reference')
                <x-inputs.text step="consultation.{{ $title }}" :node_id="$node_id" :value="$nodes_to_save[$node_id]"
                  :cache_key="$cache_key" :is_background_calc="true" />
              @break

              @default
            @endswitch
          @endif
        </div>
      @endforeach
    @endif
  </div>
  @if ($loop->last)
    <button class="btn btn-sm btn-outline-primary m-1" wire:click="goToStep('tests')">tests</button>
  @endif
@endforeach
