<?php

namespace App\Domains\Ambassador\Presentation\Http\Controllers;

use App\Domains\Ambassador\Infrastructure\Persistence\Models\PendingReferralClick;
use App\Domains\Ambassador\Infrastructure\Persistence\Models\Referral;
use App\Domains\Ambassador\Infrastructure\Persistence\Models\AmbassadorReferralUsage;
use App\Domains\Ambassador\Domain\Events\ReferralUsed;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class ReferralRedirectController extends Controller
{
    public function handle(Request $request, string $code): Response
    {
        $referral = Referral::query()
            ->with([
                'ambassador.identity.personalProfile',
                'ambassador.identity.professionalProfile',
                'ambassador.identity.credentials',
            ])
            ->where('code', $code)
            ->first();

        if (! $referral) {
            abort(404, 'Referral not found.');
        }

        if ($referral->revoked_at !== null || ($referral->expires_at !== null && $referral->expires_at->isPast())) {
            $expiredPath = public_path('referrals/expired.html');
            if (is_file($expiredPath)) {
                return response()->file($expiredPath, ['Content-Type' => 'text/html'], 410);
            }
            $expiredUrl = (string) config('ambassador.referrals.expired_url', config('app.url') . '/referrals/expired');
            return redirect()->to($expiredUrl);
        }

        // ── Cookie navigateur (fallback web) ─────────────────────────────────
        $cookieName = (string) config('ambassador.referrals.cookie_name', 'ambassador_referral');
        $ttlMinutes = $referral->expires_at?->diffInMinutes(now()) ?? 1440;
        $secure     = (bool) config('session.secure_cookie', app()->environment('production'));
        $cookie = cookie(
            $cookieName,
            $referral->code,
            max(1, $ttlMinutes),
            '/',
            null,
            $secure,
            true,
            false,
            'lax'
        );

        // ── Génération du token de clic unique ───────────────────────────────
        $clickToken  = Str::random(64);
        $ip          = $request->ip() ?? '0.0.0.0';
        $ua          = $request->userAgent();
        $deviceModel = PendingReferralClick::extractDeviceModel($ua);
        $fingerprint = PendingReferralClick::computeFingerprint($ip, $deviceModel);
        $ttlDays     = (int) config('ambassador.referrals.click_token_ttl_days', 7);

        PendingReferralClick::query()->create([
            'referral_id'        => $referral->id,
            'click_token'        => $clickToken,
            'device_fingerprint' => $fingerprint,
            'ip_address'         => $ip,
            'device_model'       => $deviceModel,
            'user_agent'         => $ua,
            'expires_at'         => now()->addDays($ttlDays),
        ]);

        // ── Enregistrement du clic (une fois par jour) ────────────────────────
        $alreadyStartedToday = AmbassadorReferralUsage::query()
            ->where('referral_id', $referral->id)
            ->where('event_type', 'started')
            ->whereDate('occurred_at', now()->toDateString())
            ->exists();

        if (! $alreadyStartedToday) {
            AmbassadorReferralUsage::query()->create([
                'referral_id' => $referral->id,
                'event_type'  => 'started',
                'source'      => $this->resolveSource($ua),
                'occurred_at' => now(),
            ]);
            event(new ReferralUsed($referral->id, 'started'));
        }

        // ── Détection plateforme & URL cible ─────────────────────────────────
        $platform    = $this->resolvePlatform($ua);
        $redirectUrl = $this->buildRedirectUrl($platform, $referral->code, $clickToken);

        // ── Métadonnées Open Graph ────────────────────────────────────────────
        $siteName   = (string) config('ambassador.referrals.preview_site_name', 'CHEREH');
        $ambassador = $referral->ambassador;
        $identity   = $ambassador?->identity;
        $fullName   = trim((string) ($identity?->professionalProfile?->full_name ?? ''));

        if ($fullName === '') {
            $firstName = (string) ($identity?->personalProfile?->first_name ?? '');
            $lastName  = (string) ($identity?->personalProfile?->last_name ?? '');
            $fullName  = trim($firstName . ' ' . $lastName);
        }

        $phone     = $identity?->credentials?->firstWhere('type', 'phone')?->identifier;
        $baseUrl   = (string) config('ambassador.referrals.base_url', config('app.url'));
        $publicUrl = rtrim($baseUrl, '/') . '/r/' . $referral->code;

        $previewImageUrl = (string) config('ambassador.referrals.preview_image_url', '');
        if ($previewImageUrl !== '') {
            $previewPath = parse_url($previewImageUrl, PHP_URL_PATH);
            if ($previewPath !== null && ! is_file(public_path(ltrim($previewPath, '/')))) {
                $previewImageUrl = '';
            }
        }

        $title = $fullName !== '' ? "Invitation de {$fullName}" : "Invitation {$siteName}";
        $descriptionParts = [];
        if ($fullName !== '') {
            $descriptionParts[] = "Ambassadrice : {$fullName}";
        }
        if ($phone) {
            $descriptionParts[] = "Contact : {$phone}";
        }
        $descriptionParts[] = "Cliquez pour commencer l'evaluation.";
        $description = implode(' . ', $descriptionParts);

        $eTitle       = htmlspecialchars($title,       ENT_QUOTES, 'UTF-8');
        $eDesc        = htmlspecialchars($description, ENT_QUOTES, 'UTF-8');
        $eSiteName    = htmlspecialchars($siteName,    ENT_QUOTES, 'UTF-8');
        $ePublicUrl   = htmlspecialchars($publicUrl,   ENT_QUOTES, 'UTF-8');
        $eRedirectUrl = htmlspecialchars($redirectUrl, ENT_QUOTES, 'UTF-8');
        $eImageUrl    = $previewImageUrl !== '' ? htmlspecialchars($previewImageUrl, ENT_QUOTES, 'UTF-8') : '';
        $eToken       = htmlspecialchars($clickToken,  ENT_QUOTES, 'UTF-8');

        $imageMeta = $eImageUrl !== ''
            ? "<meta property=\"og:image\" content=\"{$eImageUrl}\">\n    <meta property=\"og:image:alt\" content=\"{$eTitle}\">"
            : '';

        // Le JS copie le token dans le presse-papiers AVANT la redirection
        // (fallback Priorite 3 du spec)
        $html = <<<HTML
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{$eTitle}</title>
    <meta name="description" content="{$eDesc}">
    <meta property="og:type" content="website">
    <meta property="og:title" content="{$eTitle}">
    <meta property="og:description" content="{$eDesc}">
    <meta property="og:url" content="{$ePublicUrl}">
    <meta property="og:site_name" content="{$eSiteName}">
    {$imageMeta}
</head>
<body>
    <p>Redirection en cours...</p>
    <p><a href="{$eRedirectUrl}">Continuer</a></p>
    <script>
    (function () {
        var token = '{$eToken}';
        var dest  = '{$eRedirectUrl}';
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(token).catch(function(){});
        } else {
            try {
                var ta = document.createElement('textarea');
                ta.value = token;
                ta.style.position = 'fixed';
                ta.style.opacity  = '0';
                document.body.appendChild(ta);
                ta.focus(); ta.select();
                document.execCommand('copy');
                document.body.removeChild(ta);
            } catch(e) {}
        }
        window.location.replace(dest);
    })();
    </script>
</body>
</html>
HTML;

        return response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8'])->withCookie($cookie);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers privés
    // ─────────────────────────────────────────────────────────────────────────

    private function resolvePlatform(?string $ua): string
    {
        if (! $ua) {
            return 'web';
        }
        $u = strtolower($ua);
        if (str_contains($u, 'android')) {
            return 'android';
        }
        if (str_contains($u, 'iphone') || str_contains($u, 'ipad')) {
            return 'ios';
        }
        return 'web';
    }

    private function resolveSource(?string $ua): string
    {
        if (! $ua) {
            return 'browser';
        }
        $u = strtolower($ua);
        if (str_contains($u, 'ipad') || str_contains($u, 'tablet')) {
            return 'tablet';
        }
        if (str_contains($u, 'mobile') || str_contains($u, 'android')
            || str_contains($u, 'iphone') || str_contains($u, 'ipod')) {
            return 'mobile';
        }
        return 'browser';
    }

    /**
     * Android  -> Play Store avec referrer=chereh_token=<token>  (Install Referrer API)
     * iOS      -> App Store (fingerprint uniquement comme fallback)
     * Web      -> landing_url?ref=<code>
     */
    private function buildRedirectUrl(string $platform, string $referralCode, string $clickToken): string
    {
        if ($platform === 'android') {
            $playStoreUrl = (string) config('ambassador.referrals.play_store_url', '');
            if ($playStoreUrl !== '') {
                $referrerValue = urlencode('chereh_token=' . $clickToken);
                $sep = str_contains($playStoreUrl, '?') ? '&' : '?';
                return $playStoreUrl . $sep . 'referrer=' . $referrerValue;
            }
        }

        if ($platform === 'ios') {
            $appStoreUrl = (string) config('ambassador.referrals.app_store_url', '');
            if ($appStoreUrl !== '') {
                return $appStoreUrl;
            }
        }

        // Fallback web
        $landingUrl = (string) config('ambassador.referrals.landing_url', config('app.url'));
        $sep = str_contains($landingUrl, '?') ? '&' : '?';
        return rtrim($landingUrl, '/') . $sep . 'ref=' . urlencode($referralCode);
    }
}
