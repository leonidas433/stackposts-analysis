<?php

namespace App\Http\Controllers\AppAnalytee;

use App\Http\Controllers\Controller;
use App\Services\Analytee\PromptService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\AppChannels\Models\Accounts;

class ReviewAiPreviewController extends Controller
{
    public function preview(Request $request, int $review_id, PromptService $promptService)
    {
        \Access::check('appanalytee', true, true);

        $teamId = (int) ($request->team_id ?? 0);
        if ($teamId <= 0) {
            return response()->json(['status' => 0, 'message' => 'Permiso denegado'], 403);
        }

        $review = DB::table('analytee_reviews')
            ->where('id', $review_id)
            ->where('team_id', $teamId)
            ->first();

        if (! $review) {
            return response()->json(['status' => 0, 'message' => 'Reseña no encontrada'], 404);
        }

        $account = Accounts::query()
            ->byTeam($teamId)
            ->where('id', (int) ($review->account_id ?? 0))
            ->first();

        $language = trim((string) ($review->language ?? ''));
        if ($language === '') {
            $language = 'es';
        }

        try {
            $finalPrompt = $promptService->build('review_reply_default', [
                'review_text' => (string) ($review->text ?? ''),
                'business_name' => (string) ($account->name ?? ''),
                'language' => $language,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['status' => 0, 'message' => $e->getMessage()], 404);
        } catch (\Throwable) {
            return response()->json(['status' => 0, 'message' => 'Prompt no disponible'], 500);
        }

        try {
            $messages = [
                [
                    'role' => 'system',
                    'content' => 'Devuelve únicamente el texto final de la respuesta, sin comillas ni formato extra.',
                ],
                [
                    'role' => 'user',
                    'content' => $finalPrompt,
                ],
            ];

            $ai = \AI::process($messages, 'text', [
                'temperature' => 0.5,
                'max_tokens' => 300,
                'maxResult' => 1,
            ], $teamId);
        } catch (\Throwable $e) {
            return response()->json(['status' => 0, 'message' => $e->getMessage() ?: 'Fallo IA'], 500);
        }

        $suggestion = trim((string) (($ai['data'][0] ?? '') ?: ''));
        if ($suggestion === '' || ! empty($ai['error'])) {
            return response()->json(['status' => 0, 'message' => 'Fallo IA'], 500);
        }

        return response()->json([
            'status' => 1,
            'suggestion' => $suggestion,
        ]);
    }
}
