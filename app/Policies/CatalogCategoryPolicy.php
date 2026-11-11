<?php

namespace App\Policies;

use App\Models\{User, ProductCategory};
use Illuminate\Database\Eloquent\Model;

class CatalogCategoryPolicy extends AdminResourcePolicy
{
    public function delete(User $user, Model $record): bool
    {
        return $user->is_admin && ! $record->children()->exists() && ! $record->products()->exists()
            && ! \App\Models\CatalogImportMapping::where('catalog_category_id', $record->id)->exists();
    }
}
