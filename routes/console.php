<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// El registro diario del dólar: cada hora, haya o no visitas en el Inicio. La
// última lectura de cada día queda como el valor de ese día. Necesita el cron de
// `schedule:run` en hPanel.
Schedule::command('dollar:record')->hourly()->withoutOverlapping();
