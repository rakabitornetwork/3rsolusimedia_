<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PageSection extends Model
{
    protected $fillable = [
        'key',
        'label',
        'title',
        'subtitle',
        'body',
        'content',
        'image',
        'image_secondary',
        'cta_label',
        'cta_url',
        'is_visible',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'content' => 'array',
            'is_visible' => 'boolean',
        ];
    }
}
