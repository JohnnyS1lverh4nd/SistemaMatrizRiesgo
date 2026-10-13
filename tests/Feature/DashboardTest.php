<?php

use App\Models\User;

test('guests are redirected to the frontend login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(config('app.frontend_url').'/login');
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});
