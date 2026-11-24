<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalogues', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('version_label')->nullable();
            $table->string('cover_path')->nullable();
            $table->string('pdf_path')->nullable();
            $table->string('pdf_original_name')->nullable();
            $table->unsignedBigInteger('pdf_bytes')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        if (Schema::hasTable('catalogue_settings')) {
            $position = 0;
            foreach (DB::table('catalogue_settings')->orderBy('id')->get() as $row) {
                if (! is_string($row->pdf_path) || $row->pdf_path === '') {
                    continue;
                }

                $slug = $position === 0 ? 'product-catalogue' : 'product-catalogue-'.($position + 1);
                DB::table('catalogues')->insert([
                    'title' => 'Product catalogue',
                    'slug' => $slug,
                    'pdf_path' => $row->pdf_path,
                    'pdf_original_name' => $row->pdf_original_name,
                    'pdf_bytes' => $row->pdf_bytes,
                    'position' => $position,
                    'created_at' => $row->created_at ?? now(),
                    'updated_at' => $row->updated_at ?? now(),
                ]);
                $position++;
            }

            Schema::drop('catalogue_settings');
        }
    }

    public function down(): void
    {
        Schema::create('catalogue_settings', function (Blueprint $table) {
            $table->id();
            $table->string('pdf_path')->nullable();
            $table->string('pdf_original_name')->nullable();
            $table->unsignedBigInteger('pdf_bytes')->nullable();
            $table->timestamp('pdf_uploaded_at')->nullable();
            $table->timestamps();
        });

        $first = DB::table('catalogues')->orderBy('position')->orderBy('id')->first();
        if ($first !== null && is_string($first->pdf_path) && $first->pdf_path !== '') {
            DB::table('catalogue_settings')->insert([
                'pdf_path' => $first->pdf_path,
                'pdf_original_name' => $first->pdf_original_name,
                'pdf_bytes' => $first->pdf_bytes,
                'pdf_uploaded_at' => $first->updated_at,
                'created_at' => $first->created_at,
                'updated_at' => $first->updated_at,
            ]);
        }

        Schema::drop('catalogues');
    }
};
