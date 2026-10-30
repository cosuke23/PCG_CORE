@extends('layouts.app')

@section('title', 'Register')

@section('content')
<script src="{{ asset('vendor/sweetalert2/sweetalert2-11.22.0.all.min.js') }}"></script>

<div class="relative min-h-screen flex items-center justify-center bg-gradient-to-b from-blue-900 via-blue-800 to-blue-700 overflow-hidden">
    <div class="absolute inset-0 bg-black bg-opacity-50"></div>

    <div class="relative bg-white/10 backdrop-blur-md rounded-3xl shadow-2xl p-10 w-full max-w-3xl text-white z-10">
        <h2 class="text-3xl font-bold text-center mb-6">Create an Account</h2>
        <p class="text-center mb-8">
        </p>

        <form action="{{ route('register.submit') }}" method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-6" enctype="multipart/form-data" id="registerForm">
            @csrf





            <!-- Upload ID -->
            <div class="md:col-span-2">
                <label class="block text-sm font-medium mb-2">Upload ID (PDF only)</label>
                <input type="file" name="id_file" accept="application/pdf" id="idUpload" required
                    class="w-full px-3 py-2 rounded-lg bg-white/20 border border-white/30 text-white focus:outline-none focus:ring-2 focus:ring-blue-400">
            </div>

            <!-- Camera Verification -->
            <div class="md:col-span-2">
                <label class="block text-sm font-medium mb-2">Verify with Camera (Selfie with ID)</label>
                <video id="video" autoplay playsinline class="w-full rounded-lg mb-2 border border-white/30"></video>
                <button type="button" id="captureBtn"
                    class="w-full bg-green-600 hover:bg-green-700 py-2 rounded-lg font-semibold mb-2">
                    Capture Selfie
                </button>
                <canvas id="canvas" class="hidden"></canvas>
                <p id="cameraStatus" class="text-gray-200 text-sm"></p>
                <input type="hidden" name="selfie_image" id="selfieImage" required>
            </div>

            <!-- Submit Button -->
            <div class="md:col-span-2 text-center pt-4">
                <button type="submit"
                    class="w-full bg-blue-600 hover:bg-blue-700 transition duration-300 font-semibold py-2 rounded-lg shadow-lg">
                    Register
                </button>
            </div>
        </form>

        <p class="text-center text-sm mt-6 text-gray-200">
            Already have an account?
            <a href="{{ route('login') }}" class="text-orange-400 hover:text-orange-500 font-semibold">
                Login here
            </a>
        </p>
    </div>
</div>

<script>
    // SweetAlert on submit
    document.getElementById('registerForm').addEventListener('submit', function() {
        Swal.fire({
            title: 'Registering...',
            text: 'Please wait while we create your account.',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });
    });


    // ================== Camera Capture ==================
    const video = document.getElementById('video');
    const canvas = document.getElementById('canvas');
    const captureBtn = document.getElementById('captureBtn');
    const selfieInput = document.getElementById('selfieImage');
    const cameraStatus = document.getElementById('cameraStatus');

    navigator.mediaDevices.getUserMedia({ video: true })
        .then(stream => video.srcObject = stream)
        .catch(err => cameraStatus.textContent = "Cannot access camera: " + err);

    captureBtn.addEventListener('click', () => {
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        const ctx = canvas.getContext('2d');
        ctx.drawImage(video, 0, 0);
        const dataUrl = canvas.toDataURL('image/png');
        selfieInput.value = dataUrl;
        cameraStatus.textContent = "Selfie captured successfully!";
        Swal.fire('Selfie Captured', 'Your selfie with the ID has been captured.', 'success');
    });
</script>
@endsection
