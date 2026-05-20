<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspirar', function () {
    $this->comment(Inspiring::quote());
})->purpose('Muestra una cita inspiradora');
