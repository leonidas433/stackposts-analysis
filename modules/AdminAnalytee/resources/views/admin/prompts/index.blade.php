@extends('layouts.app')

@section('sub_header')
    <x-sub-header
        title="{{ __('Analytee · Prompts') }}"
        description="{{ __('Edita los prompts usados por IA en Analytee') }}"
        :count="(int) ($promptGroups?->count() ?? 0)"
    />
@endsection

@section('content')
    <div class="container max-w-1000 pb-5">
        <div class="card shadow-none border-gray-300">
            <div class="card-header d-flex align-items-center justify-content-between">
                <div class="fw-6">{{ __('Prompts') }}</div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('Key') }}</th>
                                <th>{{ __('Título') }}</th>
                                <th>{{ __('Versiones') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($promptGroups as $key => $versions)
                                @php
                                    $sortedVersions = $versions->sortByDesc('version')->values();
                                    $latestVersion = $sortedVersions->first();
                                    $hasMoreThanOne = $sortedVersions->count() > 1;
                                @endphp
                                <tr>
                                    <td class="text-gray-700 fs-14">{{ $key }}</td>
                                    <td class="text-gray-900 fw-6">
                                        {{ $latestVersion?->title ?? '-' }}
                                    </td>
                                    <td>
                                        <div class="d-flex flex-column gap-8 py-1">
                                            @foreach ($sortedVersions as $prompt)
                                                <div class="d-flex flex-wrap align-items-center justify-content-between gap-8 border border-gray-200 b-r-6 px-2 py-2">
                                                    <div class="d-flex flex-wrap align-items-center gap-6">
                                                        <span class="text-gray-900 fw-6">v{{ (int) $prompt->version }}</span>
                                                        <span class="badge {{ $prompt->is_active ? 'bg-success' : 'bg-gray-200 text-gray-700' }}">
                                                            {{ $prompt->is_active ? __('Activa') : __('Inactiva') }}
                                                        </span>
                                                        @if ($prompt->is_published)
                                                            <span class="badge bg-primary">{{ __('Publicada') }}</span>
                                                        @endif
                                                    </div>
                                                    <div class="d-flex flex-wrap align-items-center justify-content-end gap-6">
                                                        @if (! $prompt->is_published)
                                                            <form class="d-inline" method="post" action="{{ route('admin.analytee.prompts.publish', ['prompt' => $prompt->id]) }}">
                                                                @csrf
                                                                @method('PUT')
                                                                <button class="btn btn-primary btn-sm" type="submit">
                                                                    <i class="fa-solid fa-bullhorn me-1"></i>
                                                                    {{ __('Publicar') }}
                                                                </button>
                                                            </form>
                                                        @endif
                                                        <a class="btn btn-light btn-sm" href="{{ route('admin.analytee.prompts.edit', ['prompt' => $prompt->id]) }}">
                                                            <i class="fa-solid fa-pen-to-square me-1"></i>
                                                            {{ __('Editar') }}
                                                        </a>
                                                        @if (! $prompt->is_published && $hasMoreThanOne)
                                                            <form
                                                                class="d-inline"
                                                                method="post"
                                                                action="{{ route('admin.analytee.prompts.destroy', ['prompt' => $prompt->id]) }}"
                                                                onsubmit="return confirm('{{ __('¿Seguro que deseas eliminar esta versión?') }}')"
                                                            >
                                                                @csrf
                                                                @method('DELETE')
                                                                <button class="btn btn-light btn-sm text-danger" type="submit">
                                                                    <i class="fa-solid fa-trash me-1"></i>
                                                                    {{ __('Eliminar') }}
                                                                </button>
                                                            </form>
                                                        @endif
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center text-gray-600 py-4">{{ __('Sin prompts') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
