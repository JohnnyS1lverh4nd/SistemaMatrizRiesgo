<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::resetPasswords());
});

test('reset password link can be requested', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->postJson(route('password.email'), ['email' => $user->email])->assertOk();

    Notification::assertSentTo($user, ResetPassword::class);
});

test('reset link request does not reveal whether an email is registered', function () {
    Notification::fake();

    $user = User::factory()->create();

    $registrado = $this->postJson(route('password.email'), ['email' => $user->email]);
    $desconocido = $this->postJson(route('password.email'), ['email' => 'nadie@infotep.test']);
    // Segunda solicitud inmediata: el broker la limita, pero la respuesta no debe delatarlo.
    $limitado = $this->postJson(route('password.email'), ['email' => $user->email]);

    $registrado->assertOk();
    $desconocido->assertOk();
    $limitado->assertOk();

    expect($desconocido->json())->toBe($registrado->json())
        ->and($limitado->json())->toBe($registrado->json());

    Notification::assertSentToTimes($user, ResetPassword::class, 1);
});

test('reset link request still validates the email format', function () {
    $this->postJson(route('password.email'), ['email' => 'no-es-un-email'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');
});

test('reset password link points to the frontend', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->postJson(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
        $url = $notification->toMail($user)->actionUrl;

        expect($url)
            ->toStartWith(config('app.frontend_url').'/restablecer-contrasena?')
            ->toContain('token='.$notification->token)
            ->toContain('email='.urlencode($user->email));

        return true;
    });
});

test('password can be reset with valid token', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->postJson(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
        $response = $this->postJson(route('password.update'), [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'nueva-password',
            'password_confirmation' => 'nueva-password',
        ]);

        $response->assertOk();

        return true;
    });

    $this->postJson(route('login.store'), [
        'email' => $user->email,
        'password' => 'nueva-password',
    ])->assertOk();
});

test('reset attempt does not reveal whether an email is registered', function () {
    $user = User::factory()->create();

    $datos = [
        'token' => 'invalid-token',
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ];

    $registrado = $this->postJson(route('password.update'), [...$datos, 'email' => $user->email]);
    $desconocido = $this->postJson(route('password.update'), [...$datos, 'email' => 'nadie@infotep.test']);

    $registrado->assertUnprocessable();
    $desconocido->assertUnprocessable();
    expect($desconocido->json())->toBe($registrado->json());
});

test('reset link requests are rate limited per ip across different emails', function () {
    Notification::fake();

    // Con correos distintos el límite por usuario del broker no aplica:
    // solo el límite por IP evita el envío masivo de correos.
    foreach (range(1, 5) as $i) {
        $this->postJson(route('password.email'), ['email' => "persona{$i}@infotep.test"])->assertOk();
    }

    $this->postJson(route('password.email'), ['email' => 'persona6@infotep.test'])->assertTooManyRequests();

    // Otra IP no se ve afectada.
    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])
        ->postJson(route('password.email'), ['email' => 'persona7@infotep.test'])
        ->assertOk();
});

test('reset attempts are rate limited per ip', function () {
    $datos = [
        'token' => 'invalid-token',
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ];

    foreach (range(1, 5) as $i) {
        $this->postJson(route('password.update'), [...$datos, 'email' => "persona{$i}@infotep.test"])
            ->assertUnprocessable();
    }

    $this->postJson(route('password.update'), [...$datos, 'email' => 'persona6@infotep.test'])
        ->assertTooManyRequests();
});

test('password reset rate limit does not affect other routes', function () {
    foreach (range(1, 6) as $i) {
        $this->postJson(route('password.email'), ['email' => "persona{$i}@infotep.test"]);
    }

    $user = User::factory()->create();
    $this->postJson(route('login.store'), ['email' => $user->email, 'password' => 'password'])->assertOk();
});

test('password cannot be reset with invalid token', function () {
    $user = User::factory()->create();

    $response = $this->postJson(route('password.update'), [
        'token' => 'invalid-token',
        'email' => $user->email,
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors('email');
});

test('the reset email is written in Spanish and links to the Nuxt page', function () {
    Notification::fake();
    $user = User::factory()->create(['email' => 'ana@infotep.test']);

    $this->postJson(route('password.email'), ['email' => $user->email])->assertOk();

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notificacion) use ($user) {
        $correo = $notificacion->toMail($user);
        $html = (string) $correo->render();

        expect($correo->subject)->toBe('Restablecer contraseña')
            ->and($correo->actionText)->toBe('Restablecer contraseña')
            ->and($correo->actionUrl)->toStartWith(config('app.frontend_url').'/restablecer-contrasena?token=')
            ->and(implode(' ', $correo->introLines))->toContain('Recibe este correo porque se solicitó restablecer la contraseña de su cuenta.')
            ->and(implode(' ', $correo->outroLines))->toContain('Este enlace caduca en 60 minutos.')
            ->and($html)->toContain('Saludos,')
            ->and($html)->not->toContain('Reset Password')
            ->and($html)->not->toContain('Regards');

        return true;
    });
});
