@props(['file'])

<div class="modal fade" id="start" tabindex="-1" aria-labelledby="startLabel" aria-hidden="true">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="startLabel">New consultation</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-footer d-flex justify-content-between">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <button class="btn button-unisante" type="button" data-bs-toggle="modal" data-bs-target="#patientsTable">
          Find Patient
        </button>
        <a href="#" id="start_consultation" class="btn button-unisante">
          Blank consultation
        </a>
      </div>
    </div>
  </div>
</div>
