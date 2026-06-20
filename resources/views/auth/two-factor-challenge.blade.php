@extends('layouts.app')

@section('content')
    <div class="min-h-screen flex items-center justify-center bg-gray-100 px-4">
        <div class="w-full max-w-md rounded-lg bg-white p-8 shadow">
            <h1 class="mb-1 text-center text-2xl font-semibold text-gray-800">Two-factor authentication</h1>
            <p class="mb-6 text-center text-sm text-gray-500">Enter the code from your authenticator app.</p>

            @if ($errors->any())
                <div class="mb-4 rounded-md bg-red-100 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('two-factor.verify') }}" class="space-y-4">
                @csrf

                <div>
                    <label for="code" class="mb-1 block text-sm font-medium text-gray-700">Authentication code</label>
                    <input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" autofocus
                        class="w-full rounded-md border border-gray-300 px-3 py-2 tracking-widest focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                </div>

                <details class="text-sm text-gray-600">
                    <summary class="cursor-pointer">Use a recovery code instead</summary>
                    <input name="recovery_code" type="text"
                        class="mt-2 w-full rounded-md border border-gray-300 px-3 py-2 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                        placeholder="xxxxx-xxxxx">
                </details>

                <button type="submit"
                    class="w-full rounded-md bg-indigo-600 py-2 font-semibold text-white transition hover:bg-indigo-700">
                    Verify
                </button>
            </form>
        </div>
    </div>
@endsection
