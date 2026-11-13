<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpFoundation\Response;

/**
 * La API propia es solo para las apps de bambudev: cada una manda su clave en
 * el header X-Api-Key (ver `bambudev.api.keys`).
 */
class RequireApiKey
{
    /** Atributo del request con el nombre de la app, para el límite de consultas. */
    public const APP_ATTRIBUTE = 'api_app';

    public function handle(Request $request, Closure $next): Response
    {
        $given = (string) $request->header('X-Api-Key', '');
        /** @var array<string, string> $keys */
        $keys = Config::array('bambudev.api.keys');

        foreach ($keys as $key => $app) {
            // hash_equals: la comparación tarda lo mismo acierte o no.
            if ($given !== '' && hash_equals($key, $given)) {
                $request->attributes->set(self::APP_ATTRIBUTE, $app);

                return $next($request);
            }
        }

        return response()->json(['message' => 'Falta la clave de la API o no es válida.'], 401);
    }
}
