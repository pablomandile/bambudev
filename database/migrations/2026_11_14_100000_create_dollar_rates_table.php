<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El registro diario del dólar: una fila por tipo y por día, con el último
     * valor que se leyó ese día (ver App\Services\Dollar\DollarRates).
     */
    public function up(): void
    {
        Schema::create('dollar_rates', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20);
            $table->date('date');
            $table->decimal('buy', 12, 2)->nullable();
            $table->decimal('sell', 12, 2)->nullable();
            $table->string('source');
            // Cuándo lo publicó la fuente (no cuándo se leyó).
            $table->dateTime('captured_at');
            $table->timestamps();

            $table->unique(['type', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dollar_rates');
    }
};
