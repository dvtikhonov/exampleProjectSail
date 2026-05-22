<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('oauth_clients', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->nullableMorphs('owner');
            $table->string('name');
            $table->string('secret')->nullable();
            $table->string('provider')->nullable();
            $table->json('redirect_uris');
            $table->json('grant_types');
            $table->boolean('revoked');
            $table->timestamps();
        });

        Schema::create('oauth_access_tokens', function (Blueprint $table) {
            $table->string('id', 100)->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->uuid('client_id');
            $table->string('name')->nullable();
            $table->text('scopes')->nullable();
            $table->boolean('revoked');
            $table->timestamps();
            $table->dateTime('expires_at')->nullable();

            $table->foreign('client_id')->references('id')->on('oauth_clients')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('oauth_access_tokens');
        Schema::dropIfExists('oauth_clients');
    }
};
