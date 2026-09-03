<?php

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

it('collapses duplicated locale segments and preserves the selected locale', function (string $url, string $target) {
    $this->get($url)->assertRedirect($target)->assertStatus(301);
})->with([
    ['/ru/pl', '/ru/'],
    ['/sk/es', '/sk/'],
    ['/sk/cs', '/sk/'],
    ['/ru/pl/kontakt?source=legacy', '/ru/kontakt?source=legacy'],
    ['/public/en/en', '/en/'],
    ['/services/hotel-zimmerreinigung-wolgast', '/de/hotel-zimmerreinigung-wolgast'],
    ['/tr/services/hotelreinigung-hamburg', '/tr/hotelreinigung-hamburg'],
]);

it('redirects duplicated legacy service URLs before calling the content API', function () {
    Http::fake();

    $this->get('/polsterreinigung-in-leverkusen-polsterreinigung-firma-in-leverkusen/')
        ->assertRedirect('/de/polsterreinigung-in-leverkusen')
        ->assertStatus(301);

    $this->get('/tr/polsterreinigung-in-leverkusen-polsterreinigung-firma-in-leverkusen?campaign=old')
        ->assertRedirect('/tr/polsterreinigung-in-leverkusen?campaign=old')
        ->assertStatus(301);
});

it('redirects known misspelled service location slugs to canonical pages', function () {
    Http::fake();

    $this->get('/restaurantreinigung-in-ludwighburg-restaurantreinigung-firma-in-ludwighburg/')
        ->assertRedirect('/de/restaurantreinigung-in-ludwigsburg')
        ->assertStatus(301);

    $this->get('/de/restaurantreinigung-in-ludwighburg?source=legacy')
        ->assertRedirect('/de/restaurantreinigung-in-ludwigsburg?source=legacy')
        ->assertStatus(301);

    $this->get('/wohnungsreinigung-in-hagen-wohnungsreinigung-firma-in-hagen/')
        ->assertRedirect('/de/apartmentreinigung-in-hagen')
        ->assertStatus(301);
});
