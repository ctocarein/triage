<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use PDO;
use Throwable;

abstract class TestCase extends BaseTestCase
{
    /**
     * Filet de sécurité pour la base SQLite en mémoire.
     *
     * Avec `DB_DATABASE=:memory:`, Laravel conserve un unique PDO pour tout le
     * process (RefreshDatabaseState::$inMemoryConnections). Si le tearDown d'un
     * test est interrompu avant `parent::tearDown()` — par exemple parce qu'un
     * `Mockery::close()` appelé trop tôt lève une InvalidCountException — le
     * rollback de RefreshDatabase n'a jamais lieu et la transaction reste
     * ouverte sur ce PDO partagé. Tous les tests suivants échouent alors sur
     * « There is already an active transaction », ce qui rend impossible de
     * distinguer une vraie régression d'une cascade.
     *
     * On referme donc toute transaction résiduelle avant de démarrer le test.
     */
    protected function setUp(): void
    {
        $this->rollBackLeakedInMemoryTransactions();

        parent::setUp();
    }

    private function rollBackLeakedInMemoryTransactions(): void
    {
        foreach (RefreshDatabaseState::$inMemoryConnections as $pdo) {
            if (! $pdo instanceof PDO) {
                continue;
            }

            try {
                while ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
            } catch (Throwable) {
                // Rien à faire : le test en cours signalera lui-même l'anomalie.
            }
        }
    }
}
