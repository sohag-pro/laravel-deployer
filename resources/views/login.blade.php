@extends('layouts.app')

@section('content')
    <h1>Login</h1>
    <div class="login-form">
        <h4>Username</h4>
        <div class="username-input">
            <i class="fas fa-user"></i>
            <input type="text" placeholder="Type your username">
        </div>
        <h4>Password</h4>
        <div class="password-input">
            <i class="fas fa-lock"></i>
            <input type="text" placeholder="Type your password">
        </div>
        <p>Forgot password?</p>
    </div>
    <button class="login-btn">
        LOGIN
    </button>
    <div class="alternative-signup">
        <p>Not a member? <span>Sign-up</span></p>
    </div>
@endsection
