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
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('domain_id')->index()->constrained();
            $table->dateTime('start_time');
            $table->dateTime('end_time');
            $table->dateTime('loaded_time');
            $table->string('org');
            $table->string('external_id');
            $table->string('email');
            $table->string('extra_contact_info')->nullable();
            $table->string('error_string')->nullable();
            $table->string('policy_adkim')->nullable();
            $table->string('policy_aspf')->nullable();
            $table->string('policy_p')->nullable();
            $table->string('policy_sp')->nullable();
            $table->string('policy_pct')->nullable();
            $table->string('policy_fo')->nullable();
            $table->boolean('seen')->default(false);
            $table->timestamps();

            $table->unique(['domain_id', 'external_id']);
            $table->index(['org', 'start_time']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
