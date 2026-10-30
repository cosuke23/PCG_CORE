@if ((int) $doc->uploaded_by === (int) session('admin_user_id'))
    <span class="pcg-table__muted">Uploaded by you — another admin verifies</span>
@else
    <form method="POST" action="{{ route('admin.documents.verify', $doc->id) }}" class="pcg-inline-form">
        @csrf
        <label class="pcg-visually-hidden" for="rr-{{ $doc->id }}">Remarks for {{ $doc->title }}</label>
        <input class="pcg-input pcg-input--sm" id="rr-{{ $doc->id }}" name="review_remarks" maxlength="2000" placeholder="Remarks (required to reject)">
        <button type="submit" name="status" value="verified" class="pcg-btn pcg-btn--secondary pcg-btn--sm">Verify</button>
        <button type="submit" name="status" value="rejected" class="pcg-btn pcg-btn--secondary pcg-btn--sm">Reject</button>
    </form>
@endif
