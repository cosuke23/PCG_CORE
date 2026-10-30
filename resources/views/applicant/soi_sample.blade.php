@extends('layouts.app')

@section('content')
<div class="max-w-5xl mx-auto my-10 bg-white p-12 border border-gray-300 shadow-lg text-sm font-sans">

    <div class="text-center mb-8">
        <div class="flex justify-center items-center gap-4 mb-2">
            <div class="w-16 h-16 bg-gray-200 rounded-full">PCG Logo</div>
            <div class="text-center">
                <h2 class="font-bold text-lg">PHILIPPINE COAST GUARD</h2>
                <h3 class="font-bold">PAMBANSANG PUNONGHIMPILAN TANOD BAYBAYIN NG PILIPINAS</h3> [cite: 2]
                <p class="italic">(National Headquarters Philippine Coast Guard)</p> [cite: 3]
                <p>139 25th St., Port Area, 1018 Manila</p> [cite: 4, 5]
            </div>
            <div class="w-16 h-16 bg-gray-200 rounded-full">PCG Logo</div>
        </div>
        <h1 class="font-bold text-xl underline mt-6">SUMMARY OF INFORMATION</h1> [cite: 6]
        <p>(as of {{ \Carbon\Carbon::parse($record->updated_at)->format('d F Y') }})</p>
    </div>

    <div class="grid grid-cols-2 gap-4 mb-6">
        <div>
            <span class="font-bold">A. NAME:</span>
            <span class="border-b border-black px-4">{{ $record->last_name }}, {{ $record->first_name }} {{ $record->middle_name }}</span> [cite: 8, 9]
        </div>
        <div class="flex justify-between">
            <div><span class="font-bold">B. RANK:</span> {{ $record->rank }}</div> [cite: 10]
            <div><span class="font-bold">SERIAL NUMBER:</span> {{ $record->serial_number }}</div> [cite: 10]
        </div>
    </div>

    <div class="mb-6">
        <span class="font-bold">C. BIRTH DATE AND PLACE:</span>
        <span class="uppercase">{{ $record->birth_date->format('F d, Y') }} - {{ $record->birth_place }}</span> [cite: 11]
    </div>

    <h3 class="font-bold mb-2">D. PERSONAL INFORMATION:</h3> [cite: 12]
    <div class="grid grid-cols-2 border border-black mb-6">
        <div class="p-2 border-r border-b border-black"><strong>Nickname:</strong> {{ $record->nickname ?? 'N/A' }}</div> [cite: 13]
        <div class="p-2 border-b border-black"><strong>Gender:</strong> {{ $record->gender }}</div> [cite: 13]
        <div class="p-2 border-r border-b border-black"><strong>Civil Status:</strong> {{ $record->civil_status }}</div> [cite: 13]
        <div class="p-2 border-b border-black"><strong>Blood Type:</strong> {{ $record->blood_type }}</div> [cite: 13]
        <div class="p-2 border-r border-b border-black"><strong>Height/Weight:</strong> {{ $record->height_cm }}cm / {{ $record->weight_kg }}kg</div> [cite: 13]
        <div class="p-2 border-b border-black"><strong>Email:</strong> {{ $record->email }}</div> [cite: 13]
        <div class="p-2 border-r border-black"><strong>TIN:</strong> {{ $record->tin_no }}</div> [cite: 13]
        <div class="p-2"><strong>PhilHealth:</strong> {{ $record->philhealth_no }}</div> [cite: 13]
    </div>

    <h3 class="font-bold mb-2">E. EDUCATIONAL ATTAINMENT (Civilian)</h3> [cite: 16, 17]
    <table class="w-full border-collapse border border-black mb-6">
        <thead>
            <tr class="bg-gray-100">
                <th class="border border-black p-1 text-xs">COURSE</th>
                <th class="border border-black p-1 text-xs">SCHOOL/LOCATION</th>
                <th class="border border-black p-1 text-xs">PERIOD</th>
            </tr>
        </thead>
        <tbody>
            @foreach($record->education_history as $edu) [cite: 19]
            <tr>
                <td class="border border-black p-1">{{ $edu['course'] }}</td>
                <td class="border border-black p-1">{{ $edu['school'] }}</td>
                <td class="border border-black p-1 text-center">{{ $edu['years'] }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <h3 class="font-bold mb-2">H. AWARDS AND RECOGNITIONS (Coast Guard)</h3> [cite: 53, 54]
    <table class="w-full border-collapse border border-black mb-10">
        <thead>
            <tr class="bg-gray-100">
                <th class="border border-black p-1 text-xs">TYPE OF AWARD</th>
                <th class="border border-black p-1 text-xs">AUTHORITY</th>
            </tr>
        </thead>
        <tbody>
            @forelse($record->awards_decorations as $award) [cite: 56]
            <tr>
                <td class="border border-black p-1 uppercase">{{ $award['type'] }}</td>
                <td class="border border-black p-1">{{ $award['authority'] }}</td>
            </tr>
            @empty
            <tr><td colspan="2" class="border border-black p-1 text-center">N/A</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="mt-12">
        <p class="mb-8">I HEREBY CERTIFY that the aforementioned entries / information are true and correct to the best of my knowledge and belief:</p> [cite: 85]

        <div class="flex justify-between items-end">
            <div class="text-center w-1/3">
                <p class="font-bold border-b border-black uppercase">{{ $record->first_name }} {{ $record->middle_name[0] }}. {{ $record->last_name }}</p> [cite: 86]
                <p>{{ $record->rank }} PCG</p> [cite: 87]
            </div>

            <div class="text-center w-1/3">
                <p class="text-xs italic">Authenticated By:</p> [cite: 88]
                <p class="font-bold border-b border-black mt-4">ZEDRIC P RECTO</p> [cite: 89]
                <p>ENS PCG</p> [cite: 90]
                <p class="text-xs">Command Adjutant, CGHRMC</p> [cite: 91]
            </div>
        </div>
    </div>
</div>
@endsection
