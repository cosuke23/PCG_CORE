{{-- Re-enlistment process of one member (App\Support\ReenlistmentCases). Params: $person, $case, $requirements, $canManage --}}
@php
    $status = $case->status ?? 'not_started';
    $done = $case->done ?? [];
    $count = count(array_intersect_key($done, $requirements));
@endphp
<section class="pcg-card pcg-mb" aria-labelledby="case-heading">
    <div class="pcg-card__header">
        <h3 id="case-heading">Re-enlistment process: {{ \App\Support\ReenlistmentCases::STATUSES[$status] }} · {{ $count }} of {{ count($requirements) }} requirements</h3>
    </div>
    <div class="pcg-card__body">
        @if (!\App\Support\ReenlistmentCases::ready())
            <p class="pcg-alert pcg-alert--warning">The re-enlistment process needs a database update that has not been applied yet.</p>
        @else
            <form method="POST" action="{{ route('admin.reenlistment.case', $person->id) }}" class="lc-form">
                @csrf
                <fieldset class="pcg-field" @disabled(!$canManage)>
                    <legend class="pcg-label">Requirements</legend>
                    @foreach ($requirements as $key => $label)
                        <label class="pcg-check">
                            <input type="checkbox" name="done[]" value="{{ $key }}" @checked(isset($done[$key]))>
                            <span>{{ $label }}@if (isset($done[$key])) <span class="lc-muted">· done {{ \Illuminate\Support\Carbon::parse($done[$key])->format('M j, Y') }}</span>@endif</span>
                        </label>
                    @endforeach
                </fieldset>
                <div class="pcg-field">
                    <label class="pcg-label" for="case-status">Status</label>
                    <select class="pcg-input" id="case-status" name="status" @disabled(!$canManage)>
                        @foreach (\App\Support\ReenlistmentCases::STATUSES as $k => $label)
                            <option value="{{ $k }}" @selected($status === $k)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <p class="pcg-help">The member gets a notification when the status changes.</p>
                </div>
                <div class="pcg-field">
                    <label class="pcg-label" for="case-note">Note</label>
                    <textarea class="pcg-input" id="case-note" name="note" rows="2" maxlength="1000" @disabled(!$canManage)>{{ $case->note ?? '' }}</textarea>
                </div>
                @if ($canManage)
                    <button type="submit" class="pcg-btn pcg-btn--primary">Save the process</button>
                @else
                    <p class="pcg-help">Your role can view the process but not change it.</p>
                @endif
                @if ($case)
                    <p class="pcg-help">Last changed {{ \Illuminate\Support\Carbon::parse($case->updated_at)->timezone('Asia/Manila')->format('M j, Y g:i A') }}{{ $case->updated_by_name ? ' by ' . $case->updated_by_name : '' }}.</p>
                @endif
            </form>
        @endif
    </div>
</section>
