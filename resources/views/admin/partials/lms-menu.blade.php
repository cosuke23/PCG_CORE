<aside class="w-64 bg-slate-900 text-white flex flex-col">

    {{-- Logo / Title --}}
    <div class="p-6 border-b border-slate-700">

        <h2 class="text-xl font-bold">
            MEPCOM Admin
        </h2>

    </div>


    {{-- Navigation --}}
    <nav class="p-4 space-y-2 flex-1">


        {{-- Dashboard --}}
        <a href="{{ route('admin.dashboard') }}"
            class="flex items-center px-4 py-3 rounded bg-blue-600 hover:bg-slate-800 transition">

            <i class="fas fa-gauge-high w-5 mr-2"></i>

            Dashboard

        </a>

        <a href="{{ route('admin.osaert.index') }}"
            class="flex items-center gap-3 px-4 py-3 rounded-lg
   {{ request()->routeIs('admin.osaert.*')
       ? 'bg-blue-700 text-white'
       : 'text-gray-300 hover:bg-gray-800 hover:text-white' }}">

            <i class="fas fa-water text-lg"></i>

            <span>OSAERT</span>
        </a>

        <a href="{{ route('admin.training-applications.index') }}"
            class="flex items-center gap-3 px-4 py-3 rounded-lg
          text-gray-300 hover:text-white hover:bg-gray-800
          transition">

            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
            </svg>

            <span>Training Applications</span>
        </a>

        {{-- HR Analytics --}}
        <a href="{{ route('admin.hr-analytics') }}"
            class="flex items-center px-4 py-3 rounded hover:bg-slate-800 transition">

            <i class="fas fa-chart-line w-5 mr-2"></i>

            HR Analytics

        </a>


        {{-- Administrative Track --}}
        <a href="{{ route('administrative.tracks') }}"
            class="flex items-center px-4 py-3 rounded hover:bg-slate-800 transition">

            <i class="fas fa-graduation-cap w-5 mr-2"></i>

            Administrative Track

        </a>


        {{-- Operational Track --}}
        <a href="{{ route('operation_tracks.index') }}"
            class="flex items-center px-4 py-3 rounded hover:bg-slate-800 transition">

            <i class="fas fa-ship w-5 mr-2"></i>

            Operational Track

        </a>


        {{-- Administrative Training --}}
        <a href="{{ route('personnel.administrative.training') }}"
            class="flex items-center px-4 py-3 rounded hover:bg-slate-800 transition">

            <i class="fas fa-user-graduate w-5 mr-2"></i>

            Administrative Training

        </a>


        {{-- Operational Training --}}
        <a href="{{ route('personnel.operational.training') }}"
            class="flex items-center px-4 py-3 rounded hover:bg-blue-700 transition">

            <i class="fas fa-ship w-5 mr-2"></i>

            Operational Training

        </a>


        {{-- LMS Management --}}
        <div class="pt-3">

            <div class="px-4 mb-2 text-xs font-semibold text-slate-400 uppercase">

                LMS Management

            </div>


            {{-- Training Courses --}}
            <a href="{{ route('admin.training-courses.index') }}"
                class="flex items-center px-4 py-3 rounded hover:bg-blue-700 transition">

                <i class="fas fa-book-open w-5 mr-2"></i>

                Training Courses

            </a>

        </div>


        {{-- Add Unit Account --}}
        <button type="button" id="openUnitModalBtn"
            class="w-full flex items-center text-left px-4 py-3 rounded hover:bg-blue-700 transition">

            <i class="fas fa-plus w-5 mr-2"></i>

            Add Unit Account

        </button>


        {{-- Logout --}}
        <a href="{{ route('admin.logout.legacy') }}" class="flex items-center px-4 py-3 rounded hover:bg-red-700 transition">

            <i class="fas fa-right-from-bracket w-5 mr-2"></i>

            Logout

        </a>

    </nav>

</aside>
