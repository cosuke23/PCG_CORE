
<br>
<br>
<br>
@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-gray-100 py-10 px-6">
    <!-- HEADER -->
    <div class="max-w-6xl mx-auto bg-white shadow-xl rounded-2xl overflow-hidden">

        <div class="bg-blue-900 text-white p-8">
            <h1 class="text-3xl font-bold">
                MEP Training and Doctrine Institute (MEPTDI)
            </h1>
            <p class="text-sm mt-2 opacity-90">
                Marine Environmental Protection Command (MEPCOM)
            </p>
        </div>

        <!-- BODY -->
        <div class="p-8 space-y-8">

            <!-- DESCRIPTION -->
            <section>
                <h2 class="text-xl font-semibold text-gray-800 mb-3">
                    Overview
                </h2>

                <p class="text-gray-700 leading-relaxed">
                    The <span class="font-semibold">MEP Training and Doctrine Institute (MEPTDI)</span>
                    is responsible for the education and training of MEPCOM personnel in marine environmental knowledge
                    and skills for the protection, preservation, and conservation of the marine environment.
                </p>
            </section>

            <!-- MISSION -->
            <section class="bg-blue-50 p-6 rounded-xl border border-blue-100">
                <h2 class="text-xl font-semibold text-blue-900 mb-3">
                    Mission
                </h2>

                <p class="text-gray-700 leading-relaxed">
                    To ensure that all MEPCOM personnel are properly trained, educated, and indoctrinated
                    in marine environmental protection principles, enhancing operational readiness and environmental stewardship.
                </p>
            </section>

            <!-- FUNCTIONS -->
            <section>
                <h2 class="text-xl font-semibold text-gray-800 mb-3">
                    Core Functions
                </h2>

                <ul class="list-disc pl-6 space-y-2 text-gray-700">
                    <li>Conduct education and training for MEPCOM personnel</li>
                    <li>Develop marine environmental protection competencies</li>
                    <li>Formulate, review, and update doctrinal policies</li>
                    <li>Conduct doctrinal research and studies</li>
                    <li>Support environmental preservation and conservation initiatives</li>
                </ul>
            </section>

            <!-- ROLE -->
            <section class="bg-gray-50 p-6 rounded-xl border">
                <h2 class="text-xl font-semibold text-gray-800 mb-3">
                    Institutional Role
                </h2>

                <p class="text-gray-700 leading-relaxed">
                    As an administrative support unit of MEPCOM, MEPTDI plays a vital role in shaping
                    the professional development of personnel through structured training programs,
                    policy development, and continuous doctrinal improvement.
                </p>
            </section>

            <!-- IMPORTANCE -->
            <section>
                <h2 class="text-xl font-semibold text-gray-800 mb-3">
                    Strategic Importance
                </h2>

                <div class="grid md:grid-cols-3 gap-4">

                    <div class="p-4 bg-white border rounded-xl shadow-sm">
                        <h3 class="font-semibold text-blue-800">Education</h3>
                        <p class="text-sm text-gray-600 mt-1">
                            Builds foundational environmental knowledge among personnel.
                        </p>
                    </div>

                    <div class="p-4 bg-white border rounded-xl shadow-sm">
                        <h3 class="font-semibold text-blue-800">Training</h3>
                        <p class="text-sm text-gray-600 mt-1">
                            Enhances operational readiness through structured programs.
                        </p>
                    </div>

                    <div class="p-4 bg-white border rounded-xl shadow-sm">
                        <h3 class="font-semibold text-blue-800">Doctrine</h3>
                        <p class="text-sm text-gray-600 mt-1">
                            Ensures policies remain updated and aligned with environmental standards.
                        </p>
                    </div>

                </div>
            </section>

            <!-- FOOTER NOTE -->
            <div class="text-center text-sm text-gray-500 pt-6 border-t">
                Marine Environmental Protection Command (MEPCOM) • MEPTDI Information Drive
            </div>

        </div>
    </div>
</div>
@endsection
