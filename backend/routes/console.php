<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('trackeat:demo-link', function () {
    if (! config('trackeat.demo_enabled')) {
        $this->error('Activa TRACKEAT_DEMO_ENABLED para generar el enlace.');

        return 1;
    }
    $this->line(URL::temporarySignedRoute('demo.enter', now()->addHours(2), ['room' => (string) Str::uuid()]));
})->purpose('Enlace del salón válido durante dos horas');
