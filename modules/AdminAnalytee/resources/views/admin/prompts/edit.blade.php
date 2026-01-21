@extends('layouts.app')

@php
    $publishedPrompt = \Modules\AdminAnalytee\Models\AnalyteePrompt::query()
        ->where('key', $prompt->key)
        ->where('is_published', 1)
        ->where('id', '!=', $prompt->id)
        ->orderByDesc('version')
        ->first();

    $publishedText = (string) ($publishedPrompt->prompt ?? '');
    $currentText = (string) ($prompt->prompt ?? '');

    $normalizeNewlines = function (string $text): string {
        return str_replace("\r", '', str_replace("\r\n", "\n", $text));
    };

    $publishedLines = explode("\n", $normalizeNewlines($publishedText));
    $currentLines = explode("\n", $normalizeNewlines($currentText));

    $diffParts = [];

    if ($publishedPrompt) {
        $i = 0;
        $j = 0;
        $publishedCount = count($publishedLines);
        $currentCount = count($currentLines);

        while ($i < $publishedCount && $j < $currentCount) {
            $fromLine = $publishedLines[$i];
            $toLine = $currentLines[$j];

            if ($fromLine === $toLine) {
                $diffParts[] = ['type' => 'same', 'line' => $fromLine];
                $i++;
                $j++;
                continue;
            }

            $remainingFrom = array_slice($publishedLines, $i + 1);
            $remainingTo = array_slice($currentLines, $j + 1);

            if (! in_array($toLine, $remainingFrom, true)) {
                $diffParts[] = ['type' => 'add', 'line' => $toLine];
                $j++;
                continue;
            }

            if (! in_array($fromLine, $remainingTo, true)) {
                $diffParts[] = ['type' => 'del', 'line' => $fromLine];
                $i++;
                continue;
            }

            $diffParts[] = ['type' => 'del', 'line' => $fromLine];
            $diffParts[] = ['type' => 'add', 'line' => $toLine];
            $i++;
            $j++;
        }

        while ($i < $publishedCount) {
            $diffParts[] = ['type' => 'del', 'line' => $publishedLines[$i]];
            $i++;
        }

        while ($j < $currentCount) {
            $diffParts[] = ['type' => 'add', 'line' => $currentLines[$j]];
            $j++;
        }
    }
@endphp

@section('sub_header')
    <x-sub-header
        title="{{ __('Editar prompt') }}"
        description="{{ $prompt->title }}"
    />
@endsection

@section('content')
    <div class="container max-w-1000 pb-5">
        <div class="card shadow-none border-gray-300">
            <div class="card-header d-flex align-items-center justify-content-between">
                <div>
                    <div class="fw-6 text-gray-900">{{ $prompt->title }}</div>
                    <div class="fs-12 text-gray-600">{{ $prompt->key }}</div>
                </div>
                <div class="d-flex align-items-center gap-8">
                    @if (! $prompt->is_published)
                        <form method="post" action="{{ route('admin.analytee.prompts.publish', ['prompt' => $prompt->id]) }}">
                            @csrf
                            @method('PUT')
                            <button class="btn btn-primary btn-sm" type="submit">
                                <i class="fa-solid fa-bullhorn me-1"></i>
                                {{ __('Publicar') }}
                            </button>
                        </form>
                    @endif
                    @if ($publishedPrompt)
                        <button class="btn btn-light btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#prompt-diff" aria-expanded="false" aria-controls="prompt-diff">
                            <i class="fa-solid fa-code-compare me-1"></i>
                            {{ __('Ver diferencias') }}
                        </button>
                    @endif
                    <a class="btn btn-light btn-sm" href="{{ route('admin.analytee.prompts.index') }}">
                        <i class="fa-solid fa-arrow-left me-1"></i>
                        {{ __('Volver') }}
                    </a>
                </div>
            </div>
            <div class="card-body">
                <form method="post" action="{{ route('admin.analytee.prompts.update', ['prompt' => $prompt->id]) }}">
                    @csrf
                    @method('PUT')

                    <div class="mb-4">
                        <label class="form-label fw-6">{{ __('Título') }}</label>
                        <input class="form-control" name="title" value="{{ old('title', $prompt->title) }}"/>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-6">{{ __('Descripción') }}</label>
                        <textarea class="form-control" name="description" rows="3">{{ old('description', $prompt->description) }}</textarea>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-6">{{ __('Prompt') }}</label>
                        <textarea class="form-control" name="prompt" rows="14">{{ old('prompt', $prompt->prompt) }}</textarea>
                        <div class="fs-12 text-gray-600 mt-2">
                            {{ __('Variables disponibles:') }} {{ '{' . '{review_text}' . '}' }}, {{ '{' . '{business_name}' . '}' }}, {{ '{' . '{language}' . '}' }}
                        </div>
                    </div>

                    @if ($publishedPrompt)
                        <div class="collapse mb-4" id="prompt-diff">
                            <div class="border border-gray-200 b-r-6 overflow-hidden">
                                <div class="border-bottom bg-gray-100 px-3 py-2 fs-12 text-gray-700 d-flex justify-content-between">
                                    <div>
                                        {{ __('Publicado') }} v{{ (int) $publishedPrompt->version }} → {{ __('Esta versión') }} v{{ (int) $prompt->version }}
                                    </div>
                                    <div class="text-gray-600">{{ $prompt->key }}</div>
                                </div>
                                <div class="p-3">
                                    @foreach ($diffParts as $part)
                                        <div class="font-monospace fs-12 px-2 py-1 {{ $part['type'] === 'add' ? 'bg-success-100' : ($part['type'] === 'del' ? 'bg-danger-100' : '') }}" style="white-space: pre;">
                                            {{ $part['type'] === 'add' ? '+ ' : ($part['type'] === 'del' ? '- ' : '  ') }}{{ $part['line'] }}
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endif

                    <div class="mb-4">
                        <label class="d-flex align-items-center gap-8 flex-nowrap">
                            <input class="form-check-input m-0" type="checkbox" name="is_active" value="1" {{ old('is_active', $prompt->is_active) ? 'checked' : '' }}/>
                            <span class="fs-14 text-gray-700 text-nowrap">{{ __('Activo') }}</span>
                        </label>
                    </div>

                    <div class="d-flex align-items-center justify-content-end gap-8">
                        <button class="btn btn-primary" type="submit">
                            <i class="fa-solid fa-floppy-disk me-2"></i>
                            {{ __('Guardar') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
