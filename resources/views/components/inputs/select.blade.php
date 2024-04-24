@props(['step', 'node_id', 'full_nodes'])
@php
  $answers = collect($full_nodes[$node_id]['answers'])
      ->filter(function ($answer) {
          return $answer['value'] !== 'not_available';
      })
      ->sortBy('reference');
@endphp

<div class="input-container mb-2">
  <div wire:ignore>
    @if ($full_nodes[$node_id]['description']['en'])
      @if (Str::contains($full_nodes[$node_id]['description']['en'], '[i]') || $this->algorithm_type === 'dynamic')
        <div x-data="{ open: false }" wire:key="{{ 'desc' . $node_id }}">
          <div class="d-flex justify-content-between" style="align-items: flex-start;">
            <label class="form-label required">
              {{ $full_nodes[$node_id]['label']['en'] }}
            </label>
            <button class="btn btn-sm btn-outline-primary" @click="open = ! open">
              <i class="bi bi-info-circle"></i>
            </button>
          </div>
          <div x-show="open">
            @markdown(Str::remove('[i]', $full_nodes[$node_id]['description']['en']))
          </div>
        </div>
      @else
        <label class="form-label required">
          {{ $full_nodes[$node_id]['label']['en'] }}
        </label>
        @markdown($full_nodes[$node_id]['description']['en'])
      @endif
    @else
      <label class="form-label required">
        {{ $full_nodes[$node_id]['label']['en'] }}
      </label>
    @endif
  </div>
  <select wire:model.live='{{ "current_nodes.$step.$node_id" }}' id="{{ $node_id }}"
    class="form-select @error('current_nodes.' . $step . '.' . $node_id) is-invalid @enderror">
    <option selected>Select an answer</option>

    @foreach ($answers as $answer)
      <option wire:key="{{ 'answer-' . $answer['id'] }}" value="{{ $answer['id'] }}">
        {{ $answer['label']['en'] }}
      </option>
    @endforeach
  </select>
  @error('current_nodes.' . $step . '.' . $node_id)
    <div class="invalid-feedback" role="alert">{{ $message }}</div>
  @enderror
</div>
