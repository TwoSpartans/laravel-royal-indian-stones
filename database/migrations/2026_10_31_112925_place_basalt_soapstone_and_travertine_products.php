<?php

use App\Services\CatalogueIndex;
use App\Support\PlaceMissingMaterialProducts;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if (app(PlaceMissingMaterialProducts::class)->place() === 0) {
            return;
        }

        CatalogueIndex::write();
    }

    public function down(): void
    {
        //
    }
};
