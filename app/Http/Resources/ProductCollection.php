<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class ProductCollection extends ResourceCollection
{
    public $collects = ProductResource::class;

    // Sin override: el ResourceCollection sobre un paginador ya genera
    // automáticamente `data`, `links` y `meta` (current_page, total, etc.).
    // Los campos extra (success, message...) se añaden con ->additional([...])
    // en el controlador, y aparecen al nivel raíz junto a esa estructura.
}
