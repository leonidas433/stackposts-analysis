<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('analytee_accounts')) {
            Schema::create('analytee_accounts', function (Blueprint $table) {
                $table->engine = 'InnoDB';
                $table->charset = 'utf8mb4';

                $table->bigIncrements('id');
                $table->integer('team_id');
                $table->integer('account_id');
                $table->string('status', 32)->default('connected');
                $table->dateTime('last_sync_at')->nullable();
                $table->integer('created');
                $table->integer('updated');

                $table->unique(['team_id', 'account_id'], 'uniq_analytee_accounts_team_account');
                $table->index(['team_id'], 'idx_analytee_accounts_team');
                $table->index(['team_id', 'account_id'], 'idx_analytee_accounts_team_account');
            });
        }

        if (! Schema::hasTable('analytee_reviews')) {
            Schema::create('analytee_reviews', function (Blueprint $table) {
                $table->engine = 'InnoDB';
                $table->charset = 'utf8mb4';

                $table->bigIncrements('id');
                $table->integer('team_id');
                $table->integer('account_id');
                $table->string('external_id', 191);
                $table->string('author_name', 255)->nullable();
                $table->tinyInteger('rating');
                $table->longText('text')->nullable();
                $table->string('language', 32)->nullable();
                $table->dateTime('published_at');
                $table->integer('created');
                $table->integer('updated');

                $table->unique(['team_id', 'external_id'], 'uniq_analytee_reviews_team_external');
                $table->index(['team_id'], 'idx_analytee_reviews_team');
                $table->index(['team_id', 'account_id'], 'idx_analytee_reviews_team_account');
                $table->index(['team_id', 'account_id', 'published_at'], 'idx_analytee_reviews_team_account_published');
                $table->index(['external_id'], 'idx_analytee_reviews_external');
            });
        }

        if (! Schema::hasTable('analytee_review_stats')) {
            Schema::create('analytee_review_stats', function (Blueprint $table) {
                $table->engine = 'InnoDB';
                $table->charset = 'utf8mb4';

                $table->bigIncrements('id');
                $table->integer('team_id');
                $table->integer('account_id');
                $table->string('period', 16);
                $table->date('date');
                $table->integer('reviews_count')->default(0);
                $table->decimal('avg_rating', 4, 2)->default(0);
                $table->integer('created');

                $table->unique(['team_id', 'account_id', 'period', 'date'], 'uniq_analytee_review_stats_bucket');
                $table->index(['team_id'], 'idx_analytee_review_stats_team');
                $table->index(['team_id', 'date'], 'idx_analytee_review_stats_team_date');
                $table->index(['team_id', 'account_id'], 'idx_analytee_review_stats_team_account');
                $table->index(['team_id', 'account_id', 'date'], 'idx_analytee_review_stats_team_account_date');
            });
        }

        if (! Schema::hasTable('analytee_rating_distribution')) {
            Schema::create('analytee_rating_distribution', function (Blueprint $table) {
                $table->engine = 'InnoDB';
                $table->charset = 'utf8mb4';

                $table->bigIncrements('id');
                $table->integer('team_id');
                $table->integer('account_id');
                $table->date('date');
                $table->integer('stars_1')->default(0);
                $table->integer('stars_2')->default(0);
                $table->integer('stars_3')->default(0);
                $table->integer('stars_4')->default(0);
                $table->integer('stars_5')->default(0);
                $table->integer('created');

                $table->unique(['team_id', 'account_id', 'date'], 'uniq_analytee_rating_dist_bucket');
                $table->index(['team_id'], 'idx_analytee_rating_dist_team');
                $table->index(['team_id', 'date'], 'idx_analytee_rating_dist_team_date');
                $table->index(['team_id', 'account_id'], 'idx_analytee_rating_dist_team_account');
                $table->index(['team_id', 'account_id', 'date'], 'idx_analytee_rating_dist_team_account_date');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('analytee_rating_distribution');
        Schema::dropIfExists('analytee_review_stats');
        Schema::dropIfExists('analytee_reviews');
        Schema::dropIfExists('analytee_accounts');
    }
};
