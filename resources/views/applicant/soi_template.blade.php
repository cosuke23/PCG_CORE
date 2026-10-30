<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Summary of Information</title>
    <style>
        /* To remove browser-generated headers/footers (Date, Page Title, URL):
           Browsers usually hide these if the page margins are set correctly
           and the header/footer is disabled in the print dialog.
        */
        @page {
            size: A4 landscape;
            margin: 10mm;
            /* Browser default headers/footers are often removed with small margins */
        }

        body {
            font-family: 'Times New Roman', serif;
            color: black;
            background: #e5e7eb;
            margin: 0;
            padding: 20px;
        }

        .form-container {
            width: 100%;
            max-width: 1050px;
            margin: auto;
            padding: 25px;
            background: white;
            border: 1px solid black;
            min-height: 180mm;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .table-custom {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .table-custom td {
            border: 1px solid black;
            padding: 4px 10px;
            font-size: 13px;
        }

        @media print {
            body {
                background: white;
                padding: 0;
                margin: 0;
            }

            .form-container {
                border: none;
                box-shadow: none;
                margin: 0;
                padding: 0;
            }

            .print-hidden {
                display: none !important;
            }

            /* Additional force to hide browser chrome */
            @page {
                margin: 0;
            }

            body {
                padding: 10mm;
            }
        }
    </style>
    <link rel="stylesheet" href="{{ asset('css/tailwind.css') }}">
</head>

<body>

    <div class="form-container">
        <div class="text-center">
            <h1 class="font-bold tracking-widest underline text-lg">RESTRICTED</h1>
            <img src="{{ asset('backgrounds/pcg logo.png') }}" class="mx-auto my-1" style="width: 50px;">
            <div class="font-bold text-lg">PHILIPPINE COAST GUARD</div>
            <div class="font-bold text-lg leading-tight">PAMBANSANG PUNONGHIMPILAN TANOD BAYBAYIN NG PILIPINAS</div>
            <div class="text-sm">(National Headquarters Philippine Coast Guard)</div>
            <div class="text-sm">139 25th St., Port Area, 1018 Manila</div>
            <h2 class="font-bold underline mt-2 text-lg">SUMMARY OF INFORMATION</h2>
            <div class="text-sm">as of {{ date('d M Y') }}</div>
        </div>

        <div class="space-y-3">
            <div class="flex items-end">
                <div class="w-24 font-bold text-md">A. NAME:</div>
                <div class="flex-grow">
                    <div class="flex justify-between text-center font-bold text-md">
                        <span class="w-1/3 border-b border-black">{{ $applicant->last_name }}</span>
                        <span class="w-1/3 border-b border-black">{{ $applicant->first_name }}</span>
                        <span class="w-1/3 border-b border-black">{{ $applicant->middle_name }}</span>
                    </div>
                    <div class="flex justify-between text-center text-[10px] italic">
                        <span class="w-1/3">(LAST NAME)</span>
                        <span class="w-1/3">(FIRST NAME)</span>
                        <span class="w-1/3">(MIDDLE NAME)</span>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div class="flex items-end"><span class="font-bold mr-2">B. RANK:</span><span
                        class="flex-grow border-b border-black font-bold">{{ $applicant->rank }}</span></div>
                <div class="flex items-end"><span class="font-bold mr-2">SERIAL NUMBER:</span><span
                        class="flex-grow border-b border-black font-bold">{{ $applicant->serial_number }}</span></div>
            </div>

            <div class="flex items-end">
                <span class="font-bold mr-2">C. BIRTH DATE AND PLACE:</span>
                <span class="flex-grow border-b border-black font-bold">{{ $applicant->birthday }} /
                    {{ $applicant->birth_place }}</span>
            </div>

            <div>
                <p class="font-bold text-md">D. PERSONAL INFORMATION:</p>
                <table class="table-custom">
                    <tr>
                        <td class="font-bold">Nickname(s): <span class="font-normal">{{ $applicant->nickname }}</span>
                        </td>
                        <td class="font-bold">Gender: <span class="font-normal">{{ $applicant->sex }}</span></td>
                    </tr>
                    <tr>
                        <td class="font-bold">Civil Status: <span
                                class="font-normal">{{ $applicant->civil_status }}</span></td>
                        <td class="font-bold">Citizenship: <span
                                class="font-normal">{{ $applicant->citizenship }}</span></td>
                    </tr>
                    <tr>
                        <td class="font-bold">Religion: <span class="font-normal">{{ $applicant->religion }}</span>
                        </td>
                        <td class="font-bold">Blood Type: <span class="font-normal">{{ $applicant->blood_type }}</span>
                        </td>
                    </tr>
                    <tr>
                        <td class="font-bold">Height (cms): <span class="font-normal">{{ $applicant->height }}</span>
                        </td>
                        <td class="font-bold">Weight (kgs): <span class="font-normal">{{ $applicant->weight }}</span>
                        </td>
                    </tr>
                    <tr>
                        <td class="font-bold">Head Size (cms): <span
                                class="font-normal">{{ $applicant->head_size }}</span></td>
                        <td class="font-bold">Foot Size (cms): <span
                                class="font-normal">{{ $applicant->foot_size }}</span></td>
                    </tr>
                    <tr>
                        <td class="font-bold">Waist Size (cms): <span
                                class="font-normal">{{ $applicant->waist_size }}</span></td>
                        <td class="font-bold">Body Build: <span class="font-normal">{{ $applicant->body_build }}</span>
                        </td>
                    </tr>
                    <tr>
                        <td class="font-bold">Hair Color: <span class="font-normal">{{ $applicant->hair_color }}</span>
                        </td>
                        <td class="font-bold">Skin Color: <span class="font-normal">{{ $applicant->skin_color }}</span>
                        </td>
                    </tr>
                    <tr>
                        <td class="font-bold">Eye Color: <span class="font-normal">{{ $applicant->eye_color }}</span>
                        </td>
                        <td class="font-bold">Distinct Feature: <span
                                class="font-normal">{{ $applicant->distinct_features }}</span></td>
                    </tr>
                    <tr>
                        <td class="font-bold">Home Address:: <span
                                class="font-normal">{{ $applicant->home_address }}</span></td>
                        <td class="font-bold">Permanent Address: <span
                                class="font-normal">{{ $applicant->permanent_address }}</span></td>
                    </tr>
                </table>
            </div>
        </div>

        <div class="text-center font-bold underline text-lg">RESTRICTED</div>
    </div>
    <div class="form-container mt-12">
        <div class="text-center font-bold underline text-lg mt-12">RESTRICTED</div>

        <div class="flex-grow py-4">
            <table class="table-custom">
                <tr>
                    <td class="font-bold w-1/2">E-mail Address: <span
                            class="font-normal">{{ $applicant->email }}</span></td>
                    <td class="font-bold w-1/2">Contact Number(s): <span
                            class="font-normal">{{ $applicant->contact_number }}</span></td>
                </tr>
                <tr>
                    <td class="font-bold">PAG-IBIG Number: <span class="font-normal">{{ $applicant->pagibig }}</span>
                    </td>
                    <td class="font-bold">PhilHealth Number: <span
                            class="font-normal">{{ $applicant->philhealth }}</span></td>
                </tr>
                <tr>
                    <td class="font-bold">SSS Number: <span class="font-normal">{{ $applicant->sss }}</span></td>
                    <td class="font-bold">GSIS Number: <span class="font-normal">{{ $applicant->gsis }}</span></td>
                </tr>
                <tr>
                    <td class="font-bold">Tax Identification Number (TIN): <span
                            class="font-normal">{{ $applicant->tin }}</span></td>
                    <td class="font-bold">Driver's License Number: <span
                            class="font-normal">{{ $applicant->drivers_license }}</span></td>
                </tr>
            </table>

            <p class="font-bold mb-2">Dependents: <span class="font-normal">Entry will include (1) Name; (2)
                    Relationship; (3) Birth date occasional</span></p>
            <table class="table-custom">
                <tr class="font-bold text-center">
                    <td>(1) Name</td>
                    <td>(2) Relationship</td>
                    <td>(3) Birth date</td>
                </tr>

                @foreach ($dependents as $dependent)
                    <tr>
                        <td>{{ strtoupper($dependent->name) }}</td>
                        <td class="text-center">
                            {{ strtoupper($dependent->relationship) }}
                        </td>

                        <td class="text-center">
                            {{ strtoupper(\Carbon\Carbon::parse($dependent->birthdate)->format('F d Y')) }}
                        </td>
                    </tr>
                @endforeach

            </table>
            <h2 class="font-bold text-lg mb-2">E. EDUCATIONAL ATTAINMENT</h2>
            <p class="font-bold mb-2">1. Civilian: <span class="font-normal">Entry shall be from Present to
                    Past</span></p>
            <p class="font-bold mb-1">a. Academic:</p>
            <table class="table-custom">
                <tr class="font-bold text-center">
                    <td>COURSE</td>
                    <td>NAME OF SCHOOL / INSTITUTION AND LOCATION</td>
                    <td>PERIOD OF ATTENDANCE (From – To)</td>
                    <td>STANDING / HONORS / REMARKS</td>
                </tr>

                {{-- MASTERAL --}}
                <tr>
                    <td class="font-bold">Post Graduate</td>
                    <td>
                        {{ $groupedAcademics['MASTERAL'][0]->school ?? 'N/A' }}
                    </td>
                    <td>
                        {{ ($groupedAcademics['MASTERAL'][0]->year_start ?? 'N/A') . ' - ' . ($groupedAcademics['MASTERAL'][0]->year_end ?? 'N/A') }}
                    </td>
                    <td>
                        {{ $groupedAcademics['MASTERAL'][0]->remarks ?? 'N/A' }}
                    </td>
                </tr>

                {{-- TERTIARY --}}
                <tr>
                    <td class="font-bold">
                        Tertiary Education
                        <div class="font-normal">
                            {{ $groupedAcademics['TERTIARY'][0]->course ?? 'N/A' }}
                        </div>
                    </td>
                    <td>
                        {{ $groupedAcademics['TERTIARY'][0]->school ?? 'N/A' }}
                    </td>
                    <td>
                        {{ ($groupedAcademics['TERTIARY'][0]->year_start ?? 'N/A') . ' - ' . ($groupedAcademics['TERTIARY'][0]->year_end ?? 'N/A') }}
                    </td>
                    <td>
                        {{ $groupedAcademics['TERTIARY'][0]->remarks ?? 'N/A' }}
                    </td>
                </tr>

                {{-- SECONDARY --}}
                <tr>
                    <td class="font-bold">Secondary Education</td>
                    <td>
                        {{ $groupedAcademics['SECONDARY'][0]->school ?? 'N/A' }}
                    </td>
                    <td>
                        {{ ($groupedAcademics['SECONDARY'][0]->year_start ?? 'N/A') . ' - ' . ($groupedAcademics['SECONDARY'][0]->year_end ?? 'N/A') }}
                    </td>
                    <td>
                        {{ $groupedAcademics['SECONDARY'][0]->remarks ?? 'N/A' }}
                    </td>
                </tr>

                {{-- PRIMARY --}}
                <tr>
                    <td class="font-bold">Primary Education</td>
                    <td>
                        {{ $groupedAcademics['PRIMARY'][0]->school ?? 'N/A' }}
                    </td>
                    <td>
                        {{ ($groupedAcademics['PRIMARY'][0]->year_start ?? 'N/A') . ' - ' . ($groupedAcademics['PRIMARY'][0]->year_end ?? 'N/A') }}
                    </td>
                    <td>
                        {{ $groupedAcademics['PRIMARY'][0]->remarks ?? 'N/A' }}
                    </td>
                </tr>
            </table>
        </div>

        <div class="text-center font-bold underline text-lg">RESTRICTED</div>
    </div>
    <div class="form-container">
        <div class="text-center font-bold underline text-lg">RESTRICTED</div>

        <div class="flex-grow py-4">
            <p class="font-bold mb-1">b. Vocational:</p>
            <table class="table-custom">
                <tr class="font-bold">
                    <td>COURSE</td>
                    <td>NAME OF SCHOOL / INSTITUTION AND LOCATION</td>
                    <td>PERIOD OF ATTENDANCE (From – To)</td>
                    <td>STANDING / HONORS / REMARKS</td>
                </tr>

                @forelse ($groupedAcademics['VOCATIONAL'] ?? [] as $voc)
                    <tr>
                        <td>{{ $voc->course ?? 'N/A' }}</td>

                        <td>{{ $voc->school ?? 'N/A' }}</td>

                        <td>
                            {{ $voc->year_start ?? 'N/A' }}
                        </td>

                        <td>{{ $voc->remarks ?? 'N/A' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="text-center">N/A</td>
                    </tr>
                @endforelse
            </table>

            <p class="font-bold mb-1">c. Other Trainings (Seminars/Workshops):</p>
            <table class="table-custom">
                <tr class="font-bold">
                    <td>COURSE</td>
                    <td>NAME OF SCHOOL / INSTITUTION AND LOCATION</td>
                    <td>PERIOD OF ATTENDANCE (From – To)</td>
                    <td>STANDING / HONORS / REMARKS</td>
                </tr>

                @forelse ($groupedTrainings['others_local'] ?? [] as $other)
                    <tr>
                        <td>{{ $other->course ?? 'N/A' }}</td>

                        <td>
                            {{ $other->school ?? 'N/A' }}
                            @if ($other->authority)
                                <br><small>{{ $other->authority }}</small>
                            @endif
                        </td>

                        <td>{{ $other->period ?? 'N/A' }}</td>

                        <td>{{ $other->remarks ?? 'N/A' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="text-center">N/A</td>
                    </tr>
                @endforelse
            </table>

            <p class="font-bold mb-1">2. Service Related:</p>
            <p class="font-bold mb-1">a. Coast Guard</p>
            <table class="table-custom">
                <tr class="font-bold">
                    <td>COURSE</td>
                    <td>AUTHORITY (Order)</td>
                    <td>NAME OF SCHOOL / INSTITUTION AND LOCATION</td>
                    <td>PERIOD OF ATTENDANCE (From – To)</td>
                    <td>STANDING / HONORS / REMARKS</td>
                </tr>

                @forelse ($groupedTrainings['coastguard'] ?? [] as $cg)
                    <tr>
                        <td class="text-left">
                            {{ $cg->course ?? 'N/A' }}
                            @if ($cg->type)
                                <br><small>{{ $cg->type }}</small>
                            @endif
                        </td>

                        <td>
                            {{ $cg->authority ?? 'N/A' }}
                        </td>

                        <td class="text-left">
                            {{ $cg->school ?? 'N/A' }}
                        </td>

                        <td>
                            {{ $cg->period ?? 'N/A' }}
                        </td>

                        <td>
                            {{ $cg->remarks ?? 'N/A' }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center">N/A</td>
                    </tr>
                @endforelse
            </table>

            <div class="text-center font-bold underline text-lg">RESTRICTED</div>
        </div>
    </div>
    <div class="form-container">
        <div class="text-center font-bold underline text-lg">RESTRICTED</div>

        <div class="flex-grow py-4">
            <p class="font-bold mb-1">a.1. Others: Courses/Seminars: (Local)</p>
            <table class="table-custom">
                <tr class="font-bold">
                    <td>COURSE</td>
                    <td>NAME OF SCHOOL / INSTITUTION AND LOCATION</td>
                    <td>PERIOD OF ATTENDANCE (From – To)</td>
                    <td>STANDING / HONORS / REMARKS</td>
                </tr>

                @forelse ($groupedTrainings['others_local'] ?? [] as $other)
                    <tr>
                        <td>{{ $other->course ?? 'N/A' }}</td>

                        <td>
                            {{ $other->school ?? 'N/A' }}
                            @if ($other->authority)
                                <br><small>{{ $other->authority }}</small>
                            @endif
                        </td>

                        <td>{{ $other->period ?? 'N/A' }}</td>

                        <td>{{ $other->remarks ?? 'N/A' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="text-center">N/A</td>
                    </tr>
                @endforelse
            </table>
            <p class="font-bold mb-1">a.2. Others: Courses/Seminars: (Foreign)</p>
            <table class="table-custom">
                <tr class="font-bold">
                    <td>COURSE</td>
                    <td>NAME OF SCHOOL / INSTITUTION AND LOCATION</td>
                    <td>PERIOD OF ATTENDANCE (From – To)</td>
                    <td>STANDING / HONORS / REMARKS</td>
                </tr>

                @forelse ($groupedTrainings['others_foreign'] ?? [] as $other)
                    <tr>
                        <td>{{ $other->course ?? 'N/A' }}</td>

                        <td>
                            {{ $other->school ?? 'N/A' }}
                            @if ($other->authority)
                                <br><small>{{ $other->authority }}</small>
                            @endif
                        </td>

                        <td>{{ $other->period ?? 'N/A' }}</td>

                        <td>{{ $other->remarks ?? 'N/A' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="text-center">N/A</td>
                    </tr>
                @endforelse
            </table>

            <p class="font-bold mb-1">b. Military (AFP)</p>
            @php
                $military = $groupedTrainings['military'] ?? collect();

                $getMilitary = function ($type) use ($military) {
                    return $military->firstWhere('type', $type);
                };
            @endphp
            <table class="table-custom">
                <tr class="font-bold">
                    <td>COURSE</td>
                    <td>AUTHORITY (Order)</td>
                    <td>NAME OF SCHOOL / INSTITUTION AND LOCATION</td>
                    <td>PERIOD OF ATTENDANCE (From – To)</td>
                    <td>STANDING / HONORS / REMARKS</td>
                </tr>

                {{-- BASIC TRAINING --}}
                @php $basic = $getMilitary('Basic Training'); @endphp
                <tr>
                    <td class="font-bold">
                        Basic Training<br>
                        <span class="font-normal">
                            {{ $basic->course ?? 'N/A' }}
                        </span>
                    </td>
                    <td>{{ $basic->authority ?? 'N/A' }}</td>
                    <td>{{ $basic->school ?? 'N/A' }}</td>
                    <td>{{ $basic->period ?? 'N/A' }}</td>
                    <td>{{ $basic->remarks ?? 'N/A' }}</td>
                </tr>

                {{-- ADVANCE TRAINING --}}
                @php $advance = $getMilitary('Advance Training'); @endphp
                <tr>
                    <td class="font-bold">
                        Advance Training<br>
                        <span class="font-normal">
                            {{ $basic->course ?? 'N/A' }}
                        </span>
                    </td>
                    <td>{{ $advance->authority ?? 'N/A' }}</td>
                    <td>{{ $advance->school ?? 'N/A' }}</td>
                    <td>{{ $advance->period ?? 'N/A' }}</td>
                    <td>{{ $advance->remarks ?? 'N/A' }}</td>
                </tr>

                {{-- STRATEGIC TRAINING --}}
                @php $strategic = $getMilitary('Strategic Training'); @endphp
                <tr>
                    <td class="font-bold">
                        Strategic Training<br>
                        <span class="font-normal">
                            {{ $basic->course ?? 'N/A' }}
                        </span>
                    </td>
                    <td>{{ $strategic->authority ?? 'N/A' }}</td>
                    <td>{{ $strategic->school ?? 'N/A' }}</td>
                    <td>{{ $strategic->period ?? 'N/A' }}</td>
                    <td>{{ $strategic->remarks ?? 'N/A' }}</td>
                </tr>

                {{-- GENERAL STAFF COURSE --}}
                @php $general = $getMilitary('General Staff Course'); @endphp
                <tr>
                    <td class="font-bold">
                        General Staff Course<br>
                        <span class="font-normal">
                            {{ $basic->course ?? 'N/A' }}
                        </span>
                    </td>
                    <td>{{ $general->authority ?? 'N/A' }}</td>
                    <td>{{ $general->school ?? 'N/A' }}</td>
                    <td>{{ $general->period ?? 'N/A' }}</td>
                    <td>{{ $general->remarks ?? 'N/A' }}</td>
                </tr>
            </table>
        </div>

        <div class="text-center font-bold underline text-lg">RESTRICTED</div>
    </div>
    <div class="form-container">
        <div class="text-center font-bold underline text-lg">RESTRICTED</div>

        <div class="flex-grow py-4">
            <p class="font-bold mb-1">b.1. Others: Courses/Seminars: (Local)</p>
            <table class="table-custom">
                <tr class="font-bold">
                    <td>COURSE/SEMINAR</td>
                    <td>AUTHORITY (Order)</td>
                    <td>NAME OF SCHOOL / INSTITUTION AND LOCATION</td>
                    <td>PERIOD OF ATTENDANCE (From – To)</td>
                    <td>STANDING / HONORS / REMARKS</td>
                </tr>

                @forelse ($groupedTrainings['military_others_local'] ?? [] as $item)
                    <tr>
                        <td>{{ $item->course ?? 'N/A' }}</td>
                        <td>{{ $item->authority ?? 'N/A' }}</td>
                        <td>{{ $item->school ?? 'N/A' }}</td>
                        <td>{{ $item->period ?? 'N/A' }}</td>
                        <td>{{ $item->remarks ?? 'N/A' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center">N/A</td>
                    </tr>
                @endforelse
            </table>

            <p class="font-bold mb-1">b.2. Others: Courses/Seminars: (Foreign)</p>
            <table class="table-custom">
                <tr class="font-bold">
                    <td>COURSE/SEMINAR</td>
                    <td>AUTHORITY (Order)</td>
                    <td>NAME OF SCHOOL / INSTITUTION AND LOCATION</td>
                    <td>PERIOD OF ATTENDANCE (From – To)</td>
                    <td>STANDING / HONORS / REMARKS</td>
                </tr>

                @forelse ($groupedTrainings['military_others_foreign'] ?? [] as $item)
                    <tr>
                        <td>{{ $item->course ?? 'N/A' }}</td>
                        <td>{{ $item->authority ?? 'N/A' }}</td>
                        <td>{{ $item->school ?? 'N/A' }}</td>
                        <td>{{ $item->period ?? 'N/A' }}</td>
                        <td>{{ $item->remarks ?? 'N/A' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center">N/A</td>
                    </tr>
                @endforelse
            </table>

            <p class="font-bold mb-1">F. APPOINTMENTS, PROMOTIONS AND REDUCTIONS: <span class="font-normal">Entry
                    shall be from Present to Past</span></p>
            <table class="table-custom">
                <tr class="font-bold">
                    <td>DESCRIPTION</td>
                    <td>EFFECTIVE DATE</td>
                    <td>AUTHORITY</td>
                </tr>

                @forelse ($appointments as $appointment)
                    <tr>
                        <td class="text-left">
                            {{ strtoupper($appointment->description ?? 'N/A') }}
                        </td>

                        <td style="text-align:center;">
                            {{ $appointment->date ? strtoupper(\Carbon\Carbon::parse($appointment->date)->format('F d, Y')) : 'N/A' }}
                        </td>

                        <td class="text-left">
                            {{ strtoupper($appointment->authority ?? 'N/A') }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" style="text-align:center;">
                            N/A
                        </td>
                    </tr>
                @endforelse
            </table>

            <p class="font-bold mb-1">G. DUTY ASSIGNMENTS AND ADDITIONAL DUTIES: <span class="font-normal">Entry shall
                    be from Present to Past</span></p>
            <p class="font-bold mb-1">1. SHIPBOARD:</p>
            <table class="table-custom">
                <tr class="font-bold">
                    <td>UNIT</td>
                    <td>DUTY/POSITION</td>
                    <td>INCLUSIVE PERIOD (From-To)</td>
                    <td>AUTHORITY</td>
                </tr>

                @forelse ($duties->where('type', 'shipboard') as $duty)
                    <tr>
                        <td class="text-left">
                            {{ strtoupper($duty->unit ?? 'N/A') }}
                        </td>

                        <td class="text-left">
                            {{ strtoupper($duty->position ?? 'N/A') }}
                        </td>

                        <td style="text-align:center;">
                            {{ strtoupper($duty->period ?? 'N/A') }}
                        </td>

                        <td class="text-left">
                            {{ strtoupper($duty->authority ?? 'N/A') }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" style="text-align:center;">N/A</td>
                    </tr>
                @endforelse
            </table>
        </div>

        <div class="text-center font-bold underline text-lg">RESTRICTED</div>
    </div>
    <div class="form-container">
        <div class="text-center font-bold underline text-lg">RESTRICTED</div>

        <div class="flex-grow py-4">
            <p class="font-bold mb-1">1.a. Collateral Duties</p>
            <table class="table-custom">
                <tr class="font-bold">
                    <td>UNIT</td>
                    <td>DUTY/POSITION</td>
                    <td>INCLUSIVE PERIOD (From-To)</td>
                    <td>AUTHORITY</td>
                </tr>

                @forelse ($duties->where('type', 'shipboard_collateral') as $duty)
                    <tr>
                        <td class="text-left">
                            {{ strtoupper($duty->unit ?? 'N/A') }}
                        </td>

                        <td class="text-left">
                            {{ strtoupper($duty->position ?? 'N/A') }}
                        </td>

                        <td style="text-align:center;">
                            {{ strtoupper($duty->period ?? 'N/A') }}
                        </td>

                        <td class="text-left">
                            {{ strtoupper($duty->authority ?? 'N/A') }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" style="text-align:center;">N/A</td>
                    </tr>
                @endforelse
            </table>

            <p class="font-bold mb-1">2. SHORE BASED:</p>
            <table class="table-custom">
                <tr class="font-bold">
                    <td>UNIT</td>
                    <td>DUTY/POSITION</td>
                    <td>INCLUSIVE PERIOD (From-To)</td>
                    <td>AUTHORITY</td>
                </tr>

                @forelse ($duties->where('type', 'shorebased') as $duty)
                    <tr>
                        <td class="text-left">
                            {{ strtoupper($duty->unit ?? 'N/A') }}
                        </td>

                        <td class="text-left">
                            {{ strtoupper($duty->position ?? 'N/A') }}
                        </td>

                        <td style="text-align:center;">
                            {{ strtoupper($duty->period ?? 'N/A') }}
                        </td>

                        <td class="text-left">
                            {{ strtoupper($duty->authority ?? 'N/A') }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" style="text-align:center;">N/A</td>
                    </tr>
                @endforelse
            </table>

            <p class="font-bold mb-1">2.a. Collateral Duties</p>
            <table class="table-custom">
                <tr class="font-bold">
                    <td>UNIT</td>
                    <td>DUTY/POSITION</td>
                    <td>INCLUSIVE PERIOD (From-To)</td>
                    <td>AUTHORITY</td>
                </tr>

                @forelse ($duties->where('type', 'collateral_shorebased') as $duty)
                    <tr>
                        <td class="text-left">
                            {{ strtoupper($duty->unit ?? 'N/A') }}
                        </td>

                        <td class="text-left">
                            {{ strtoupper($duty->position ?? 'N/A') }}
                        </td>

                        <td style="text-align:center;">
                            {{ strtoupper($duty->period ?? 'N/A') }}
                        </td>

                        <td class="text-left">
                            {{ strtoupper($duty->authority ?? 'N/A') }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" style="text-align:center;">N/A</td>
                    </tr>
                @endforelse
            </table>

            <p class="font-bold mb-1">3. OTHERS:</p>
            <p class="font-bold mb-1">3.a. Other Government Service</p>
            <table class="table-custom">
                <tr class="font-bold">
                    <td>UNIT</td>
                    <td>DUTY/POSITION</td>
                    <td>INCLUSIVE PERIOD (From-To)</td>
                    <td>AUTHORITY</td>
                </tr>

                @forelse ($duties->where('type', 'govt') as $duty)
                    <tr>
                        <td class="text-left">
                            {{ strtoupper($duty->unit ?? 'N/A') }}
                        </td>

                        <td class="text-left">
                            {{ strtoupper($duty->position ?? 'N/A') }}
                        </td>

                        <td style="text-align:center;">
                            {{ strtoupper($duty->period ?? 'N/A') }}
                        </td>

                        <td class="text-left">
                            {{ strtoupper($duty->authority ?? 'N/A') }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" style="text-align:center;">N/A</td>
                    </tr>
                @endforelse
            </table>

            <p class="font-bold mb-1">3.b. Non-Government Service</p>
            <table class="table-custom">
                <tr class="font-bold">
                    <td>UNIT</td>
                    <td>DUTY/POSITION</td>
                    <td>INCLUSIVE PERIOD (From-To)</td>
                    <td>AUTHORITY</td>
                </tr>

                @forelse ($duties->where('type', 'non_govt') as $duty)
                    <tr>
                        <td class="text-left">
                            {{ strtoupper($duty->unit ?? 'N/A') }}
                        </td>

                        <td class="text-left">
                            {{ strtoupper($duty->position ?? 'N/A') }}
                        </td>

                        <td style="text-align:center;">
                            {{ strtoupper($duty->period ?? 'N/A') }}
                        </td>

                        <td class="text-left">
                            {{ strtoupper($duty->authority ?? ($duty->agency ?? 'N/A')) }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" style="text-align:center;">N/A</td>
                    </tr>
                @endforelse
            </table>
        </div>

        <div class="text-center font-bold underline text-lg">RESTRICTED</div>
    </div>
    <div class="form-container">
        <div class="text-center font-bold underline text-lg">RESTRICTED</div>

        <div class="flex-grow py-4">
            <h2 class="font-bold text-lg mb-2">H. AWARDS AND RECOGNITIONS:</h2>

            <p class="font-bold mb-1">1. Coast Guard</p>
            <p class="mb-1">a. <span class="font-bold">Awards and Decorations:</span> Entry shall be in order of
                merit of the Awards from Highest down to Lowest</p>
            <table class="table-custom">
                <tr class="font-bold">
                    <td>TYPE OF AWARD(S)</td>
                    <td>AUTHORITY</td>
                </tr>

                @forelse ($groupedCgAwardsDecorations as $type => $items)
                    @foreach ($items as $award)
                        <tr>
                            <td class="text-left">
                                {{ strtoupper($award->type ?? 'N/A') }}
                            </td>

                            <td class="text-left">
                                {{ strtoupper($award->authority ?? 'N/A') }}
                            </td>
                        </tr>
                    @endforeach
                @empty
                    <tr>
                        <td colspan="2" class="text-center">N/A</td>
                    </tr>
                @endforelse
            </table>
            <p class="mb-1">b. <span class="font-bold">Letter of Commendation:</span></p>
            @php $counterL = 1; @endphp

            @foreach ($groupedCgLetters as $type => $items)
                @foreach ($items as $appreciation)
                    <p class="ml-8 mb-1">
                        {{ $counterL++ }}.
                        Letter of {{ $appreciation->type ?? 'Appreciation' }}
                        from {{ strtoupper($appreciation->authority ?? 'N/A') }}
                    </p>
                @endforeach
            @endforeach
            <p class="mb-1">c. <span class="font-bold">Appreciation:</span></p>
            @php $counterA = 1; @endphp

            @foreach ($groupedAppreciations as $type => $items)
                <p class="ml-6 font-semibold uppercase mt-2">
                    {{ $type ?? 'APPRECIATION' }}
                </p>

                @foreach ($items as $appreciation)
                    <p class="ml-10 mb-1">
                        {{ $counterA++ }}.
                        Letter of {{ $appreciation->type ?? 'Appreciation' }}
                        from {{ strtoupper($appreciation->authority ?? 'N/A') }}
                    </p>
                @endforeach
            @endforeach
            <p class="mb-4">d. <span class="font-bold">Plaque:</span></p>
            @php $counterP = 1; @endphp

            @foreach ($groupedPlaques as $type => $items)
                <p class="ml-6 font-semibold uppercase mt-2">
                    {{ $type ?? 'PLAQUE' }}
                </p>

                @foreach ($items as $appreciation)
                    <p class="ml-10 mb-1">
                        {{ $counterP++ }}.
                        Plaque of {{ $appreciation->type ?? 'Recognition' }}
                        from {{ strtoupper($appreciation->authority ?? 'N/A') }}
                    </p>
                @endforeach
            @endforeach
            <p class="font-bold mb-1">2. Military (AFP)</p>
            <p class="mb-1">a. <span class="font-bold">Awards and Decorations:</span> Entry shall be in order of
                merit of the Awards from Highest down to Lowest</p>
            <table class="table-custom">
                <tr class="font-bold">
                    <td>TYPE OF AWARD(S)</td>
                    <td>AUTHORITY</td>
                </tr>

                @forelse($awardsMilitary as $awardsMilitaries)
                    <tr>
                        <td>{{ $awardsMilitaries->type ?? 'N/A' }}</td>
                        <td>{{ strtoupper($awardsMilitaries->authority ?? 'N/A') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td>N/A</td>
                        <td>N/A</td>
                    </tr>
                @endforelse
            </table>
            <p class="mb-1">b. <span class="font-bold">Letter of Commendation:</span></p>
            @php $counterLC = 1; @endphp

            @foreach ($groupedOtherLetters as $type => $items)
                @foreach ($items as $commendation)
                    <p class="ml-8 mb-1">
                        {{ $counterLC++ }}.
                        Letter of {{ $commendation->type ?? 'Commendation' }}
                        from {{ strtoupper($commendation->authority ?? 'N/A') }}
                    </p>
                @endforeach
            @endforeach
            <p class="mb-1">c. <span class="font-bold">Appreciation:</span></p>
            @php $counterOA = 1; @endphp
            @foreach ($groupedOtherAppreciations as $type => $items)
                @foreach ($items as $commendation)
                    <p class="ml-8 mb-1">
                        {{ $counterOA++ }}.
                        Letter of {{ $commendation->type ?? 'Appreciation' }}
                        from {{ strtoupper($commendation->authority ?? 'N/A') }}
                    </p>
                @endforeach
            @endforeach
            <p class="mb-1">d. <span class="font-bold">Plaque:</span></p>
            @php $counterOP = 1; @endphp
            @foreach ($groupedOtherPlaques as $type => $items)
                @foreach ($items as $commendation)
                    <p class="ml-8 mb-1">
                        {{ $counterOP++ }}.
                        Letter of {{ $commendation->type ?? 'Plaque' }}
                        from {{ strtoupper($commendation->authority ?? 'N/A') }}
                    </p>
                @endforeach
            @endforeach
        </div>

        <div class="text-center font-bold underline text-lg">RESTRICTED</div>
    </div>
    <div class="form-container">
        <div class="text-center font-bold underline text-lg">RESTRICTED</div>

        <div class="flex-grow py-4">
            <p class="font-bold mb-1">3. Civilian (Commendations/Appreciations/Plaque):</p>

            <table class="table-custom">
                <tr class="font-bold">
                    <td>TYPE OF AWARD(S)</td>
                    <td>AUTHORITY</td>
                </tr>

                @forelse($civilianAwards as $award)
                    <tr>
                        <td>{{ $award->type ?? 'N/A' }}</td>
                        <td>{{ $award->authority ?? 'N/A' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td>N/A</td>
                        <td>N/A</td>
                    </tr>
                @endforelse
            </table>

            <p class="font-bold mb-2">
                I. CAREER MANAGAMENT PROFILE:
                <span class="font-normal">
                    Entry shall include total number of years, months and days
                </span>
            </p>

            <div class="ml-8 mb-4">

                <p>
                    Student Tour -
                    {{ !empty($careers->student) ? $careers->student : 'N/A' }}
                </p>

                <p>
                    Sea Duty -
                    {{ !empty($careers->sea) ? $careers->sea : 'N/A' }}
                </p>

                <p>
                    Staff Duty -
                    {{ !empty($careers->staff) ? $careers->staff : 'N/A' }}
                </p>

                <p>
                    Instructor Duty -
                    {{ !empty($careers->instructor) ? $careers->instructor : 'N/A' }}
                </p>

                <p>
                    Command (Special / Major Unit) -
                    {{ !empty($careers->command) ? $careers->command : 'N/A' }}
                </p>

            </div>

            <p class="font-bold mb-2">J. ORGANIZATION AFFILIATION: <span class="font-normal">{{ !empty($careers->organization_affiliation) ? $careers->organization_affiliation : 'N/A' }}</span></p>
            <p class="font-bold mb-2">K. CIVIL SERVICE ELIGIBILITY/LICENSURE: <span class="font-normal">{{ !empty($careers->civil_service_eligibility) ? $careers->civil_service_eligibility : 'N/A' }}</span></p>
            <p class="font-bold mb-2">L. DATE OF COMPULSORY RETIREMENT: <span class="font-normal">{{ !empty($careers->compulsory_retirement) ? $careers->compulsory_retirement : 'N/A' }}</span></p>
        </div>

        <div class="text-center font-bold underline text-lg">RESTRICTED</div>
    </div>
    <div class="form-container">
        <div class="text-center font-bold underline text-lg">RESTRICTED</div>

        <div class="flex-grow py-4 flex flex-col justify-center items-center">

            <p class="text-center mb-8">
                I HEREBY CERTIFY that the aforementioned entries/information is true and correct to the best of my
                knowledge and belief:
            </p>
            <div
                class="w-40 h-48 border-2 border-black flex items-center justify-center text-center p-2 mb-12 overflow-hidden">
                @if (!empty($personnel_data->photo_2x2))
                    <img src="{{ \App\Support\Uploads::url('documents_toupload/' . $personnel_data->photo_2x2) }}"
                        class="w-full h-full object-cover" alt="2x2 Photo">
                @else
                    <p class="text-sm">
                        2x2 Latest formal picture<br>
                        White background<br>
                        In prescribe uniform<br>
                        No headgear<br>
                        Visible nameplate
                    </p>
                @endif

            </div>

            <div class="w-full grid grid-cols-2 gap-16 text-center">
                <div class="mt-12">
                    <div class="font-bold underline mb-1">{{ strtoupper($applicant->first_name) }}
                        {{ strtoupper(substr($applicant->middle_name, 0, 1)) }}.
                        {{ strtoupper($applicant->last_name) }}</div>
                    <div class="font-bold">
                        {{ strtoupper($applicant->rank) }}&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;PCG
                    </div>
                </div>
                <div>
                    <div class="text-left ml-[-120px] mb-6 font-bold">AUTHENTICATED BY:</div>
                    <div class="font-bold underline mb-1">RONALYN C GULMATICO</div>
                    <div class="font-bold">P/ENS&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;PCG
                    </div>
                    <div>Adjutant, MEPCOM</div>
                </div>
            </div>
        </div>

        <div class="text-center font-bold underline text-lg">RESTRICTED</div>
    </div>
    <button onclick="window.print()"
        class="print-hidden fixed bottom-10 right-10 bg-blue-700 text-white font-bold py-3 px-6 rounded-full shadow-2xl z-50">
        🖨️ PRINT DOCUMENT
    </button>
</body>

</html>
