<?php

namespace Tests\Feature;

use Database\Seeders\Identity\AmbassadorSeeder;
use Database\Seeders\Identity\IdentitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeederValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_evaluation_seeders_run_without_errors(): void
    {
        $this->seed();

        $this->assertDatabaseHas('dynamic_question_templates', [
            'key' => 'consent_declined_thank_you',
        ]);
    }

    /**
     * Le parcours de l'application mobile : numero, PIN, puis profil.
     * L'ambassadrice doit en sortir avec son role, sans etape manuelle.
     */
    public function test_seeded_ambassador_logs_in_by_phone_and_pin(): void
    {
        $this->seed(IdentitySeeder::class);
        $this->seed(AmbassadorSeeder::class);

        $auth = $this->postJson('/api/identity/phone', ['phone' => '+2250700000001'])
            ->assertSuccessful()
            ->assertJsonPath('security_gate.secret_set', true)
            ->assertJsonPath('security_gate.gate_required', true);

        $token = $auth->json('token');

        $this->withToken($token)
            ->postJson('/api/identity/unlock', ['method' => 'pin', 'secret' => '1234'])
            ->assertSuccessful();

        $roles = collect(
            $this->withToken($token)
                ->getJson('/api/identity/me')
                ->assertOk()
                ->json('identity.memberships')
        )->pluck('role');

        $this->assertContains('Ambassador', $roles);
    }
}
