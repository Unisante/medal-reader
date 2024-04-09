@props(['nodes', 'cache_key', 'nodes_to_save', 'full_nodes', 'villages', 'algorithm_type', 'debug_mode'])

<h2 class="fw-normal pb-3">Registration</h2>

@foreach ($nodes as $node_id => $answer_id)
  <div wire:key="{{ 'registration-' . $node_id }}" class="mb-2">
    @if ($node_id === 'birth_date')
      <div class="input-container mb-2">
        <label class="form-label" for="birth_date">Date of birth</label>
        <input class="form-control @error('current_nodes.registration.birth_date') is-invalid @enderror"
          wire:model.blur="current_nodes.registration.birth_date" type="date" pattern="\d{4}-\d{2}-\d{2}"
          id="birth_date" name="birth_date" required>
        @error('current_nodes.registration.birth_date')
          <span class="invalid-feedback" role="alert">{{ $message }}</span>
        @enderror
      </div>
    @elseif ($node_id === 'first_name')
      <div class="input-container mb-2">
        <label class="form-label" for="first_name">First name</label>
        <input class="form-control" wire:model.live="current_nodes.registration.first_name" type="text"
          id="first_name" name="birth_date">
      </div>
    @elseif ($node_id === 'last_name')
      <div class="input-container mb-2">
        <label class="form-label" for="last_name">Last name</label>
        <input class="form-control" wire:model.live="current_nodes.registration.last_name" type="text" id="last_name"
          name="last_name">
      </div>
    @else
      @switch($full_nodes[$node_id]['display_format'])
        @case('RadioButton')
          <x-inputs.radio step="registration" :node_id="$node_id" :full_nodes="$full_nodes" />
        @break

        @case('DropDownList')
          <x-inputs.select step="registration" :node_id="$node_id" :full_nodes="$full_nodes" />
        @break

        @case('Input')
          <x-inputs.numeric step="registration" :node_id="$node_id" :full_nodes="$full_nodes" :label="$nodes_to_save[$node_id]['label']" :$debug_mode />
        @break

        @case('String')
          <x-inputs.text step="registration" :node_id="$node_id" :full_nodes="$full_nodes" :is_background_calc="false" />
        @break

        @case('Autocomplete')
          @php $node_label=$full_nodes[$node_id]['label']['en'] @endphp
          <x-inputs.datalist step="registration" :node_id="$node_id" :villages="$villages" :label="$node_label" />
        @break

        @case('Formula')
          @if ($debug_mode)
            <x-inputs.text step="registration" :node_id="$node_id" :full_nodes="$full_nodes" :is_background_calc="true" />
          @endif
        @break

        @default
      @endswitch
    @endif

  </div>
@endforeach
<div class="d-flex justify-content-end">
  @if ($algorithm_type === 'dynamic')
    <button class="btn button-unisante mt-3" wire:click="goToStep('first_look_assessment')">
      First Look Assessment
    </button>
  @else
    <button class="btn button-unisante mt-3" wire:click="goToStep('first_look_assessment')">
      Survey selection
    </button>
  @endif
</div>
