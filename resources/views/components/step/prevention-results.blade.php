@props(['df_to_display', 'final_diagnoses', 'cache_key'])

<div>
  <h2 class="mb-5">RECOMMANDATIONS EVIPREV 2024</h2>
  @if (empty($df_to_display))
    <p>No results</p>
  @else
    <div class="input-container mb-5">
      <h2 class="fw-normal mb-3">Highly recommended</h2>
      @foreach ($df_to_display as $diagnosis_id => $drugs)
        @if ($final_diagnoses[$diagnosis_id]['level_of_urgency'] === 10)
          <div wire:key="{{ 'df-' . $diagnosis_id }}">
            <label class="form-check-label" for="{{ $diagnosis_id }}">
              {{-- @markdown($final_diagnoses[$diagnosis_id]['label']['en']) --}}
              @if (isset($final_diagnoses[$diagnosis_id]['description']['en']))
                @markdown($final_diagnoses[$diagnosis_id]['description']['en'])
              @endif
            </label>
          </div>
        @endif
      @endforeach
    </div>

    <div class="input-container mb-5">
      <h2 class="fw-normal mb-3">Moderate</h2>
      @foreach ($df_to_display as $diagnosis_id => $drugs)
        @if ($final_diagnoses[$diagnosis_id]['level_of_urgency'] === 9)
          <div wire:key="{{ 'df-' . $diagnosis_id }}">
            <label class="form-check-label" for="{{ $diagnosis_id }}">
              {{-- @markdown($final_diagnoses[$diagnosis_id]['label']['en']) --}}
              @if (isset($final_diagnoses[$diagnosis_id]['description']['en']))
                @markdown($final_diagnoses[$diagnosis_id]['description']['en'])
              @endif
            </label>
          </div>
        @endif
      @endforeach
    </div>

    <div class="input-container mb-5">
      <h2 class="fw-normal mb-3">Light</h2>
      @foreach ($df_to_display as $diagnosis_id => $drugs)
        @if ($final_diagnoses[$diagnosis_id]['level_of_urgency'] === 8)
          <div wire:key="{{ 'df-' . $diagnosis_id }}">
            <label class="form-check-label" for="{{ $diagnosis_id }}">
              {{-- @markdown($final_diagnoses[$diagnosis_id]['label']['en']) --}}
              @if (isset($final_diagnoses[$diagnosis_id]['description']['en']))
                @markdown($final_diagnoses[$diagnosis_id]['description']['en'])
              @endif
            </label>
          </div>
        @endif
      @endforeach
    </div>

    <div class="input-container mb-5">
      <h2 class="fw-normal mb-3">Other informations</h2>
      @foreach ($df_to_display as $diagnosis_id => $drugs)
        @if ($final_diagnoses[$diagnosis_id]['level_of_urgency'] < 8)
          <div wire:key="{{ 'df-' . $diagnosis_id }}">
            <label class="form-check-label" for="{{ $diagnosis_id }}">
              {{-- @markdown($final_diagnoses[$diagnosis_id]['label']['en']) --}}
              @if (isset($final_diagnoses[$diagnosis_id]['description']['en']))
                @markdown($final_diagnoses[$diagnosis_id]['description']['en'])
              @endif
            </label>
          </div>
        @endif
      @endforeach
    </div>
  @endif
  <div>
    <div class="d-flex justify-content-end">
      <button class="btn button-unisante m-1" onclick="window.location.href = window.location.href;">Restart</button>
    </div>
  </div>
</div>
