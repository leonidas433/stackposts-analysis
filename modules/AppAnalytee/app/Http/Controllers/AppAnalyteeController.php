<?php

namespace Modules\AppAnalytee\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\AppAnalytee\Services\AnalyteeService;
use Modules\AppAnalytee\Services\PlacesReviewsService;
use Modules\AppChannels\Models\Accounts;

class AppAnalyteeController extends Controller
{
    private function extractPlaceIdFromUrl($url): string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return '';
        }

        $parts = parse_url($url);
        if (is_array($parts) && isset($parts['query']) && is_string($parts['query'])) {
            $qs = [];
            parse_str($parts['query'], $qs);
            foreach (['place_id', 'query_place_id', 'placeId', 'placeid'] as $key) {
                $candidate = trim((string) ($qs[$key] ?? ''));
                if ($candidate !== '') {
                    return $candidate;
                }
            }
        }

        if (preg_match('/(?:\?|&)(?:place_id|query_place_id)=([^&]+)/i', $url, $m)) {
            return trim(urldecode((string) ($m[1] ?? '')));
        }

        return '';
    }

    private function extractCidFromUrl($url): string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return '';
        }

        $parts = parse_url($url);
        if (is_array($parts) && isset($parts['query']) && is_string($parts['query'])) {
            $qs = [];
            parse_str($parts['query'], $qs);
            $cid = trim((string) ($qs['cid'] ?? ''));
            if ($cid !== '') {
                return $cid;
            }
        }

        if (preg_match('/(?:\\?|&)cid=([^&]+)/i', $url, $m)) {
            return trim(urldecode((string) ($m[1] ?? '')));
        }

        return '';
    }

    public function index(Request $request)
    {
        \Access::check('appanalytee', true, true);

        $teamId = (int) $request->team_id;
        $profiles = app(AnalyteeService::class)->getConnectedProfiles($teamId);

        $missingPlaceIdCount = 0;
        $missingPidCount = 0;
        foreach ($profiles as $p) {
            $pid = trim((string) ($p->pid ?? ''));
            if ($pid === '') {
                $missingPidCount++;
            }

            $data = is_array($p->data ?? null) ? $p->data : (json_decode((string) ($p->data ?? ''), true) ?: []);
            $placeId = trim((string) ($data['place_id'] ?? ''));
            if ($placeId === '') {
                $missingPlaceIdCount++;
            }
        }

        return view('appanalytee::index', [
            'team' => $request->team,
            'user' => auth()->user(),
            'metrics' => [],
            'hasData' => $profiles->isNotEmpty(),
            'profiles' => $profiles,
            'missingPlaceIdCount' => $missingPlaceIdCount,
            'missingPidCount' => $missingPidCount,
        ]);
    }

    public function link(Request $request, int $profile_id)
    {
        \Access::check('appanalytee', true, true);

        $teamId = (int) $request->team_id;
        $profileId = (int) $profile_id;

        $profile = Accounts::query()
            ->byTeam($teamId)
            ->where('id', $profileId)
            ->where('social_network', 'google_business_profile')
            ->where('category', 'location')
            ->first(['id', 'team_id', 'name', 'url', 'data']);

        if (! $profile) {
            return redirect()->route('app.analytee.index')->with('error', 'Negocio no disponible');
        }

        $data = is_array($profile->data ?? null) ? $profile->data : (json_decode((string) ($profile->data ?? ''), true) ?: []);
        $placeId = trim((string) ($data['place_id'] ?? ''));

        if ($placeId === '') {
            $placeId = $this->extractPlaceIdFromUrl($profile->url ?? '');
            if ($placeId !== '' && str_starts_with($placeId, 'ChIJ')) {
                $data['place_id'] = $placeId;
                Accounts::where('id', $profile->id)->update([
                    'data' => json_encode($data, JSON_UNESCAPED_UNICODE),
                ]);
            }
        }

        if ($placeId === '' && $this->extractCidFromUrl($profile->url ?? '') !== '') {
            $resolved = app(PlacesReviewsService::class)->resolvePlaceIdFromCidUrl((string) ($profile->url ?? ''), (string) ($profile->name ?? ''));
            if (($resolved['status'] ?? 0) === 1) {
                $candidate = trim((string) ($resolved['place_id'] ?? ''));
                if ($candidate !== '' && str_starts_with($candidate, 'ChIJ')) {
                    $placeId = $candidate;
                    $data['place_id'] = $placeId;
                    Accounts::where('id', $profile->id)->update([
                        'data' => json_encode($data, JSON_UNESCAPED_UNICODE),
                    ]);
                }
            }
        }

        if ($placeId === '') {
            return redirect()->route('app.analytee.index')->with('error', 'place_id no disponible');
        }

        $accountId = app(AnalyteeService::class)->ensureAnalyteeAccountIdForPlace(
            $teamId,
            $placeId,
            (string) ($profile->url ?? '')
        );

        if (! $accountId) {
            return redirect()->route('app.analytee.index')->with('error', 'No se pudo crear la cuenta Analytee');
        }

        return redirect()->route('app.analytee.reviews.index', ['account_id' => $accountId])->with('success', 'Cuenta Analytee vinculada. Ejecuta sincronización y análisis.');
    }
}
