@extends('layouts.app')

@section('content')
    <div class="min-h-screen ">
        <div class="max-w-8xl mx-auto flex flex-col justify-center space-y-5 p-12 lg:flex-row lg:space-x-5 lg:space-y-0">
            <!-- Card 1 -->
            <div
                class="flex h-full w-full flex-col items-center justify-between space-y-5 rounded-md border border-gray-100 bg-gray-200 bg-opacity-40 bg-clip-padding py-8 px-5 backdrop-blur-sm backdrop-filter">
                <h2 class="pb-8 text-2xl font-medium text-gray-800">Deployment</h2>

                <a class="rounded-md bg-green-500 text-2xl px-6 py-3 font-semibold text-white" href="{{ route('deploy') }}">Deploy</a>
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
                                    <a class="rounded-md no-underline bg-gradient-to-r from-blue-500 to-green-400 hover:from-yellow-500 hover:to-pink-500 p-1 text-white" href="{{ route('restore', ['folder' => $folder['name']]) }}">
                                        Restore
                                    </a>
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
                                    <a class="rounded-md no-underline bg-gradient-to-r from-green-400 to-blue-500 hover:from-pink-500 hover:to-yellow-500 p-1 text-white" href="{{ route('restoreDb', ['db_file' => $db_file['name']]) }}">
                                        Restore
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
