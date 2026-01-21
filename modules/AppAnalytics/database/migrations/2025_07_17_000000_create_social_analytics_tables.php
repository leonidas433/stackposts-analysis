<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateSocialAnalyticsTables extends Migration
{
    public function up()
    {
        $driver = DB::connection()->getDriverName();

        if (! Schema::hasTable('social_analytics')) {
            Schema::create('social_analytics', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('account_id');
                $table->string('social_network', 50)->default('facebook');
                $table->string('metric', 255);
                $table->date('date');
                $table->integer('hour')->nullable();
                $table->double('value')->default(0);
                $table->integer('created')->nullable();

                $table->index('account_id');
                $table->index('metric');
                $table->index('date');
            });
        }

        if (! Schema::hasTable('social_analytics_posts')) {
            Schema::create('social_analytics_posts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('account_id');
                $table->string('social_network', 50);
                $table->string('post_id', 100);
                $table->date('date');
                $table->text('message')->nullable();
                $table->dateTime('created_time')->nullable();
                $table->text('full_picture')->nullable();
                $table->text('permalink_url')->nullable();
                $table->string('type', 50)->nullable();
                $table->string('status_type', 50)->nullable();
                $table->json('details')->nullable();
                $table->integer('created')->nullable();

                $table->unique(['account_id', 'social_network', 'post_id', 'date'], 'uniq_social_analytics_posts');
                $table->index('post_id', 'idx_social_analytics_posts_post_id');
            });
        }

        if (! Schema::hasTable('social_analytics_post_infos')) {
            Schema::create('social_analytics_post_infos', function (Blueprint $table) {
                $table->id();
                $table->string('post_id', 100);
                $table->unsignedBigInteger('account_id');
                $table->string('social_network', 50);
                $table->string('metric', 100);
                $table->double('value')->default(0);
                $table->date('date');
                $table->integer('created')->nullable();

                $table->unique(['post_id', 'metric', 'date'], 'uniq_social_analytics_post_infos');
                $table->index('post_id', 'idx_social_analytics_post_infos_post_id');
                $table->index('metric', 'idx_social_analytics_post_infos_metric');
                $table->index('date', 'idx_social_analytics_post_infos_date');
            });
        }

        if (! Schema::hasTable('social_analytics_snapshots')) {
            Schema::create('social_analytics_snapshots', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('account_id');
                $table->string('social_network', 50);
                $table->date('date');
                $table->json('data')->nullable();
                $table->integer('created')->nullable();

                $table->unique(['account_id', 'social_network', 'date'], 'uniq_social_analytics_snapshots');
            });
        }

        if (! Schema::hasTable('social_analytics_sync_log')) {
            Schema::create('social_analytics_sync_log', function (Blueprint $table) {
                $table->id();
                $table->bigInteger('account_id')->nullable();
                $table->string('social_network', 50)->nullable();
                $table->string('type', 50)->nullable();
                $table->date('date')->nullable();
                $table->integer('synced_at')->nullable();
            });
        }

        if ($driver === 'sqlite') {
            if (Schema::hasTable('social_analytics_posts')) {
                DB::statement('CREATE INDEX IF NOT EXISTS idx_social_analytics_posts_post_id ON social_analytics_posts (post_id)');
            }

            if (Schema::hasTable('social_analytics_post_infos')) {
                DB::statement('CREATE INDEX IF NOT EXISTS idx_social_analytics_post_infos_post_id ON social_analytics_post_infos (post_id)');
                DB::statement('CREATE INDEX IF NOT EXISTS idx_social_analytics_post_infos_metric ON social_analytics_post_infos (metric)');
                DB::statement('CREATE INDEX IF NOT EXISTS idx_social_analytics_post_infos_date ON social_analytics_post_infos (date)');
            }
        }
    }

    public function down() {}
}
