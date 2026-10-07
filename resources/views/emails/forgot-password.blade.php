@extends('emails.layout')

@section('title', 'Reset Password — Happy Tea')

@section('content')
    <div class="welcome">Hello, {{ $user->name }}!</div>
    <p class="intro">
        You are receiving this email because we received a password reset request for your account. Please click the button below to set a new password.
    </p>

    <center>
        <a href="{{ $resetUrl }}" class="button">
            Reset Password
        </a>
    </center>

    <div class="warning-box" style="margin-top: 30px;">
        ⚠️ This password reset link will expire in 60 minutes.<br>
        If you did not request a password reset, no further action is required.
    </div>

    <div class="break-link">
        If you're having trouble clicking the "Reset Password" button, copy and paste the URL below into your web browser:<br>
        <a href="{{ $resetUrl }}" style="color: #F88027;">{{ $resetUrl }}</a>
    </div>
@endsection
