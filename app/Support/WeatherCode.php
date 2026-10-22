<?php

namespace App\Support;

/**
 * Traduce los códigos de tiempo WMO que devuelve Open-Meteo a un texto corto y
 * a un ícono de Phosphor, con variante de noche donde aparece el sol.
 *
 * @see https://open-meteo.com/en/docs (sección "WMO Weather interpretation codes")
 */
final class WeatherCode
{
    /**
     * @return array{label: string, icon: string}
     */
    public static function describe(int $code, bool $isDay): array
    {
        return match (true) {
            $code === 0 => ['label' => 'Despejado', 'icon' => $isDay ? 'ph-sun' : 'ph-moon'],
            in_array($code, [1, 2], true) => ['label' => 'Parcialmente nublado', 'icon' => $isDay ? 'ph-cloud-sun' : 'ph-cloud-moon'],
            $code === 3 => ['label' => 'Nublado', 'icon' => 'ph-cloud'],
            in_array($code, [45, 48], true) => ['label' => 'Niebla', 'icon' => 'ph-cloud-fog'],
            $code >= 51 && $code <= 57 => ['label' => 'Llovizna', 'icon' => 'ph-drop'],
            ($code >= 61 && $code <= 67) || ($code >= 80 && $code <= 82) => ['label' => 'Lluvia', 'icon' => 'ph-cloud-rain'],
            ($code >= 71 && $code <= 77) || in_array($code, [85, 86], true) => ['label' => 'Nieve', 'icon' => 'ph-cloud-snow'],
            $code >= 95 && $code <= 99 => ['label' => 'Tormenta', 'icon' => 'ph-cloud-lightning'],
            default => ['label' => '—', 'icon' => 'ph-cloud'],
        };
    }
}
