<?php

namespace App\Domains\Ambassador\Presentation\Http\Controllers;

use App\Domains\Ambassador\Infrastructure\Persistence\Models\Referral;
use App\Domains\Ambassador\Infrastructure\Persistence\Models\AmbassadorReferralUsage;
use App\Domains\Ambassador\Domain\Events\ReferralUsed;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        $cookieName = (string) config('ambassador.referrals.cookie_name', 'ambassador_referral');
        $ttlMinutes = $referral->expires_at?->diffInMinutes(now()) ?? 1440;

        $secure = (bool) config('session.secure_cookie', app()->environment('production'));
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

        $landingUrl = (string) config('ambassador.referrals.landing_url', config('app.url'));
        $separator = str_contains($landingUrl, '?') ? '&' : '?';
        $redirectUrl = rtrim($landingUrl, '/') . $separator . 'ref=' . urlencode($referral->code);

        $alreadyStartedToday = AmbassadorReferralUsage::query()
            ->where('referral_id', $referral->id)
            ->where('event_type', 'started')
            ->whereDate('occurred_at', now()->toDateString())
            ->exists();

        if (! $alreadyStartedToday) {
            AmbassadorReferralUsage::query()->create([
                'referral_id' => $referral->id,
                'event_type' => 'started',
                'source' => $this->resolveSource($request->userAgent()),
                'occurred_at' => now(),
            ]);

            event(new ReferralUsed($referral->id, 'started'));
        }

        $previewImageUrl = (string) config('ambassador.referrals.preview_image_url', '');
        if ($previewImageUrl !== '') {
            $previewPath = parse_url($previewImageUrl, PHP_URL_PATH);
            if ($previewPath !== null) {
                $localPath = public_path(ltrim($previewPath, '/'));
                if (! is_file($localPath)) {
                    $previewImageUrl = '';
                }
            }
        }

        $siteName = (string) config('ambassador.referrals.preview_site_name', 'CHEREH');
        $ambassador = $referral->ambassador;
        $identity = $ambassador?->identity;
        $fullName = trim((string) ($identity?->professionalProfile?->full_name ?? ''));

        if ($fullName === '') {
            $firstName = (string) ($identity?->personalProfile?->first_name ?? '');
            $lastName = (string) ($identity?->personalProfile?->last_name ?? '');
            $fullName = trim($firstName . ' ' . $lastName);
        }

        $phone = $identity?->credentials?->firstWhere('type', 'phone')?->identifier;

        $baseUrl = (string) config('ambassador.referrals.base_url', config('app.url'));
        $publicUrl = rtrim($baseUrl, '/') . '/r/' . $referral->code;

        $title = $fullName !== '' ? "Invitation de {$fullName}" : "Invitation {$siteName}";
        $descriptionParts = [];
        if ($fullName !== '') {
            $descriptionParts[] = "Ambassadrice: {$fullName}";
        }
        if ($phone) {
            $descriptionParts[] = "Contact: {$phone}";
        }
        $descriptionParts[] = "Cliquez pour commencer l'evaluation.";
        $description = implode(' · ', $descriptionParts);

        $escapedTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $escapedDescription = htmlspecialchars($description, ENT_QUOTES, 'UTF-8');
        $escapedSiteName = htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8');
        $escapedPublicUrl = htmlspecialchars($publicUrl, ENT_QUOTES, 'UTF-8');
        $escapedRedirectUrl = htmlspecialchars($redirectUrl, ENT_QUOTES, 'UTF-8');
        $escapedImageUrl = $previewImageUrl !== '' ? htmlspecialchars($previewImageUrl, ENT_QUOTES, 'UTF-8') : '';

        $imageMeta = $escapedImageUrl !== ''
            ? "<meta property=\"og:image\" content=\"{$escapedImageUrl}\">\n    <meta property=\"og:image:alt\" content=\"{$escapedTitle}\">"
            : '';

        $html = "<!doctype html>
<html lang=\"fr\">
<head>
    <meta charset=\"utf-8\">
    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">
    <title>{$escapedTitle}</title>
    <meta name=\"description\" content=\"{$escapedDescription}\">
    <meta property=\"og:type\" content=\"website\">
    <meta property=\"og:title\" content=\"{$escapedTitle}\">
    <meta property=\"og:description\" content=\"{$escapedDescription}\">
    <meta property=\"og:url\" content=\"{$escapedPublicUrl}\">
    <meta property=\"og:site_name\" content=\"{$escapedSiteName}\">
    {$imageMeta}
    <meta http-equiv=\"refresh\" content=\"0;url={$escapedRedirectUrl}\">
</head>
<body>
    <p>Redirection en cours...</p>
    <p><a href=\"{$escapedRedirectUrl}\">Continuer</a></p>
</body>
</html>";

        return response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8'])->withCookie($cookie);
    }

    private function resolveSource(?string $userAgent): string
    {
        if (! $userAgent) {
            return 'browser';
        }

        $ua = strtolower($userAgent);

        if (str_contains($ua, 'ipad') || str_contains($ua, 'tablet')) {
            return 'tablet';
        }

        if (
            str_contains($ua, 'mobile')
            || str_contains($ua, 'android')
            || str_contains($ua, 'iphone')
            || str_contains($ua, 'ipod')
        ) {
            return 'mobile';
        }

        return 'browser';
    }
}
