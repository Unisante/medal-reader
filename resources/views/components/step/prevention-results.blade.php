@props([
    'gender_node',
    'diagnoses_per_cc',
    'df_to_display',
    'chosen_complaint_categories',
    'final_diagnoses',
    'cache_key',
])

{{-- In theory the view shouldn't do any logic BUT that would actually reduce the cache usage --}}
@php
  $cache = Cache::get($cache_key);
  $full_nodes = $cache['full_nodes'];
  $final_diagnoses = $cache['final_diagnoses'];
  $health_cares = $cache['health_cares'];
  $female_gender_answer_id = $cache['female_gender_answer_id'];
  $gender = $gender_node === $female_gender_answer_id ? 'f' : 'm';
  $all_diag = [];
  $df_to_display_after_check = [];

  foreach ($diagnoses_per_cc as $diag_cc_id => $dd_per_cc) {
      foreach ($dd_per_cc as $dd_id => $label) {
          $all_diag[$dd_id] = $label;
      }
  }

  foreach ($df_to_display as $potential_df_id => $potential_df) {
      foreach ($diagnoses_per_cc as $diags_cc_dd => $diags_per_cc) {
          foreach ($diags_per_cc as $diag_id => $l) {
              $df = $final_diagnoses[$potential_df_id];
              $df_id = $df['id'];
              $dd_to_check = $df['diagnosis_id'];
              if ($dd_to_check === $diag_id) {
                  if (isset(array_filter($chosen_complaint_categories)[$df['cc']])) {
                      $df_to_display_after_check[$df_id] = '';
                  }
              }
          }
      }
  }
  // if () {
  //       if (!Str::contains($diagnose, '[M]')) {

  $high_dfs = collect($df_to_display_after_check)->filter(function ($df, $k) use (
      $final_diagnoses,
      $all_diag,
      $gender,
  ) {
      $dd_id = $final_diagnoses[$k]['diagnosis_id'];
      $gender_is_ok = false;
      if (
          isset($all_diag[$dd_id]) &&
          ((!Str::contains($all_diag[$dd_id], '[M]') && $gender === 'f') ||
              (!Str::contains($all_diag[$dd_id], '[F]') && $gender === 'm'))
      ) {
          $gender_is_ok = true;
      }
      return $final_diagnoses[$k]['level_of_urgency'] === 10 && $gender_is_ok;
  });

  $moderate_dfs = collect($df_to_display_after_check)->filter(function ($df, $k) use (
      $final_diagnoses,
      $all_diag,
      $gender,
  ) {
      $dd_id = $final_diagnoses[$k]['diagnosis_id'];
      $gender_is_ok = false;
      if (
          isset($all_diag[$dd_id]) &&
          ((!Str::contains($all_diag[$dd_id], '[M]') && $gender === 'f') ||
              (!Str::contains($all_diag[$dd_id], '[F]') && $gender === 'm'))
      ) {
          $gender_is_ok = true;
      }
      return $final_diagnoses[$k]['level_of_urgency'] === 9 && $gender_is_ok;
  });
  $light_dfs = collect($df_to_display_after_check)->filter(function ($df, $k) use (
      $final_diagnoses,
      $all_diag,
      $gender,
  ) {
      $dd_id = $final_diagnoses[$k]['diagnosis_id'];
      $gender_is_ok = false;
      if (
          isset($all_diag[$dd_id]) &&
          ((!Str::contains($all_diag[$dd_id], '[M]') && $gender === 'f') ||
              (!Str::contains($all_diag[$dd_id], '[F]') && $gender === 'm'))
      ) {
          $gender_is_ok = true;
      }
      return $final_diagnoses[$k]['level_of_urgency'] === 8 && $gender_is_ok;
  });
  $other_dfs = collect($df_to_display_after_check)->filter(function ($df, $k) use (
      $final_diagnoses,
      $all_diag,
      $gender,
  ) {
      $dd_id = $final_diagnoses[$k]['diagnosis_id'];
      $gender_is_ok = false;
      if (
          isset($all_diag[$dd_id]) &&
          ((!Str::contains($all_diag[$dd_id], '[M]') && $gender === 'f') ||
              (!Str::contains($all_diag[$dd_id], '[F]') && $gender === 'm'))
      ) {
          $gender_is_ok = true;
      }
      return $final_diagnoses[$k]['level_of_urgency'] < 8 && $gender_is_ok;
  });
@endphp

<div>
  <h2 class="mb-5">RECOMMANDATIONS EVIPREV 2024</h2>
  @if (empty($df_to_display))
    <p>No results</p>
  @else
    @if (count($high_dfs))
      <div class="input-container mb-5">
        <h2 class="fw-normal mb-3">Highly recommended</h2>
        @foreach ($high_dfs as $diagnosis_id => $drugs)
          <div wire:key="{{ 'df-' . $diagnosis_id }}">
            <div class="input-container mb-3">
              @if (isset($final_diagnoses[$diagnosis_id]['description']['en']) && $final_diagnoses[$diagnosis_id]['description']['en'])
                @markdown($final_diagnoses[$diagnosis_id]['description']['en'])
              @else
                {{ $final_diagnoses[$diagnosis_id]['label']['en'] }}
              @endif
            </div>
          </div>
        @endforeach
      </div>
    @endif

    @if (count($moderate_dfs))
      <div class="input-container mb-5">
        <h2 class="fw-normal mb-3">Recommended</h2>
        @foreach ($moderate_dfs as $diagnosis_id => $drugs)
          <div wire:key="{{ 'df-' . $diagnosis_id }}">
            <div class="input-container mb-3">
              @if (isset($final_diagnoses[$diagnosis_id]['description']['en']) && $final_diagnoses[$diagnosis_id]['description']['en'])
                @markdown($final_diagnoses[$diagnosis_id]['description']['en'])
              @else
                {{ $final_diagnoses[$diagnosis_id]['label']['en'] }}
              @endif
            </div>
          </div>
        @endforeach
      </div>
    @endif

    @if (count($light_dfs))
      <div class="input-container mb-5">
        <h2 class="fw-normal mb-3">To be considered</h2>
        @foreach ($light_dfs as $diagnosis_id => $drugs)
          <div wire:key="{{ 'df-' . $diagnosis_id }}">
            <div class="input-container mb-3">
              @if (isset($final_diagnoses[$diagnosis_id]['description']['en']) && $final_diagnoses[$diagnosis_id]['description']['en'])
                @markdown($final_diagnoses[$diagnosis_id]['description']['en'])
              @else
                {{ $final_diagnoses[$diagnosis_id]['label']['en'] }}
              @endif
            </div>
          </div>
        @endforeach
      </div>
    @endif

    @if (count($other_dfs))
      <div class="input-container mb-5">
        <h2 class="fw-normal mb-3">Further information</h2>
        @foreach ($other_dfs as $diagnosis_id => $drugs)
          <div wire:key="{{ 'df-' . $diagnosis_id }}">
            <div class="input-container mb-3">
              @if (isset($final_diagnoses[$diagnosis_id]['description']['en']) && $final_diagnoses[$diagnosis_id]['description']['en'])
                @markdown($final_diagnoses[$diagnosis_id]['description']['en'])
              @else
                {{ $final_diagnoses[$diagnosis_id]['label']['en'] }}
              @endif
            </div>
          </div>
        @endforeach
      </div>
    @endif
  @endif
  <div>
    <div class="d-flex justify-content-end">
      <button class="btn button-unisante m-1" onclick="window.location.href = window.location.href;">Restart</button>
    </div>
  </div>
</div>
