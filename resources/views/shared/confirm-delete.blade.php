<div class="modal fade" id="vaDeleteModal" tabindex="-1" aria-labelledby="vaDeleteTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content va-confirm-dialog">
        <div class="modal-body p-4">
            <div class="va-confirm-icon"><i class="mdi mdi-alert-outline" aria-hidden="true"></i></div>
            <h4 id="vaDeleteTitle" class="mb-2">Delete this record?</h4>
            <p class="text-muted mb-0"><strong id="vaDeleteName"></strong> will be removed from active records.</p>
        </div>
        <div class="modal-footer border-0 pt-0 px-4 pb-4">
            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-danger" id="vaDeleteConfirm"><span class="spinner-border spinner-border-sm me-1 d-none" id="vaDeleteSpinner" aria-hidden="true"></span><span id="vaDeleteLabel">Yes, delete</span></button>
        </div>
    </div></div>
</div>
