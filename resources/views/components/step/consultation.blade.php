@props(['nodes', 'cache_key'])

@php
  $cache = Cache::get($cache_key);
  $full_nodes = $cache['full_nodes'];
@endphp

@dump($nodes['priority_sign'])
@dump($nodes['general'])

@foreach ($nodes as $title => $system)
  {{-- System container --}}
  <div wire:key="{{ 'system-' . $title }}">
    @if (count($system))
      <h4>{{ $title }}</h4>
      @foreach ($system as $node_id => $answer_id)
        <div wire:key="{{ 'nodes-' . $node_id }}">
          @switch($full_nodes[$node_id]['display_format'])
            @case('RadioButton')
              <div>
                <x-inputs.radio step="consultation.{{ $title }}" :node_id="$node_id" :cache_key="$cache_key" />
              </div>
            @break

            @case('String')
              <div>
                <x-inputs.text step="consultation.{{ $title }}" :node_id="$node_id" :cache_key="$cache_key"
                  :is_background_calc="false" />
              </div>
            @break

            @case('DropDownList')
              <div>
                <x-inputs.select step="consultation.{{ $title }}" :node_id="$node_id" :cache_key="$cache_key" />

              </div>
            @break

            @case('Input')
              <div>
                <x-inputs.numeric step="consultation.{{ $title }}" :node_id="$node_id" :cache_key="$cache_key" />

              </div>
            @break

            @case('Formula')
              <div>
                <x-inputs.text step="consultation.{{ $title }}" :node_id="$node_id" :cache_key="$cache_key"
                  :is_background_calc="truey" />

              </div>
            @break

            @case('Reference')
              <div>
                <x-inputs.text step="consultation.{{ $title }}" :node_id="$node_id" :cache_key="$cache_key"
                  :is_background_calc="true" />
              </div>
            @break

            @default
          @endswitch
        </div>
      @endforeach
    @endif
  </div>
  @if ($loop->last)
    <button class="btn btn-sm btn-outline-primary m-1" wire:click="goToStep('tests')">tests</button>
  @endif
@endforeach
