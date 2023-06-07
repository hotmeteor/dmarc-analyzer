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
        Schema::create('report_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->index()->constrained();
            $table->ipAddress()->index();
            $table->integer('rcount')->unsigned();
            $table->tinyInteger('disposition')->unsigned();
            $table->text('reason')->nullable();
            $table->text('dkim_auth')->nullable();
            $table->text('spf_auth')->nullable();
            $table->tinyInteger('dkim_align')->unsigned();
            $table->tinyInteger('spf_align')->unsigned();
            $table->string('envelope_to')->nullable();
            $table->string('envelope_from')->nullable();
            $table->string('header_from')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('report_records');
    }
};
