{{-- Leave at a glance on the HR admin's landing pages (App\Support\AdminLeaveSummary). --}}
@php $leaveSummary = \App\Support\AdminLeaveSummary::get(); @endphp
@if ($leaveSummary)
    <section class="pcg-card pcg-mb lsum" aria-labelledby="lsum-heading">
        <div class="lsum__body">
            <span class="lsum__icon" aria-hidden="true"><i class="fa-solid fa-calendar-days"></i></span>
            <div class="lsum__text">
                <h3 id="lsum-heading">
                    @if ($leaveSummary['pending'])
                        {{ $leaveSummary['pending'] === 1 ? '1 leave request is' : number_format($leaveSummary['pending']) . ' leave requests are' }} waiting for approval
                    @else
                        No leave is waiting for approval
                    @endif
                </h3>
                <p>{{ $leaveSummary['awayToday'] === 1 ? '1 member is' : number_format($leaveSummary['awayToday']) . ' members are' }} on leave today ·
                    {{ number_format($leaveSummary['startingThisWeek']) }} starting in the next 7 days</p>
            </div>
            <div class="lsum__actions">
                <a class="pcg-btn {{ $leaveSummary['pending'] && $leaveSummary['canApprove'] ? 'pcg-btn--primary' : 'pcg-btn--secondary' }}" href="{{ route('admin.leave.index') }}">
                    {{ $leaveSummary['pending'] && $leaveSummary['canApprove'] ? 'Review leave' : 'Open leave' }}
                </a>
                <a class="pcg-btn pcg-btn--ghost" href="{{ route('admin.leave.credits') }}">Leave credits</a>
            </div>
        </div>
    </section>
@endif
