<div class="mb-2">
  @php $full_nodes=Cache::get($cache_key)["full_nodes"] @endphp
  <label class="form-label" for="{{ $node_id }}">
    {{ $full_nodes[$node_id]['label']['en'] }}
    @if ($full_nodes[$node_id]['description']['en'])
      <div x-data="{ open: false }">
        <button class="btn btn-sm btn-outline-secondary m-1" @click="open = ! open">Description</button>
        <div x-show="open">
          <p>{{ $full_nodes[$node_id]['description']['en'] }}</p>
        </div>
      </div>
    @endif
  </label>
  @foreach ($full_nodes[$node_id]['answers'] as $answer)
    <div class="form-check">
      <input class="form-check-input" type="radio" wire:model.live="answer" value="{{ $answer['id'] }}"
        name="{{ $node_id }}" id="{{ $answer['id'] }}" wire:key="{{ $answer['id'] }}">
      <label class="form-check-label" for="{{ $answer['id'] }}">{{ $answer['label']['en'] }}</label>
    </div>
  @endforeach
</div>
