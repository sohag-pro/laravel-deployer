@extends('layouts.app')

@section('content')
    <div class="min-h-screen bg-gray-100 px-4 py-12">
        <div class="mx-auto w-full max-w-xl rounded-lg bg-white p-8 shadow">
            <div class="mb-6 flex items-center justify-between">
                <h1 class="text-2xl font-semibold text-gray-800">Two-factor authentication</h1>
                <a href="{{ url('/') }}" class="text-sm text-indigo-600 hover:underline">&larr; Dashboard</a>
            </div>

            @if (session('success'))
                <div class="mb-4 rounded-md bg-green-100 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>
            @endif
            @if ($errors->any())
                <div class="mb-4 rounded-md bg-red-100 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
            @endif

            @if ($recoveryCodes)
                <div class="mb-6 rounded-md border border-yellow-300 bg-yellow-50 p-4">
                    <p class="mb-2 text-sm font-medium text-yellow-800">Recovery codes — store these somewhere safe. Each can be used once.</p>
                    <div class="grid grid-cols-2 gap-1 font-mono text-sm text-gray-800">
                        @foreach ($recoveryCodes as $code)
                            <span>{{ $code }}</span>
                        @endforeach
                    </div>
                </div>
            @endif

            @if ($user->hasTwoFactorEnabled())
                <p class="mb-4 rounded-md bg-green-50 px-4 py-3 text-sm text-green-800">
                    Two-factor authentication is <strong>enabled</strong> for your account.
                </p>
                <form method="POST" action="{{ route('two-factor.disable') }}"
                    onsubmit="return confirm('Disable two-factor authentication?');">
                    @csrf
                    <button type="submit" class="rounded-md bg-red-600 px-4 py-2 font-semibold text-white hover:bg-red-700">
                        Disable two-factor
                    </button>
                </form>
            @elseif ($provisioningUri)
                <p class="mb-4 text-sm text-gray-600">Scan this QR code with your authenticator app, then enter a code to confirm.</p>

                <div id="qr" class="mb-4 flex justify-center"></div>
                <p class="mb-4 break-all text-center text-xs text-gray-500">
                    Or enter this secret manually: <span class="font-mono">{{ $user->two_factor_secret }}</span>
                </p>

                <form method="POST" action="{{ route('two-factor.confirm') }}" class="flex items-center gap-2">
                    @csrf
                    <input name="code" type="text" inputmode="numeric" placeholder="123456" required
                        class="flex-1 rounded-md border border-gray-300 px-3 py-2 tracking-widest focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                    <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 font-semibold text-white hover:bg-indigo-700">
                        Confirm
                    </button>
                </form>

                <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
                <script>
                    new QRCode(document.getElementById('qr'), {
                        text: @json($provisioningUri),
                        width: 180,
                        height: 180,
                    });
                </script>
            @else
                <p class="mb-4 text-sm text-gray-600">
                    Add a second step to logins using an authenticator app (e.g. Google Authenticator, 1Password, Authy).
                </p>
                <form method="POST" action="{{ route('two-factor.enable') }}">
                    @csrf
                    <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 font-semibold text-white hover:bg-indigo-700">
                        Enable two-factor
                    </button>
                </form>
            @endif
        </div>
    </div>
@endsection
