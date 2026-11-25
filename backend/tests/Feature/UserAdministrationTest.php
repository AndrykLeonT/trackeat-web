<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UserAdministrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_redirects_by_role_and_discards_an_admin_intended_url(): void
    {
        foreach ([
            'cliente' => 'menu', 'cocinero' => 'comandas', 'mesero' => 'mesero.pedidos',
            'cajero' => 'caja.index', 'administrador' => 'admin.panel',
        ] as $role => $route) {
            $user = User::factory()->create(['rol' => $role]);
            $this->withSession(['url.intended' => route('admin.panel')])->post('/login', [
                'email' => $user->email, 'password' => 'password',
            ])->assertRedirect($role === 'cliente' ? route('menu').'/' : route($route));
            $this->post('/logout')->assertRedirect('/');
        }
    }

    public function test_inertia_login_loads_the_static_menu_with_a_trailing_slash(): void
    {
        $user = User::factory()->create();
        $this->post('/login', ['email' => $user->email, 'password' => 'password'], ['X-Inertia' => 'true'])
            ->assertStatus(409)->assertHeader('X-Inertia-Location', url('/menu').'/');
    }

    public function test_session_cookie_has_secure_http_only_and_same_site_attributes(): void
    {
        config(['session.secure' => true]);
        $cookie = $this->get('/login')->assertOk()->getCookie(config('session.cookie'), false);

        $this->assertNotNull($cookie);
        $this->assertTrue($cookie->isSecure());
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('lax', $cookie->getSameSite());
    }

    public function test_users_cannot_access_or_mutate_accounts_by_id_without_the_admin_role(): void
    {
        $target = User::factory()->create();
        $this->get('/admin')->assertRedirect('/login');
        $this->actingAs($target)->get('/admin/usuarios')->assertForbidden()->assertInertia(fn (Assert $page) => $page->component('errors/403', false));
        $this->postJson('/admin/usuarios', $this->account())->assertForbidden();
        $this->patchJson("/admin/usuarios/{$target->id}", $this->account())->assertForbidden();
        $this->deleteJson("/admin/usuarios/{$target->id}")->assertForbidden();
        $this->assertFalse($target->fresh()->eliminado);
    }

    public function test_admin_creates_edits_and_deactivates_clients_without_deleting_them(): void
    {
        $admin = User::factory()->create(['rol' => 'administrador']);
        $this->actingAs($admin)->post('/admin/usuarios', $this->account() + [
            'password' => 'UnaClave!987', 'password_confirmation' => 'UnaClave!987',
        ])->assertSessionHasNoErrors()->assertRedirect(route('admin.usuarios'));

        $account = User::where('email', 'gestion@example.test')->sole();
        $this->assertSame('cliente', $account->rol);
        $this->assertTrue(Hash::check('UnaClave!987', $account->password));
        $this->get('/admin/usuarios?buscar=gestion&rol=cliente')->assertInertia(fn (Assert $page) => $page
            ->component('admin/usuarios', false)->has('users.data', 1)->missing('users.data.0.password'));
        $this->patch("/admin/usuarios/{$account->id}", array_replace($this->account(), [
            'rol' => 'cocinero', 'estacion' => 'barra', 'eliminado' => true,
        ]))->assertSessionHasNoErrors();
        $this->assertSame('barra', $account->fresh()->estacion);
        $this->assertFalse($account->fresh()->eliminado);
        $token = Password::createToken($account);

        $this->delete("/admin/usuarios/{$account->id}")->assertSessionHasNoErrors()->assertRedirect(route('admin.usuarios'));
        $this->assertTrue($account->fresh()->eliminado);
        $this->assertFalse(Password::tokenExists($account, $token));
        $this->actingAs($account->fresh())->getJson('/perfil')->assertForbidden();
    }

    public function test_last_active_admin_cannot_be_deactivated_or_demoted(): void
    {
        $admin = User::factory()->create(['rol' => 'administrador']);
        User::factory()->create(['rol' => 'administrador', 'eliminado' => true]);
        $this->actingAs($admin)->deleteJson("/admin/usuarios/{$admin->id}")
            ->assertUnprocessable()->assertJsonValidationErrors('cuenta');
        $this->patchJson("/admin/usuarios/{$admin->id}", array_replace($this->account(), ['email' => $admin->email]))
            ->assertUnprocessable()->assertJsonValidationErrors('rol');
        $this->assertSame('administrador', $admin->fresh()->rol);
        $this->assertFalse($admin->fresh()->eliminado);

        $second = User::factory()->create(['rol' => 'administrador']);
        $this->delete("/admin/usuarios/{$second->id}")->assertSessionHasNoErrors();
        $this->assertTrue($second->fresh()->eliminado);
    }

    public function test_profile_ignores_privileged_fields_and_requires_password_for_sensitive_changes(): void
    {
        $user = User::factory()->create();
        $details = $this->account() + ['id' => $user->id + 1, 'eliminado' => true];
        $details['rol'] = 'administrador';

        $this->actingAs($user)->patchJson('/perfil', $details)->assertUnprocessable()->assertJsonValidationErrors('current_password');
        $this->patchJson('/perfil', $details + ['current_password' => 'password'])->assertRedirect();
        $this->assertSame('cliente', $user->fresh()->rol);
        $this->assertFalse($user->fresh()->eliminado);
        $this->assertSame('gestion@example.test', $user->fresh()->email);
        $token = Password::createToken($user->fresh());

        $this->putJson('/perfil/contrasena', [
            'current_password' => 'errónea', 'password' => 'OtraClave!987', 'password_confirmation' => 'OtraClave!987',
        ])->assertUnprocessable()->assertJsonValidationErrors('current_password');
        $this->putJson('/perfil/contrasena', [
            'current_password' => 'password', 'password' => 'OtraClave!987', 'password_confirmation' => 'OtraClave!987',
        ])->assertRedirect();
        $this->assertTrue(Hash::check('OtraClave!987', $user->fresh()->password));
        $this->assertFalse(Password::tokenExists($user->fresh(), $token));
    }

    public function test_dashboard_uses_operational_records_and_excludes_refunds_and_tips(): void
    {
        $this->seed(DemoSeeder::class);
        $admin = User::where('rol', 'administrador')->sole();

        $this->actingAs($admin)->get('/admin')->assertInertia(fn (Assert $page) => $page
            ->component('admin/panel', false)
            ->missing('auth.user.telefono')
            ->has('days', 30)
            ->where('days.29.value', 3)
            ->has('products', 3)
            ->where('products.0.label', 'Hamburguesa')->where('products.0.value', 2)
            ->where('sales.0.value', 160)->where('sales.1.value', 50)->where('sales.2.value', 0)
            ->where('salesTotal', '210.00')
            ->where('stations.0.label', 'Barra')->where('stations.1.label', 'Cocina'));
    }

    public function test_recovery_ignores_inactive_accounts_and_prevents_token_reuse(): void
    {
        Notification::fake();
        $inactive = User::factory()->create(['eliminado' => true]);
        $active = User::factory()->create();

        $this->post('/forgot-password', ['email' => $inactive->email])->assertSessionHas('status');
        Notification::assertNothingSent();
        $token = Password::createToken($active);
        $payload = ['token' => $token, 'email' => $active->email, 'password' => 'NuevaClave!987', 'password_confirmation' => 'NuevaClave!987'];
        $this->post('/reset-password', $payload)->assertRedirect('/login');
        $this->postJson('/reset-password', $payload)->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    private function account(): array
    {
        return ['primer_nombre' => 'Cuenta', 'primer_apellido' => 'Gestionada', 'email' => 'GESTION@example.test', 'rol' => 'cliente'];
    }
}
