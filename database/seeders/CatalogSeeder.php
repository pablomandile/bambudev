<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\MicroApp;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Catálogo inicial del launcher, tal cual el prototipo de docs/design.
 */
class CatalogSeeder extends Seeder
{
    private const CATEGORIES = [
        'Finanzas', 'Salud', 'Hogar', 'Mascotas', 'Ocio',
        'Escritura', 'Documentos', 'Organización', 'Compras', 'Viajes',
    ];

    private const APPS = [
        ['mibilletera', 'MiBilletera', 'Finanzas', 'ph-wallet', 150, 'plata dinero gastos ahorro', 'Gastos, ahorros y ese café que no anotaste. Tus cuentas, en orden y sin drama.'],
        ['misalud', 'MiSalud', 'Salud', 'ph-heartbeat', 20, 'medico turnos remedios', 'Turnos, medicación y estudios médicos en un solo lugar. Tu cuerpo te lo agradece.'],
        ['alquilerfacil', 'AlquilerFácil', 'Hogar', 'ph-house-line', 60, 'casa depto contrato', 'Contratos, pagos y aumentos de alquiler sin dolores de cabeza.'],
        ['huella', 'Huella', 'Mascotas', 'ph-paw-print', 85, 'perro gato veterinario', 'Vacunas, paseos y mimos de tu mascota, todo bien registrado.'],
        ['movieboxd', 'Movieboxd', 'Ocio', 'ph-film-slate', 330, 'pelis peliculas cine series', 'Tu diario de películas: lo que viste, lo que amaste y lo que tienes pendiente.'],
        ['escribelo', 'Escríbelo', 'Escritura', 'ph-pen-nib', 275, 'notas ideas texto', 'Notas rápidas e ideas sueltas, antes de que se escapen.'],
        ['docbrainer', 'Docbrainer', 'Documentos', 'ph-brain', 235, 'pdf ia preguntas', 'Sube un documento y pregúntale lo que quieras. Él lee, tú decides.'],
        ['agendaflex', 'AgendaFlex', 'Organización', 'ph-calendar-check', 195, 'calendario tareas citas', 'Una agenda que se adapta a tu día, y no al revés.'],
    ];

    public function run(): void
    {
        foreach (self::CATEGORIES as $i => $name) {
            Category::updateOrCreate(['slug' => Str::slug($name)], ['name' => $name, 'sort_order' => $i + 1]);
        }

        $categories = Category::pluck('id', 'name');

        foreach (self::APPS as $i => [$slug, $name, $cat, $icon, $hue, $tags, $desc]) {
            MicroApp::updateOrCreate(['slug' => $slug], [
                'name' => $name,
                'category_id' => $categories[$cat],
                'icon' => $icon,
                'hue' => $hue,
                'tags' => $tags,
                'description' => $desc,
                'visible' => true,
                'sort_order' => $i + 1,
                'target_type' => MicroApp::TARGET_EXTERNAL,
            ]);
        }
    }
}
