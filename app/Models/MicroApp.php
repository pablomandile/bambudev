<?php

namespace App\Models;

use Database\Factories\MicroAppFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Una micro app del launcher. Su destino puede ser una ruta de este mismo
 * proyecto (internal) o una URL de otro sitio (external).
 *
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property string|null $description
 * @property int $category_id
 * @property string $icon
 * @property int $hue
 * @property string|null $logo_path
 * @property string|null $tags
 * @property bool $visible
 * @property int $sort_order
 * @property string $target_type
 * @property string|null $target
 * @property-read Category $category
 */
#[Fillable(['slug', 'name', 'description', 'category_id', 'icon', 'hue', 'tags', 'visible', 'sort_order', 'target_type', 'target'])]
class MicroApp extends Model
{
    /** @use HasFactory<MicroAppFactory> */
    use HasFactory;

    public const TARGET_INTERNAL = 'internal';

    public const TARGET_EXTERNAL = 'external';

    /**
     * Carpeta del disco público donde se guardan los logos propios.
     */
    public const LOGO_DIRECTORY = 'logos';

    protected static function booted(): void
    {
        static::creating(function (MicroApp $app) {
            $app->slug ??= static::uniqueSlug($app->name);
            $app->sort_order = $app->sort_order ?: ((int) static::max('sort_order')) + 1;
        });

        static::deleted(function (MicroApp $app) {
            if ($app->logo_path) {
                Storage::disk('public')->delete($app->logo_path);
            }
        });
    }

    public static function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'app';
        $slug = $base;

        for ($i = 2; static::where('slug', $slug)->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'hue' => 'integer',
            'visible' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @param  Builder<MicroApp>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @param  Builder<MicroApp>  $query
     */
    public function scopeVisible(Builder $query): void
    {
        $query->where('visible', true);
    }

    /**
     * URL pública del logo propio, o null si la app solo tiene el icono genérico.
     */
    public function logoUrl(): ?string
    {
        return $this->logo_path ? Storage::disk('public')->url($this->logo_path) : null;
    }

    /**
     * Guarda el logo nuevo (o lo quita, con null) y borra el archivo anterior.
     */
    public function replaceLogo(?UploadedFile $file): void
    {
        $old = $this->logo_path;
        $this->logo_path = $file?->store(self::LOGO_DIRECTORY, 'public') ?: null;
        $this->save();

        if ($old) {
            Storage::disk('public')->delete($old);
        }
    }

    /**
     * URL a la que lleva el launcher, o null si todavía no tiene destino.
     */
    public function launchUrl(): ?string
    {
        if (blank($this->target)) {
            return null;
        }

        return $this->target_type === self::TARGET_INTERNAL
            ? url($this->target)
            : $this->target;
    }
}
