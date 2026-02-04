<?php

namespace App\Observers;

use App\Domains\Engagement\Infrastructure\Persistence\Models\Campaign;
use App\Domains\Evaluation\Models\EvaluationSession;
use App\Domains\Engagement\Infrastructure\Persistence\Models\QrCode;
use App\Domains\Report\Models\Report;
use App\Domains\Engagement\Infrastructure\Persistence\Models\ScanEvent;
use App\Support\OrganizationDashboardCache;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;

class OrganizationDashboardCacheObserver
{
    public function saved(Model $model): void
    {
        $this->flush($model);
    }

    public function deleted(Model $model): void
    {
        $this->flush($model);
    }

    protected function flush(Model $model): void
    {
        $organizationId = $this->resolveOrganizationId($model);

        if ($organizationId !== null) {
            OrganizationDashboardCache::flushForOrganization($organizationId);
        }
    }

    protected function resolveOrganizationId(Model $model): ?string
    {
        if (isset($model->organization_id) && $model->organization_id !== null) {
            return (string) $model->organization_id;
        }

        if ($model instanceof ScanEvent) {
            $model->loadMissing('qrCode');

            $organizationId = $model->qrCode?->organization_id;

            return $organizationId !== null ? (string) $organizationId : null;
        }

        if ($model instanceof Report) {
            $model->loadMissing('evaluationSession.scanEvents.qrCode');

            return $this->firstOrganizationIdFromScanEvents($model->evaluationSession?->scanEvents);
        }

        if ($model instanceof EvaluationSession) {
            $model->loadMissing('scanEvents.qrCode');

            return $this->firstOrganizationIdFromScanEvents($model->scanEvents);
        }

        return null;
    }

    /**
     * @param EloquentCollection<int, ScanEvent>|null $events
     */
    protected function firstOrganizationIdFromScanEvents(?EloquentCollection $events): ?string
    {
        if ($events === null) {
            return null;
        }

        foreach ($events as $event) {
            $event->loadMissing('qrCode');

            $organizationId = $event->qrCode?->organization_id;
            if ($organizationId !== null) {
                return (string) $organizationId;
            }
        }

        return null;
    }
}

