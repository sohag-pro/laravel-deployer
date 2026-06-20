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
                class="flex h-full w-full flex-col items-center justify-between space-y-5 rounded-md border border-gray-100 bg-gray-200 bg-opacity-40 bg-clip-padding py-8 px-5 backdrop-blur-sm backdrop-filter">
                <h2 class="pb-8 text-2xl font-medium text-gray-800">Deployment</h2>

                <form method="POST" action="{{ route('deploy') }}"
                    onsubmit="return confirm('Deploy the latest commit now?');">
                    @csrf
                    <button type="submit" class="rounded-md bg-green-500 px-6 py-3 text-2xl font-semibold text-white">
                        Deploy
                    </button>
                </form>
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
@endsection
