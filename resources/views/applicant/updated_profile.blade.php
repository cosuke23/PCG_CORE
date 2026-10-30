@extends('layouts.app') <!-- Adjust if your layout file is different -->

@section('content')
<div class="container mx-auto py-10 px-6">
    <div class="max-w-4xl mx-auto bg-white shadow-lg rounded-2xl p-8">

        <h2 class="text-3xl font-bold mb-6 text-center">Your Profile</h2>

        @if($profile)
            <div class="flex flex-col md:flex-row items-center md:items-start space-y-6 md:space-y-0 md:space-x-8">
                <!-- Photo -->
                <div class="flex-shrink-0">
                    <img src="{{ $profile->photo ? asset('storage/'.$profile->photo) : asset('images/default-2x2.png') }}"
                         alt="Profile Photo" class="w-32 h-32 rounded-lg object-cover border-2 border-gray-300">
                </div>

                <!-- Personal Info -->
                <div class="flex-1 space-y-2">
                    <p><strong>Profile Code:</strong> {{ $profile->profile_code ?? '-' }}</p>
                    <p><strong>District:</strong> {{ $profile->district ?? '-' }}</p>
                    <p><strong>Full Name:</strong> {{ $profile->first_name }} {{ $profile->middle_name }} {{ $profile->last_name }} {{ $profile->extension_name }}</p>
                    <p><strong>Birthday:</strong> {{ $profile->birthday ? \Carbon\Carbon::parse($profile->birthday)->format('F d, Y') : '-' }}</p>
                    <p><strong>Sex:</strong> {{ $profile->sex ?? '-' }}</p>
                </div>
            </div>

            <hr class="my-6 border-gray-300">

            <!-- Permanent Address -->
            <div class="mb-6">
                <h3 class="text-xl font-semibold mb-2">Permanent Address</h3>
                <p><strong>Street:</strong> {{ $profile->perm_street ?? '-' }}</p>
                <p><strong>City/Municipality:</strong> {{ $profile->perm_city ?? '-' }}</p>
                <p><strong>Province:</strong> {{ $profile->perm_province ?? '-' }}</p>
                <p><strong>Zip Code:</strong> {{ $profile->perm_zipcode ?? '-' }}</p>
                <p><strong>Years of Residency:</strong> {{ $profile->perm_years_residency ?? '-' }}</p>
            </div>

            <!-- Present Address -->
            <div class="mb-6">
                <h3 class="text-xl font-semibold mb-2">Present Address</h3>
                <p><strong>Street:</strong> {{ $profile->pres_street ?? '-' }}</p>
                <p><strong>City/Municipality:</strong> {{ $profile->pres_city ?? '-' }}</p>
                <p><strong>Province:</strong> {{ $profile->pres_province ?? '-' }}</p>
                <p><strong>Zip Code:</strong> {{ $profile->pres_zipcode ?? '-' }}</p>
                <p><strong>Years of Residency:</strong> {{ $profile->pres_years_residency ?? '-' }}</p>
            </div>

            <!-- Education -->
            <div>
                <h3 class="text-xl font-semibold mb-2">Educational Background</h3>
                <p><strong>Highest Education Level:</strong> {{ $profile->education_level ?? '-' }}</p>

                @if($profile->education_level == 'Tertiary')
                    <p><strong>Tertiary Course:</strong> {{ $profile->tertiary_course ?? '-' }}</p>
                    <p><strong>Tertiary School:</strong> {{ $profile->tertiary_school ?? '-' }}</p>
                    <p><strong>Tertiary Year Graduated:</strong> {{ $profile->tertiary_year_graduated ?? '-' }}</p>
                @endif

                @if(in_array($profile->education_level, ['Secondary', 'Tertiary']))
                    <p><strong>Secondary School:</strong> {{ $profile->secondary_school ?? '-' }}</p>
                    <p><strong>Track / Strand:</strong> {{ $profile->secondary_strand ?? '-' }}</p>
                    <p><strong>Secondary Year Graduated:</strong> {{ $profile->secondary_year_graduated ?? '-' }}</p>
                @endif

                <p><strong>Elementary School:</strong> {{ $profile->elementary_school ?? '-' }}</p>
                <p><strong>Elementary Year Graduated:</strong> {{ $profile->elementary_year_graduated ?? '-' }}</p>
            </div>

            <div class="mt-6 text-center">
                <a href="{{ route('profile.update') }}"
                   class="bg-blue-600 hover:bg-blue-700 px-6 py-2 rounded-lg font-semibold text-white transition">
                   Edit Profile
                </a>
            </div>
        @else
            <p class="text-center text-gray-500">You haven't created a profile yet. <a href="{{ route('profile.edit') }}" class="text-blue-500 underline">Create Profile</a></p>
        @endif
    </div>
</div>
@endsection
