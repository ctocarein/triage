<?php

namespace App\Console\Commands;

use App\Domains\Evaluation\Services\Catalog\CatalogConsistencyChecker;
use Illuminate\Console\Command;

class CheckEvaluationCatalog extends Command
{
    protected $signature = 'evaluation:catalog-check';

    protected $description = 'Validate evaluation catalog consistency (routing targets, visible_if refs, duplicate orders).';

    public function handle(CatalogConsistencyChecker $checker): int
    {
        $errors = $checker->check();

        if ($errors === []) {
            $this->info('Catalog consistency check passed.');
            return self::SUCCESS;
        }

        $this->error('Catalog consistency check failed:');
        foreach ($errors as $error) {
            $this->line("- {$error}");
        }

        return self::FAILURE;
    }
}

