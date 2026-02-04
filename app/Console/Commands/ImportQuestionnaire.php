<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ImportQuestionnaire extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:import-questionnaire {file : Path to questionnaire JSON file}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import or validate questionnaire payloads';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $file = $this->argument('file');

        if (!is_string($file) || $file === '' || !file_exists($file)) {
            $this->error("Fichier introuvable: {$file}");
            return self::FAILURE;
        }

        $raw = file_get_contents($file);
        if ($raw === false) {
            $this->error("Impossible de lire le fichier: {$file}");
            return self::FAILURE;
        }

        $payload = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error('JSON invalide: ' . json_last_error_msg());
            return self::FAILURE;
        }

        if (!is_array($payload)) {
            $this->error('Format invalide: racine attendue de type objet ou tableau.');
            return self::FAILURE;
        }

        $questions = $payload['questions'] ?? $payload;
        if (!is_array($questions)) {
            $this->error('Format invalide: "questions" doit etre un tableau.');
            return self::FAILURE;
        }

        $knownQuestionKeys = [];
        $knownBlocKeys = [];
        foreach ($questions as $question) {
            if (!is_array($question)) {
                continue;
            }
            if (!empty($question['key']) && is_string($question['key'])) {
                $knownQuestionKeys[] = $question['key'];
            }
            if (!empty($question['bloc_key']) && is_string($question['bloc_key'])) {
                $knownBlocKeys[] = $question['bloc_key'];
            }
        }

        $knownQuestionKeys = array_values(array_unique($knownQuestionKeys));
        $knownBlocKeys = array_values(array_unique($knownBlocKeys));

        $validator = app(\App\Domains\Evaluation\Services\RoutingRulesValidator::class);
        $errors = [];
        foreach ($questions as $index => $question) {
            if (!is_array($question)) {
                $errors[] = "questions[{$index}] doit etre un objet.";
                continue;
            }

            $rules = $question['routing_rules'] ?? null;
            if ($rules === null) {
                continue;
            }

            $key = $question['key'] ?? "index {$index}";
            $errors = array_merge(
                $errors,
                $validator->validateRoutingRules($rules, $knownQuestionKeys, $knownBlocKeys, (string) $key)
            );
        }

        if (!empty($errors)) {
            $this->error('Erreurs de validation routing_rules:');
            foreach ($errors as $error) {
                $this->line("- {$error}");
            }
            return self::FAILURE;
        }

        $this->info('Validation routing_rules OK.');
        return self::SUCCESS;
    }
}
