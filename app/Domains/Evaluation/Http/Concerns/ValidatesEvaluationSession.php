<?php

namespace App\Domains\Evaluation\Http\Concerns;

use App\Domains\Evaluation\Models\EvaluationSession;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;

trait ValidatesEvaluationSession
{
    protected function ensureSessionAccess(Request $request, EvaluationSession $session): void
    {
        try {
            $this->authorize('view', $session);
        } catch (AuthorizationException $exception) {
            abort(404, 'Session introuvable.');
        }

        if ($this->tokenInvalidatesSession($request, $session)) {
            $this->expireSession($session);
            abort(410, 'Session invalidee.');
        }

        if ($session->isExpired()) {
            $this->expireSession($session);
            abort(410, 'Session expiree.');
        }
    }

    protected function tokenInvalidatesSession(Request $request, EvaluationSession $session): bool
    {
        $tokenId = $request->user()?->currentAccessToken()?->id;
        $context = $session->context ?? [];
        $sessionTokenId = is_array($context) ? ($context['token_id'] ?? null) : null;

        return $tokenId !== null
            && $sessionTokenId !== null
            && (int) $tokenId !== (int) $sessionTokenId;
    }

    protected function expireSession(EvaluationSession $session): void
    {
        if ($session->status !== 'expired') {
            $updates = ['status' => 'expired'];
            if ($session->expires_at === null) {
                $updates['expires_at'] = now();
            }
            $session->update($updates);
        }
    }
}
