@props(['step', 'node_id', 'full_nodes'])

<div class="input-container mb-2">
  <label class="form-label">
    {{ $full_nodes[$node_id]['label']['en'] }}
    @if ($full_nodes[$node_id]['description']['en'])
      @markdown($full_nodes[$node_id]['description']['en'])
    @endif
  </label>
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
