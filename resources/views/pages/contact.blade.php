@extends('layouts.app')

@section('title', 'Contact Us')

@section('content')
<div class="py-5">
    <h2 class="text-center mb-4">Contact Us</h2>

    <div class="row justify-content-center">
        <div class="col-md-6">
            <form>
                <div class="mb-3">
                    <label class="form-label">Full Name</label>
                    <input type="text" class="form-control" placeholder="Enter your name">
                </div>

                <div class="mb-3">
                    <label class="form-label">Email Address</label>
                    <input type="email" class="form-control" placeholder="Enter your email">
                </div>

                <div class="mb-3">
                    <label class="form-label">Message</label>
                    <textarea class="form-control" rows="4" placeholder="Your message here..."></textarea>
                </div>

                <div class="text-center">
                    <button class="btn btn-primary px-4">Send Message</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
