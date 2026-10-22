<?php

namespace App\Http\Resources;

use App\Models\MicroApp;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Forma de una app para el frontend (la misma que usa el prototipo).
 *
 * @mixin MicroApp
 */
class MicroAppResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->slug,
            'name' => $this->name,
            'cat' => $this->category->name,
            'categoryId' => $this->category_id,
            'icon' => $this->icon,
            'hue' => $this->hue,
            'tags' => $this->tags ?? '',
            'desc' => $this->description ?? '',
            'visible' => $this->visible,
            'targetType' => $this->target_type,
            'target' => $this->target,
            'hasTarget' => filled($this->target),
        ];
    }
}
