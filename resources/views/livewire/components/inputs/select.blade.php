<div class="mb-2">
  @php $full_nodes=Cache::get($cache_key)["full_nodes"] @endphp
  <label class="form-label" for="{{ $node_id }}">
    {{ $full_nodes[$node_id]['label']['en'] }}
    @if ($full_nodes[$node_id]['description']['en'])
      <div x-data="{ open: false }">
        <button class="btn btn-sm btn-outline-secondary m-1" @click="open = ! open">
          <i class="bi bi-info-circle"> Description</i>
        </button>
        <div x-show="open">
          <p>{{ $full_nodes[$node_id]['description']['en'] }}</p>
        </div>
      </div>
    @endif
  </label>
  <select wire:model.live="answer" id="{{ $node_id }}" class="form-select">
    <option selected>Select an answer</option>
    {{ $answers = collect($full_nodes[$node_id]['answers'])->filter(function ($answer) {
            return $answer['value'] !== 'not_available';
        })->sortBy('reference') }}
    @foreach ($answers as $answer)
      <option value="{{ $answer['id'] }}">{{ $answer['label']['en'] }}</option>
    @endforeach
  </select>
</div>
