<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document Checker</title>

    <link rel="stylesheet" href="{{ asset('css/tailwind.css') }}">
</head>

<body id="body"
    class="bg-[#f5f5f7] min-h-screen overflow-hidden transition-all duration-300">

    <div class="flex h-screen">

        <!-- SIDEBAR -->
        <aside class="w-[250px] bg-white border-r border-gray-200 flex flex-col justify-between">

            <div>

                <!-- LOGO -->
                <div class="flex justify-center py-8">

                    <img src="{{ asset('logo.png') }}"
                        class="w-24 h-24 object-contain">

                </div>

                <!-- MENU -->
                <nav class="px-4 space-y-2">

                    <a href="#"
                        class="flex items-center gap-3 px-4 py-3 rounded-xl text-gray-600 hover:bg-gray-100 transition">

                        <span>📊</span>
                        <span>Dashboard</span>

                    </a>

                    <a href="#"
                        class="flex items-center gap-3 px-4 py-3 rounded-xl text-gray-600 hover:bg-gray-100 transition">

                        <span>📝</span>
                        <span>Enlistment</span>

                    </a>

                    <a href="#"
                        class="flex items-center gap-3 px-4 py-3 rounded-xl bg-indigo-100 text-indigo-600 font-semibold">

                        <span>✔</span>
                        <span>Verification</span>

                    </a>

                    <a href="#"
                        class="flex items-center gap-3 px-4 py-3 rounded-xl text-gray-600 hover:bg-gray-100 transition">

                        <span>⚙</span>
                        <span>Settings</span>

                    </a>

                </nav>

            </div>

            <!-- LOGOUT -->
            <div class="p-4">

                <a href="/unit/logout"
                    class="block w-full bg-red-500 hover:bg-red-600 text-white text-center py-4 rounded-2xl font-semibold transition">

                    Logout

                </a>

            </div>

        </aside>

        <!-- MAIN -->
        <main class="flex-1 overflow-y-auto p-10">

            <!-- HEADER -->
            <div class="flex justify-between items-start mb-8">

                <div>

                    <h1 class="text-5xl font-bold text-black">
                        Document Checker
                    </h1>

                    <div class="flex items-center gap-2 text-gray-500 mt-3 text-lg">

                        <span class="text-indigo-500">Home</span>
                        <span>/</span>
                        <span>Verification</span>
                        <span>/</span>
                        <span>Document Checker</span>

                    </div>

                </div>

                <!-- MODE -->
                <button
                    onclick="toggleMode()"
                    id="modeBtn"
                    class="bg-white border border-gray-300 rounded-full px-5 py-3 flex items-center gap-3 shadow-sm hover:shadow-md transition">

                    <span id="modeText"
                        class="text-sm font-semibold text-black">
                        DAY MODE
                    </span>

                    <span id="modeIcon">☀</span>

                </button>

            </div>

            <!-- SEARCH -->
            <div class="flex flex-col lg:flex-row lg:items-center gap-4 mb-6">

                <!-- SEARCH -->
                <div class="relative flex-1">

                    <input type="text"
                        placeholder="Search"
                        class="w-full bg-white border border-gray-300 rounded-2xl py-4 pl-12 pr-4 text-lg focus:ring-2 focus:ring-indigo-300 outline-none">

                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400">
                        🔍
                    </span>

                </div>

                <!-- FILTERS -->
                <div class="flex gap-3">

                    <button
                        class="px-5 py-3 rounded-full bg-white border border-gray-300">
                        All
                    </button>

                    <button
                        class="px-5 py-3 rounded-full bg-indigo-500 text-white">
                        Pending
                    </button>

                    <button
                        class="px-5 py-3 rounded-full bg-white border border-gray-300">
                        Reviewed
                    </button>

                    <button
                        class="px-5 py-3 rounded-full bg-white border border-gray-300">
                        Rejected
                    </button>

                </div>

            </div>

            <!-- TABLE -->
            <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">

                <!-- TITLE -->
                <div class="px-8 py-6 border-b border-gray-100">

                    <h2 class="text-3xl font-bold text-black">
                        To Verify
                    </h2>

                </div>

                <!-- TABLE -->
                <div class="overflow-x-auto">

                    <table class="w-full">

                        <thead class="bg-gray-50 text-gray-500">

                            <tr>

                                <th class="px-8 py-5 text-left">
                                    Applicant Name
                                </th>

                                <th class="px-8 py-5 text-left">
                                    Reference No.
                                </th>

                                <th class="px-8 py-5 text-left">
                                    Contact
                                </th>

                                <th class="px-8 py-5 text-left">
                                    Upload Date
                                </th>

                                <th class="px-8 py-5 text-left">
                                    Status
                                </th>

                                <th class="px-8 py-5 text-left">
                                    Actions
                                </th>

                            </tr>

                        </thead>

                        <tbody>

                            @foreach ($documents as $doc)

                            <tr
                                ondblclick='openModal(@json($doc))'
                                class="border-t border-gray-100 hover:bg-indigo-50 transition cursor-pointer">

                                <!-- NAME -->
                                <td class="px-8 py-6 text-lg text-black">

                                    {{ $doc->applicant_name ?? 'N/A' }}

                                </td>

                                <!-- SERIAL -->
                                <td class="px-8 py-6 text-lg text-black">

                                    {{ $doc->serial_number }}

                                </td>

                                <!-- CONTACT -->
                                <td class="px-8 py-6 text-lg text-black">

                                    {{ $doc->contact_number ?? 'N/A' }}

                                </td>

                                <!-- DATE -->
                                <td class="px-8 py-6 text-lg text-black">

                                    {{ \Carbon\Carbon::parse($doc->created_at)->format('M d, Y') }}

                                </td>

                                <!-- STATUS -->
                                <td class="px-8 py-6">

                                    @php
                                    $status = strtolower($doc->status ?? 'pending');
                                    @endphp

                                    <span class="
                                        px-4 py-2 rounded-full text-sm font-semibold

                                        @if($status === 'pending')
                                            bg-indigo-500 text-white
                                        @elseif($status === 'approved')
                                            bg-green-500 text-white
                                        @elseif($status === 'rejected')
                                            bg-red-500 text-white
                                        @else
                                            bg-gray-200 text-black
                                        @endif
                                    ">

                                        {{ ucfirst($status) }}

                                    </span>

                                </td>

                                <!-- ACTION -->
                                <td class="px-8 py-6">

                                    <button
                                        class="text-indigo-500 font-semibold hover:text-indigo-700">

                                        View/Edit

                                    </button>

                                </td>

                            </tr>

                            @endforeach

                        </tbody>

                    </table>

                </div>

            </div>

        </main>

    </div>

    <!-- MODAL -->
    <div id="modal"
        class="fixed inset-0 bg-black/50 backdrop-blur-sm hidden items-center justify-center z-50 p-6">

        <div class="bg-white w-full max-w-6xl rounded-3xl shadow-2xl max-h-[95vh] overflow-hidden flex flex-col">

            <!-- HEADER -->
            <div class="flex justify-between items-center border-b border-gray-200 px-8 py-6">

                <div>

                    <h2 id="m_name"
                        class="text-3xl font-bold text-black"></h2>

                    <p id="m_serial"
                        class="text-gray-500 mt-1"></p>

                </div>

                <button onclick="closeModal()"
                    class="w-12 h-12 rounded-full bg-gray-100 hover:bg-red-100 hover:text-red-500 transition">

                    ✕

                </button>

            </div>

            <!-- CONTENT -->
            <div class="flex-1 overflow-y-auto p-8 bg-gray-50">

                <form id="reviewForm"
                    method="POST"
                    action="/unit/documents/review">

                    @csrf

                    <input type="hidden"
                        name="serial_number"
                        id="input_serial">

                    <input type="hidden"
                        name="decision"
                        id="decision_input">

                    <!-- CHECK ALL -->
                    <div class="bg-white border border-gray-200 rounded-2xl p-4 mb-6 flex items-center gap-3">

                        <input type="checkbox"
                            id="checkAll"
                            onclick="toggleAll(this)"
                            class="w-5 h-5">

                        <label for="checkAll"
                            class="font-semibold text-black">

                            Check All Documents

                        </label>

                    </div>

                    <!-- DOC BOX -->
                    <div id="doc_box"
                        class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                    </div>

                    <!-- LOCATION -->
                    <div class="mt-6">

                        <p id="coords"
                            class="text-indigo-500 font-medium"></p>

                    </div>

                    <!-- BUTTONS -->
                    <div class="flex flex-col md:flex-row gap-4 mt-8">

                        <button type="button"
                            onclick="submitDecision('approved')"
                            class="flex-1 bg-green-500 hover:bg-green-600 text-white py-4 rounded-2xl font-semibold text-lg">

                            ✔ ACCEPT

                        </button>

                        <button type="button"
                            onclick="submitDecision('rejected')"
                            class="flex-1 bg-red-500 hover:bg-red-600 text-white py-4 rounded-2xl font-semibold text-lg">

                            ❌ REJECT

                        </button>

                    </div>

                </form>

            </div>

        </div>

    </div>
    <script>
        let darkMode = false;

        function toggleMode() {

            darkMode = !darkMode;

            const body = document.getElementById('body');

            const modeBtn = document.getElementById('modeBtn');

            const modeText = document.getElementById('modeText');

            const modeIcon = document.getElementById('modeIcon');

            // =========================
            // DARK MODE
            // =========================
            if (darkMode) {

                // BODY
                body.classList.remove('bg-[#f5f5f7]');
                body.classList.add('bg-[#111827]');

                // SIDEBAR
                document.querySelectorAll('aside')
                    .forEach(el => {
                        el.classList.remove('bg-white', 'border-gray-200');
                        el.classList.add('bg-[#1f2937]', 'border-gray-700');
                    });

                // MAIN CARDS
                document.querySelectorAll('.bg-white')
                    .forEach(el => {
                        el.classList.remove('bg-white');
                        el.classList.add('bg-[#1f2937]');
                    });

                // TEXT
                document.querySelectorAll('.text-black')
                    .forEach(el => {
                        el.classList.remove('text-black');
                        el.classList.add('text-white');
                    });

                document.querySelectorAll('.text-gray-500')
                    .forEach(el => {
                        el.classList.remove('text-gray-500');
                        el.classList.add('text-gray-300');
                    });

                // TABLE
                document.querySelectorAll('thead')
                    .forEach(el => {
                        el.classList.remove('bg-gray-50');
                        el.classList.add('bg-[#111827]');
                    });

                // BUTTON
                modeBtn.classList.remove('bg-white');
                modeBtn.classList.add('bg-[#374151]');

                modeText.innerText = 'NIGHT MODE';
                modeIcon.innerText = '🌙';

            }

            // =========================
            // LIGHT MODE
            // =========================
            else {

                // BODY
                body.classList.remove('bg-[#111827]');
                body.classList.add('bg-[#f5f5f7]');

                // SIDEBAR
                document.querySelectorAll('aside')
                    .forEach(el => {
                        el.classList.add('bg-white', 'border-gray-200');
                        el.classList.remove('bg-[#1f2937]', 'border-gray-700');
                    });

                // MAIN CARDS
                document.querySelectorAll('.bg-[#1f2937]')
                    .forEach(el => {
                        el.classList.remove('bg-[#1f2937]');
                        el.classList.add('bg-white');
                    });

                // TEXT
                document.querySelectorAll('.text-white')
                    .forEach(el => {
                        el.classList.remove('text-white');
                        el.classList.add('text-black');
                    });

                document.querySelectorAll('.text-gray-300')
                    .forEach(el => {
                        el.classList.remove('text-gray-300');
                        el.classList.add('text-gray-500');
                    });

                // TABLE
                document.querySelectorAll('thead')
                    .forEach(el => {
                        el.classList.add('bg-gray-50');
                        el.classList.remove('bg-[#111827]');
                    });

                // BUTTON
                modeBtn.classList.add('bg-white');
                modeBtn.classList.remove('bg-[#374151]');

                modeText.innerText = 'DAY MODE';
                modeIcon.innerText = '☀';

            }
        }
    </script>
    <script>
        // Escape server data before inserting it with innerHTML.
        function escapeHtml(value) {
            return String(value ?? '').replace(/[&<>"']/g, c => ({
                '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
            })[c]);
        }
        function openModal(doc) {

            const modal = document.getElementById('modal');

            modal.classList.remove('hidden');
            modal.classList.add('flex');

            document.getElementById('m_name').innerText = doc.applicant_name ?? 'N/A';

            document.getElementById('m_serial').innerText =
                "Serial: " + doc.serial_number;

            document.getElementById('input_serial').value =
                doc.serial_number;

            const docs = [
                ['SOI', doc.soi],
                ['NBI Clearance', doc.nbi_clearance],
                ['Medical', doc.medical_clearance],
                ['PFT', doc.pft_clearance],
                ['2x2 Photo', doc.photo_2x2],
            ];

            let html = '';

            docs.forEach((d, i) => {

                let label = d[0];
                let file = d[1];

                let preview = `
                    <div class="text-yellow-500 text-sm">
                        No file uploaded
                    </div>
                `;

                if (file) {

                    let path = file
                        .replace(/^\/+/, '')
                        .replace(/^storage\//, '');

                    if (!path.startsWith('documents/')) {
                        path = 'documents/' + path;
                    }

                    let url = @json(\App\Support\Uploads::url('documents_toupload/')) + path;

                    let isImg = /\.(jpg|jpeg|png|gif|webp)$/i.test(path);
                    let isPdf = /\.pdf$/i.test(path);

                    if (isImg) {

                        preview = `
                            <img src="${escapeHtml(url)}"
                                class="w-full h-[500px] object-cover">
                        `;

                    } else if (isPdf) {

                        preview = `
                            <iframe src="${escapeHtml(url)}"
                                class="w-full h-[500px]"></iframe>
                        `;

                    } else {

                        preview = `
                            <a href="${escapeHtml(url)}"
                                target="_blank"
                                class="text-indigo-500">
                                Open File
                            </a>
                        `;
                    }
                }

                html += `
                    <div class="bg-white border border-gray-200 rounded-3xl p-5 shadow-sm">

                        <div class="flex items-center gap-3 mb-4">

                            <input type="checkbox"
                                class="doc-check w-5 h-5"
                                name="docs[${i}][checked]">

                            <h3 class="font-bold text-lg text-black">
                                ${label}
                            </h3>

                        </div>

                        <div class="overflow-hidden rounded-2xl border border-gray-100 bg-gray-50">
                            ${preview}
                        </div>

                        <div class="mt-4">

                            <textarea
                                name="docs[${i}][comment]"
                                placeholder="Write comment..."
                                rows="3"
                                class="w-full border border-gray-300 rounded-2xl p-4 outline-none focus:ring-2 focus:ring-indigo-300"></textarea>

                        </div>

                    </div>
                `;
            });

            document.getElementById('doc_box').innerHTML = html;

            if (doc.latitude && doc.longitude) {

                document.getElementById('coords').innerHTML =
                    `
                    📍
                    <a href="https://www.google.com/maps?q=${encodeURIComponent(doc.latitude)},${encodeURIComponent(doc.longitude)}"
                        target="_blank"
                        class="underline">

                        Open in Google Maps

                    </a>
                `;

            } else {

                document.getElementById('coords').innerText =
                    "No location available";
            }

            document.getElementById('checkAll').checked = false;
        }

        function closeModal() {

            const modal = document.getElementById('modal');

            modal.classList.remove('flex');
            modal.classList.add('hidden');
        }

        function toggleAll(source) {

            document.querySelectorAll('.doc-check')
                .forEach(cb => cb.checked = source.checked);
        }

        function submitDecision(status) {

            document.getElementById('decision_input').value = status;

            document.getElementById('reviewForm').submit();
        }

        window.onclick = function(e) {

            let modal = document.getElementById('modal');

            if (e.target === modal) {

                closeModal();
            }
        }
    </script>

</body>

</html>