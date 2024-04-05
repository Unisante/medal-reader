@props(['step', 'node_id', 'full_nodes'])

<div class="input-container mb-2">

  <div wire:ignore>
    @if ($full_nodes[$node_id]['description']['en'])
      @if (Str::contains($full_nodes[$node_id]['description']['en'], '[i]'))
        <div x-data="{ open: false }" wire:key="{{ 'desc' . $node_id }}">
          <div class="d-flex justify-content-between" style="align-items: flex-start;">
            <label class="form-label">
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
        <label class="form-label">
          {{ $full_nodes[$node_id]['label']['en'] }}
        </label>
        @markdown($full_nodes[$node_id]['description']['en'])
      @endif
    @else
      <label class="form-label d-flex justify-content-between">
        {{ $full_nodes[$node_id]['label']['en'] }}
      </label>
    @endif
  </div>
  @foreach ($full_nodes[$node_id]['answers'] as $answer)
    <div wire:key="{{ 'answer-' . $answer['id'] }}" class="form-check">
      <input class="form-check-input" type="radio"
        wire:model.live.number="current_nodes.{{ $step }}.{{ $node_id }}" value={{ $answer['id'] }}
        name="{{ $node_id }}" id="{{ $answer['id'] }}">
      <label class="form-check-label" for="{{ $answer['id'] }}">
        {{ $answer['label']['en'] }}
      </label>
      @if ($loop->last)
        @error('{{ "current_nodes.$step.$node_id" }}')
          <div class="invalid-feedback" role="alert">{{ $message }}</div>
        @enderror
      @endif
    </div>
  @endforeach
</div>
