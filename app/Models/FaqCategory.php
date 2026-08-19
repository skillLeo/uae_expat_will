<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FaqCategory extends Model
{
    protected $fillable = ['key', 'order', 'label', 'locale'];

    public function faqs(): HasMany
    {
        return $this->hasMany(Faq::class, 'category_key', 'key')->orderBy('order');
    }
}
