<?php

namespace Modules\AdminAnalytee\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Modules\AdminAnalytee\Models\AnalyteePrompt;

class AdminAnalyteePromptController extends Controller
{
    public function index()
    {
        $prompts = AnalyteePrompt::query()
            ->orderBy('key')
            ->orderByDesc('version')
            ->get();

        return view('adminanalytee::admin.prompts.index', [
            'promptGroups' => $prompts->groupBy('key'),
        ]);
    }

    public function edit(int $prompt)
    {
        $promptModel = AnalyteePrompt::query()->findOrFail($prompt);

        return view('adminanalytee::admin.prompts.edit', [
            'prompt' => $promptModel,
        ]);
    }

    public function update(Request $request, int $prompt)
    {
        $promptModel = AnalyteePrompt::query()->findOrFail($prompt);

        $data = $request->validate([
            'title' => ['required', 'string'],
            'description' => ['nullable', 'string'],
            'prompt' => ['required', 'string'],
        ]);

        $newPromptId = DB::transaction(function () use ($promptModel, $data): int {
            $latestVersion = (int) AnalyteePrompt::query()
                ->where('key', $promptModel->key)
                ->max('version');

            $newPrompt = AnalyteePrompt::query()->create([
                'key' => $promptModel->key,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'prompt' => $data['prompt'],
                'parent_id' => $promptModel->id,
                'version' => $latestVersion + 1,
                'is_published' => 0,
                'is_active' => 1,
            ]);

            return (int) $newPrompt->id;
        });

        return redirect()
            ->route('admin.analytee.prompts.edit', ['prompt' => $newPromptId])
            ->with('success', __('Guardado correctamente'));
    }

    public function publish(int $prompt)
    {
        $promptModel = AnalyteePrompt::query()->findOrFail($prompt);

        DB::transaction(function () use ($promptModel): void {
            AnalyteePrompt::query()
                ->where('key', $promptModel->key)
                ->update(['is_published' => 0]);

            AnalyteePrompt::query()
                ->where('id', $promptModel->id)
                ->update([
                    'is_published' => 1,
                    'is_active' => 1,
                ]);
        });

        return redirect()
            ->route('admin.analytee.prompts.edit', ['prompt' => $promptModel->id])
            ->with('success', __('Publicado correctamente'));
    }

    public function destroy(int $prompt)
    {
        $promptModel = AnalyteePrompt::query()->findOrFail($prompt);

        if ((int) $promptModel->is_published === 1) {
            return redirect()
                ->back()
                ->with('error', __('No se puede eliminar una versión publicada'));
        }

        $versionsCount = (int) AnalyteePrompt::query()
            ->where('key', $promptModel->key)
            ->count();

        if ($versionsCount <= 1) {
            return redirect()
                ->back()
                ->with('error', __('No se puede eliminar la única versión de este prompt'));
        }

        DB::transaction(function () use ($promptModel): void {
            $promptModel->delete();
        });

        return redirect()
            ->route('admin.analytee.prompts.index')
            ->with('success', __('Versión eliminada correctamente'));
    }
}
