<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('analytee_prompts', function (Blueprint $table) {
            $table->dropUnique(['key']);

            $table->integer('version')->default(1);
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->boolean('is_published')->default(true);

            $table->unique(['key', 'version']);
            $table->foreign('parent_id')->references('id')->on('analytee_prompts');
        });

        DB::table('analytee_prompts')->update([
            'version' => 1,
            'parent_id' => null,
            'is_published' => 1,
        ]);
    }

    public function down(): void
    {
        Schema::table('analytee_prompts', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropUnique(['key', 'version']);

            $table->dropColumn(['version', 'parent_id', 'is_published']);

            $table->unique(['key']);
        });
    }
};
