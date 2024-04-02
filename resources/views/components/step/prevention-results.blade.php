@props(['df_to_display', 'final_diagnoses', 'cache_key'])

<div>
  <h2 class="fw-normal">Results</h2>
  @forelse ($df_to_display as $diagnosis_id => $drugs)
    <div wire:key="{{ 'df-' . $diagnosis_id }}">
      <div class="input-container m-5">
        <label class="form-check-label" for="{{ $diagnosis_id }}">
          @markdown($final_diagnoses[$diagnosis_id]['label']['en'])
          @if (isset($final_diagnoses[$diagnosis_id]['description']['en']))
            @markdown($final_diagnoses[$diagnosis_id]['description']['en'])
          @endif
        </label>
      </div>
    </div>
  @empty
    <p>No results</p>
  @endforelse
  <div>
    <div class="d-flex justify-content-end">
      <button class="btn button-unisante m-1" onclick="window.location.href = window.location.href;">Restart</button>
    </div>
  </div>
</div>
