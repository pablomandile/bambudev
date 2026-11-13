<?php

namespace App\Console\Commands;

use App\Services\Dollar\DollarRates;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('dollar:record')]
#[Description('Lee el valor del dólar de todas las fuentes y lo guarda en el registro diario')]
class RecordDollarRates extends Command
{
    public function handle(DollarRates $dollar): int
    {
        $rates = $dollar->refresh();

        if ($rates === null) {
            $this->error('Ninguna fuente respondió: no se registró nada.');

            return self::FAILURE;
        }

        $this->info(count($rates['rates']).' cotizaciones al día ('.$rates['updatedAt'].').');

        return self::SUCCESS;
    }
}
