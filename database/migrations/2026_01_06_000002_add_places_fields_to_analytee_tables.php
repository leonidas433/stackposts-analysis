<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('analytee_reviews')) {
            Schema::table('analytee_reviews', function (Blueprint $table) {
                if (! Schema::hasColumn('analytee_reviews', 'place_id')) {
                    $table->string('place_id', 191)->nullable()->after('account_id');
                    $table->index(['team_id', 'place_id'], 'idx_analytee_reviews_team_place');
                }
                if (! Schema::hasColumn('analytee_reviews', 'author_url')) {
                    $table->string('author_url', 500)->nullable()->after('author_name');
                }
                if (! Schema::hasColumn('analytee_reviews', 'meta')) {
                    $table->json('meta')->nullable()->after('language');
                }
                if (! Schema::hasColumn('analytee_reviews', 'owner_response_text')) {
                    $table->longText('owner_response_text')->nullable()->after('text');
                }
                if (! Schema::hasColumn('analytee_reviews', 'owner_response_at')) {
                    $table->dateTime('owner_response_at')->nullable()->after('owner_response_text');
                }
            });
        }

        if (Schema::hasTable('analytee_accounts')) {
            Schema::table('analytee_accounts', function (Blueprint $table) {
                if (! Schema::hasColumn('analytee_accounts', 'place_id')) {
                    $table->string('place_id', 191)->nullable()->after('account_id');
                    $table->index(['team_id', 'place_id'], 'idx_analytee_accounts_team_place');
                }
                if (! Schema::hasColumn('analytee_accounts', 'rating')) {
                    $table->decimal('rating', 4, 2)->nullable()->after('place_id');
                }
                if (! Schema::hasColumn('analytee_accounts', 'user_ratings_total')) {
                    $table->integer('user_ratings_total')->nullable()->after('rating');
                }
                if (! Schema::hasColumn('analytee_accounts', 'url')) {
                    $table->string('url', 1000)->nullable()->after('user_ratings_total');
                }
                if (! Schema::hasColumn('analytee_accounts', 'website')) {
                    $table->string('website', 1000)->nullable()->after('url');
                }
                if (! Schema::hasColumn('analytee_accounts', 'types')) {
                    $table->json('types')->nullable()->after('website');
                }
                if (! Schema::hasColumn('analytee_accounts', 'vicinity')) {
                    $table->string('vicinity', 1000)->nullable()->after('types');
                }
                if (! Schema::hasColumn('analytee_accounts', 'place_details')) {
                    $table->json('place_details')->nullable()->after('vicinity');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('analytee_reviews')) {
            Schema::table('analytee_reviews', function (Blueprint $table) {
                if (Schema::hasColumn('analytee_reviews', 'owner_response_at')) {
                    $table->dropColumn('owner_response_at');
                }
                if (Schema::hasColumn('analytee_reviews', 'owner_response_text')) {
                    $table->dropColumn('owner_response_text');
                }
                if (Schema::hasColumn('analytee_reviews', 'meta')) {
                    $table->dropColumn('meta');
                }
                if (Schema::hasColumn('analytee_reviews', 'author_url')) {
                    $table->dropColumn('author_url');
                }
                if (Schema::hasColumn('analytee_reviews', 'place_id')) {
                    $table->dropIndex('idx_analytee_reviews_team_place');
                    $table->dropColumn('place_id');
                }
            });
        }

        if (Schema::hasTable('analytee_accounts')) {
            Schema::table('analytee_accounts', function (Blueprint $table) {
                if (Schema::hasColumn('analytee_accounts', 'place_details')) {
                    $table->dropColumn('place_details');
                }
                if (Schema::hasColumn('analytee_accounts', 'vicinity')) {
                    $table->dropColumn('vicinity');
                }
                if (Schema::hasColumn('analytee_accounts', 'types')) {
                    $table->dropColumn('types');
                }
                if (Schema::hasColumn('analytee_accounts', 'website')) {
                    $table->dropColumn('website');
                }
                if (Schema::hasColumn('analytee_accounts', 'url')) {
                    $table->dropColumn('url');
                }
                if (Schema::hasColumn('analytee_accounts', 'user_ratings_total')) {
                    $table->dropColumn('user_ratings_total');
                }
                if (Schema::hasColumn('analytee_accounts', 'rating')) {
                    $table->dropColumn('rating');
                }
                if (Schema::hasColumn('analytee_accounts', 'place_id')) {
                    $table->dropIndex('idx_analytee_accounts_team_place');
                    $table->dropColumn('place_id');
                }
            });
        }
    }
};
