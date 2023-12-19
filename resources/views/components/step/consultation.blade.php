@props(['substep', 'nodes', 'cache_key', 'nodes_to_save', 'full_nodes', 'villages'])

@foreach ($nodes as $title => $system)
  {{-- System container --}}
  <div wire:key="{{ 'system-' . $substep . $title }}">
    @if (count($system))
      <h4>{{ $title }}</h4>
      @foreach ($system as $node_id => $answer_id)
        <div wire:key="{{ 'nodes-' . $node_id }}">
          @if (isset($full_nodes[$node_id]['display_format']))
            @switch($full_nodes[$node_id]['display_format'])
              @case('RadioButton')
                <x-inputs.radio step='{{"consultation.$substep.$title"}}' :node_id="$node_id"
                  :full_nodes="$full_nodes"/>
              @break

              @case('String')
                <x-inputs.text step="consultation.{{ $substep }}.{{ $title }}" :node_id="$node_id"
                  :full_nodes="$full_nodes" :is_background_calc="false" />
              @break

              @case('DropDownList')
                <x-inputs.select step="consultation.{{ $substep }}.{{ $title }}" :node_id="$node_id"
                  :full_nodes="$full_nodes" />
              @break

              @case('Input')
                <x-inputs.numeric step="consultation.{{ $substep }}.{{ $title }}" :node_id="$node_id"
                  :full_nodes="$full_nodes" />
              @break

              @case('Formula')
                <x-inputs.text step="consultation.{{ $substep }}.{{ $title }}" :node_id="$node_id"
                  :value="$nodes_to_save[$node_id]" :full_nodes="$full_nodes" :is_background_calc="true" />
              @break

              @case('Reference')
                <x-inputs.text step="consultation.{{ $substep }}.{{ $title }}" :node_id="$node_id"
                  :value="$nodes_to_save[$node_id]" :full_nodes="$full_nodes" :is_background_calc="true" />
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
