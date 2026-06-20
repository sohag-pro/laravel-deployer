@extends('layouts.app')

@section('content')
    <div class="min-h-screen flex items-center justify-center bg-gray-100 px-4">
        <div class="w-full max-w-md rounded-lg bg-white p-8 shadow">
            <h1 class="mb-1 text-center text-2xl font-semibold text-gray-800">Laravel Deployer</h1>
            <p class="mb-6 text-center text-sm text-gray-500">Sign in to continue</p>

            @if ($errors->any())
                <div class="mb-4 rounded-md bg-red-100 px-4 py-3 text-sm text-red-700">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('login.attempt') }}" class="space-y-4">
                @csrf

                <div>
                    <label for="email" class="mb-1 block text-sm font-medium text-gray-700">Email</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus
                        class="w-full rounded-md border border-gray-300 px-3 py-2 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                </div>

                <div>
                    <label for="password" class="mb-1 block text-sm font-medium text-gray-700">Password</label>
                    <input id="password" name="password" type="password" required
                        class="w-full rounded-md border border-gray-300 px-3 py-2 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                </div>

                <label class="flex items-center text-sm text-gray-600">
                    <input name="remember" type="checkbox" class="mr-2 rounded border-gray-300">
                    Remember me
                </label>

                <button type="submit"
                    class="w-full rounded-md bg-indigo-600 py-2 font-semibold text-white transition hover:bg-indigo-700">
                    Sign in
                </button>
            </form>
        </div>
    </div>
@endsection
