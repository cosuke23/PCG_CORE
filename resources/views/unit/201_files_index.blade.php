<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>201 Files</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-[#f5f5f7] min-h-screen overflow-hidden">

    <div class="flex h-screen">

        <!-- SIDEBAR -->
        <aside class="w-[250px] bg-white border-r flex flex-col justify-between">

            <div>

                <div class="flex justify-center py-8">
                    <img src="{{ asset('logo.png') }}" class="w-24 h-24">
                </div>

                <nav class="px-4 space-y-2">

                    <a href="#" class="flex gap-3 px-4 py-3 rounded-xl hover:bg-gray-100">
                        📊 Dashboard
                    </a>

                    <a href="{{ route('unit.201files') }}"
                        class="flex gap-3 px-4 py-3 rounded-xl bg-indigo-100 text-indigo-600 font-semibold">
                        📝 201 Files
                    </a>

                </nav>

            </div>

            <div class="p-4">
                <a href="/unit/logout" class="block bg-red-500 text-white text-center py-4 rounded-2xl">
                    Logout
                </a>
            </div>

        </aside>

        <!-- MAIN -->
        <main class="flex-1 overflow-y-auto p-10">

            <h1 class="text-4xl font-bold mb-6">201 Files</h1>

            <input id="tableSearch" class="w-full p-4 border rounded-xl mb-6" placeholder="Search personnel...">

            <div class="bg-white rounded-2xl border overflow-hidden">

                <table class="w-full">

                    <thead class="bg-gray-50 text-left">
                        <tr>
                            <th class="p-4">Name</th>
                            <th class="p-4">Rank</th>
                            <th class="p-4">Serial</th>
                            <th class="p-4">Class</th>
                            <th class="p-4">Action</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($personnel as $p)
                            <tr onclick="openModal('{{ $p->serial_number }}')"
                                class="border-t hover:bg-indigo-50 cursor-pointer">

                                <td class="p-4">
                                    {{ $p->last_name }}, {{ $p->first_name }} {{ $p->middle_name }}
                                </td>

                                <td class="p-4">{{ $p->rank }}</td>
                                <td class="p-4">{{ $p->serial_number }}</td>
                                <td class="p-4">{{ $p->class }}</td>

                                <td class="p-4 text-indigo-600 font-semibold">View</td>

                            </tr>
                        @endforeach
                    </tbody>

                </table>

            </div>

        </main>

    </div>

    <!-- MODAL -->
    <div id="modal" class="fixed inset-0 hidden items-center justify-center bg-black/50 p-6">

        <div class="bg-white w-full max-w-6xl rounded-3xl max-h-[90vh] overflow-hidden flex flex-col">

            <div class="flex justify-between p-6 border-b">

                <div>
                    <h2 id="personnel_name" class="text-2xl font-bold"></h2>
                    <p id="serial_number" class="text-gray-500"></p>
                </div>

                <button onclick="closeModal()">✕</button>

            </div>

            <div class="p-6 overflow-y-auto bg-gray-50">

                <div id="documents_grid" class="grid md:grid-cols-2 gap-6"></div>

            </div>

        </div>

    </div>

    <script>
        const BASE_URL = @json(\App\Support\Uploads::baseUrl());

        // SEARCH
        document.getElementById('tableSearch').addEventListener('keyup', function() {
            let value = this.value.toLowerCase();

            document.querySelectorAll('tbody tr').forEach(row => {
                row.style.display = row.innerText.toLowerCase().includes(value) ? '' : 'none';
            });
        });

        // OPEN MODAL
        function openModal(serial) {

            fetch(`/unit/201-files/${serial}`)
                .then(res => res.json())
                .then(data => {

                    document.getElementById('modal').classList.remove('hidden');
                    document.getElementById('modal').classList.add('flex');

                    document.getElementById('personnel_name').innerText =
                        `${data.personnel.last_name}, ${data.personnel.first_name}`;

                    document.getElementById('serial_number').innerText =
                        data.personnel.serial_number;

                    const docs = {
                        'SOI': data.documents.soi,
                        'Appointment': data.documents.appointment,
                        'Leave': data.documents.leave,
                        'Awards': data.documents.awards,
                        'Disciplinary': data.documents.disciplinary,
                        'Performance': data.documents.performance,
                        'Legal': data.documents.legal,
                        'Academic': data.documents.academic,
                        'Financial': data.documents.financial,
                        'Retirement': data.documents.retirement,
                        'Body Picture': data.documents.body_picture,
                    };

                    let html = '';

                    Object.entries(docs).forEach(([label, file]) => {

                        let preview = `<span class="text-yellow-500">No file uploaded</span>`;

                        if (file) {

                            let cleanPath = file.replace(/^\/+/, '');
                            let url = BASE_URL + cleanPath;

                            let isImg = /\.(jpg|jpeg|png|gif|webp)$/i.test(cleanPath);
                            let isPdf = /\.pdf$/i.test(cleanPath);

                            if (isImg) {
                                preview = `<img src="${url}" class="w-full max-h-[400px] object-contain">`;
                            } else if (isPdf) {
                                preview = `
                            <iframe 
                                src="${url}" 
                                class="w-full h-[500px] bg-white">
                            </iframe>`;
                            } else {
                                preview = `
                            <a href="${url}" target="_blank" class="text-indigo-600 font-semibold">
                                Open File
                            </a>`;
                            }
                        }

                        html += `
                    <div class="bg-white border rounded-2xl p-5">
                        <div class="font-bold mb-3">${label}</div>
                        <div class="bg-gray-50 rounded-xl p-3">
                            ${preview}
                        </div>
                    </div>
                `;
                    });

                    document.getElementById('documents_grid').innerHTML = html;

                });
        }

        function closeModal() {
            document.getElementById('modal').classList.add('hidden');
            document.getElementById('modal').classList.remove('flex');
        }
    </script>

</body>

</html>
