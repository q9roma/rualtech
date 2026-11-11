<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CatalogProductPolicy extends AdminResourcePolicy
{
    public function delete(User $user, Model $record): bool
    {
        // Imported identities must survive price updates. Archive by is_active instead.
        return $user->is_admin && ! DB::table('catalog_source_products')->where('product_id', $record->id)->exists()
            && ! DB::table('catalog_import_rows')->where('product_id', $record->id)->exists();
    }
}
