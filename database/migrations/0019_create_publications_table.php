<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('publications', function (Blueprint $table) {
            $table->id();
            $table->text('title');
            $table->text('excerpt')->nullable();
            $table->mediumText('description')->nullable();
            $table->date('publication_date');
            $table->enum('type', [
                'Journal Article','Conference Paper','Book Chapter','Patent','Review','Thesis',
                'Report','Case study','Newspaper article','Position paper','Dataset','Other'
            ]);
            $table->string('link_url', 1024);
            $table->text('keywords')->nullable();
            $table->timestamps();
            $table->softDeletes(); 
        });
    }

    public function down(): void {
        Schema::dropIfExists('publications');
    }
};
