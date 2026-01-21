<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateAnalyteePromptsTable extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('analytee_prompts')) {
            Schema::create('analytee_prompts', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->string('title');
                $table->text('description')->nullable();
                $table->longText('prompt');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        $exists = DB::table('analytee_prompts')->where('key', 'review_reply_default')->exists();
        if (! $exists) {
            DB::table('analytee_prompts')->insert([
                'key' => 'review_reply_default',
                'title' => 'Respuesta inteligente a reseñas',
                'description' => 'Prompt para analizar reseñas y sugerir una respuesta editable.',
                'prompt' => "Reseña del cliente:\n{{review_text}}\n\nNombre del negocio:\n{{business_name}}\n\nIdioma preferido:\n{{language}}\n\nInstrucciones:\nGenera una respuesta profesional, cercana y educada, escrita desde la perspectiva del propietario del negocio.\nNo inventes hechos. Si faltan detalles, sé general y ofrece ayuda.\nDevuelve solo el texto final de la respuesta.",
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down()
    {
        Schema::dropIfExists('analytee_prompts');
    }
}
