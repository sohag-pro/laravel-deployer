@extends('layouts.app')

@section('content')
    <div class="min-h-screen">
        <div class="flex items-center justify-between px-12 pt-8">
            <h1 class="text-2xl font-semibold text-gray-800">Laravel Deployer</h1>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="rounded-md bg-gray-700 px-4 py-2 text-sm text-white hover:bg-gray-800">
                    Log out
                </button>
            </form>
        </div>

        @if (session('success'))
            <div class="mx-12 mt-6 rounded-md bg-green-100 px-4 py-3 text-green-800">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="mx-12 mt-6 rounded-md bg-red-100 px-4 py-3 text-red-800">{{ session('error') }}</div>
        @endif

        <div class="max-w-8xl mx-auto flex flex-col justify-center space-y-5 p-12 lg:flex-row lg:space-x-5 lg:space-y-0">
            <!-- Card 1 -->
            <div
                class="flex h-full w-full flex-col items-center space-y-5 rounded-md border border-gray-100 bg-gray-200 bg-opacity-40 bg-clip-padding py-8 px-5 backdrop-blur-sm backdrop-filter">
                <h2 class="text-2xl font-medium text-gray-800">Deployment</h2>

                <div class="flex items-center gap-2 text-sm">
                    <span class="text-gray-600">Status:</span>
                    <span id="deploy-status-badge"
                        class="rounded-md px-2 py-1 font-semibold"
                        data-status="{{ $deploy['status'] }}">{{ ucfirst($deploy['status']) }}</span>
                </div>

                <form method="POST" action="{{ route('deploy') }}"
                    onsubmit="return confirm('Deploy the latest commit now?');">
                    @csrf
                    <button id="deploy-button" type="submit"
                        class="rounded-md bg-green-500 px-6 py-3 text-2xl font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50">
                        Deploy
                    </button>
                </form>

                <pre id="deploy-log"
                    class="hidden h-48 w-full overflow-auto rounded-md bg-gray-900 p-3 text-left text-xs text-green-200"></pre>
            </div>

            <!-- Card 2 -->
            <div
                class="flex h-full w-full flex-col items-center justify-between space-y-1 rounded-md border border-gray-100 bg-gray-200 bg-opacity-40 bg-clip-padding py-8 px-5 backdrop-blur-sm backdrop-filter">
                <h2 class="pb-5 text-2xl font-medium text-gray-800">Files</h2>

                <table class="table-auto">
                    <thead class="text-white">
                        <tr class="overflow-hidden rounded-t-md bg-gradient-to-r from-indigo-500 via-purple-500 to-pink-500 px-4 py-4 text-white">
                            <th class="w-6/8 px-4 py-4">Date</th>
                            <th class="w-3/8">Download</th>
                            <th class="w-3/12">Restore</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($folders as $folder)
                            <tr @if ($loop->index % 2 == 0) class="bg-white" @endif>
                                <td class="mx-2 inline-block py-2 px-4">
                                    <span
                                        @if ($folder['name'] == $live) class="py-2 px-4 inline-block rounded-md bg-green-500 text-sm text-white" @endif>
                                        {{ $folder['created_at'] }}
                                    </span>
                                </td>
                                <td class="text-center text-blue-500 px-4 py-2">
                                    <a class="rounded-md no-underline bg-gradient-to-r from-green-400 to-blue-500 hover:from-pink-500 hover:to-yellow-500 p-1 text-white" href="{{ route('download', ['folder' => $folder['name']]) }}">
                                        Download
                                    </a>
                                </td>
                                <td class="text-center text-purple-500 px-4 py-2">
                                    <form method="POST" action="{{ route('restore', ['folder' => $folder['name']]) }}"
                                        onsubmit="return confirm('Restore this version as live?');">
                                        @csrf
                                        <button type="submit" class="rounded-md bg-gradient-to-r from-blue-500 to-green-400 p-1 text-white hover:from-yellow-500 hover:to-pink-500">
                                            Restore
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach

                    </tbody>
                </table>
            </div>

            <!-- Card 3 -->
            <div
                class="flex h-full w-full flex-col items-center justify-between space-y-5 rounded-md border border-gray-100 bg-gray-200 bg-opacity-40 bg-clip-padding py-8 px-5 backdrop-blur-sm backdrop-filter">
                <h2 class="pb-5 text-2xl font-medium text-gray-800">Database</h2>
                <table class="table-auto">
                    <thead class="text-white">
                        <tr class="overflow-hidden rounded-t-md bg-gradient-to-r from-indigo-500 via-purple-500 to-pink-500 px-2 py-2 text-white">
                            <th class="w-6/8 px-4 py-4">Date</th>
                            <th class="w-3/8">Download</th>
                            <th class="w-3/12">Restore</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($db_files as $db_file)
                            <tr @if ($loop->index % 2 == 0) class="bg-white" @endif>
                                <td class="mx-2 inline-block px-4 py-2">
                                    {{ $db_file['created_at'] }}
                                </td>
                                <td class="text-center text-blue-500 px-4 py-2">
                                    <a class="rounded-md no-underline bg-gradient-to-r from-blue-500 to-green-400 hover:from-yellow-500 hover:to-pink-500 p-1 text-white" href="{{ route('downloadDb', ['db_file' => $db_file['name']]) }}">
                                        Download
                                    </a>
                                </td>
                                <td class="text-center text-purple-500 px-4 py-2">
                                    <form method="POST" action="{{ route('restoreDb', ['db_file' => $db_file['name']]) }}"
                                        onsubmit="return confirm('Restore this database dump? Current data will be overwritten.');">
                                        @csrf
                                        <button type="submit" class="rounded-md bg-gradient-to-r from-green-400 to-blue-500 p-1 text-white hover:from-pink-500 hover:to-yellow-500">
                                            Restore
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        (function () {
            const statusUrl = @json(route('deploy.status'));
            const badge = document.getElementById('deploy-status-badge');
            const log = document.getElementById('deploy-log');
            const button = document.getElementById('deploy-button');

            const colors = {
                running: 'bg-yellow-200 text-yellow-900',
                success: 'bg-green-200 text-green-900',
                failed: 'bg-red-200 text-red-900',
                idle: 'bg-gray-200 text-gray-700',
            };

            function render(data) {
                badge.textContent = data.status.charAt(0).toUpperCase() + data.status.slice(1);
                badge.className = 'rounded-md px-2 py-1 font-semibold ' + (colors[data.status] || colors.idle);
                button.disabled = data.running;

                if (data.log && data.log.trim().length) {
                    log.classList.remove('hidden');
                    const atBottom = log.scrollHeight - log.clientHeight <= log.scrollTop + 4;
                    log.textContent = data.log;
                    if (atBottom) log.scrollTop = log.scrollHeight;
                } else {
                    log.classList.add('hidden');
                }
            }

            let timer = null;
            async function poll() {
                try {
                    const res = await fetch(statusUrl, { headers: { 'Accept': 'application/json' } });
                    const data = await res.json();
                    render(data);
                    if (!data.running && timer) { clearInterval(timer); timer = null; }
                } catch (e) { /* keep polling */ }
            }

            const initial = badge.dataset.status;
            poll();
            if (initial === 'running') {
                timer = setInterval(poll, 2000);
            }

            // When a deploy is kicked off, the page reloads (form POST) with the
            // status already "running", so polling starts on the fresh load.
        })();
    </script>
@endsection
