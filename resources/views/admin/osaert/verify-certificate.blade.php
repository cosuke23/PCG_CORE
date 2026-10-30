@extends('layouts.admin')

@section('title', 'OSAERT Certificate Verification')

@section('content')
    <div class="min-h-screen bg-gray-100 py-10 px-4">
        <div class="max-w-3xl mx-auto">

            <div class="bg-white rounded-2xl shadow-lg overflow-hidden">

                <div class="bg-[#0033A0] px-6 py-8 text-center text-white">
                    <div class="flex justify-center mb-4">
                        <div class="w-20 h-20 bg-white rounded-full flex items-center justify-center shadow">
                            <i class="fas fa-certificate text-[#0033A0] text-4xl"></i>
                        </div>
                    </div>

                    <h1 class="text-2xl md:text-3xl font-bold">
                        OSAERT Certificate Verification
                    </h1>

                    <p class="mt-2 text-blue-100">
                        Oil Spill Awareness and Emergency Response Training
                    </p>
                </div>

                <div class="p-6 md:p-10">

                    <div class="flex justify-center mb-8">
                        <div
                            class="inline-flex items-center gap-2 px-5 py-3 rounded-full bg-green-100 text-green-700 font-bold">
                            <i class="fas fa-circle-check"></i>
                            Certificate Verified
                        </div>
                    </div>

                    @php
                        $year = substr(trim((string) $trainee->end_year), -4);

                        $certificateNumber = 'OSAERT-' . $year . '-' . str_pad($trainee->id, 6, '0', STR_PAD_LEFT);
                    @endphp

                    <div class="text-center mb-8">
                        <p class="text-sm text-gray-500 uppercase tracking-wider">
                            Certificate Number
                        </p>

                        <p class="text-xl font-bold text-[#0033A0] mt-1">
                            {{ $certificateNumber }}
                        </p>
                    </div>

                    <div class="border border-gray-200 rounded-xl overflow-hidden">

                        <div class="bg-gray-50 px-5 py-4 border-b border-gray-200">
                            <h2 class="font-bold text-gray-800">
                                Certificate Information
                            </h2>
                        </div>

                        <div class="divide-y divide-gray-200">

                            <div class="grid grid-cols-1 md:grid-cols-3 gap-2 px-5 py-4">
                                <div class="font-semibold text-gray-500">
                                    Trainee Name
                                </div>

                                <div class="md:col-span-2 text-gray-900 font-semibold">
                                    {{ $trainee->name }}
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-3 gap-2 px-5 py-4">
                                <div class="font-semibold text-gray-500">
                                    Company / Organization
                                </div>

                                <div class="md:col-span-2 text-gray-900">
                                    {{ $trainee->company }}
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-3 gap-2 px-5 py-4">
                                <div class="font-semibold text-gray-500">
                                    Training
                                </div>

                                <div class="md:col-span-2 text-gray-900">
                                    Oil Spill Awareness and Emergency Response Training
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-3 gap-2 px-5 py-4">
                                <div class="font-semibold text-gray-500">
                                    Date Started
                                </div>

                                <div class="md:col-span-2 text-gray-900">
                                    {{ $trainee->start_year }}
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-3 gap-2 px-5 py-4">
                                <div class="font-semibold text-gray-500">
                                    Date Completed
                                </div>

                                <div class="md:col-span-2 text-gray-900">
                                    {{ $trainee->end_year }}
                                </div>
                            </div>

                        </div>
                    </div>

                    <div class="mt-8 p-5 bg-blue-50 border border-blue-200 rounded-xl">
                        <div class="flex gap-4">

                            <div class="flex-shrink-0">
                                <div
                                    class="w-10 h-10 rounded-full bg-[#0033A0] text-white flex items-center justify-center">
                                    <i class="fas fa-shield-check"></i>
                                </div>
                            </div>

                            <div>
                                <h3 class="font-bold text-[#0033A0]">
                                    Authentic Certificate
                                </h3>

                                <p class="text-sm text-gray-600 mt-1">
                                    This certificate has been verified against the
                                    official OSAERT training records.
                                </p>
                            </div>

                        </div>
                    </div>

                    <div class="text-center mt-8">
                        <p class="text-xs text-gray-400">
                            This verification page was generated by the OSAERT
                            Training Management System.
                        </p>
                    </div>

                </div>

            </div>

        </div>
    </div>
@endsection

@section('styles')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
@endsection
