<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('file_attachments', function (Blueprint $table) {
            $table->id();
            $table->morphs('attachable');
            $table->string('collection')->default('default');
            $table->string('filename');
            $table->string('original_filename');
            $table->string('path');
            $table->string('disk');
            $table->string('mime_type');
            $table->unsignedBigInteger('size');
            $table->json('metadata')->nullable();
            $table->json('variants')->nullable();
            $table->string('file_hash', 64)->nullable();
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->string('uploaded_by_type')->nullable();
            $table->string('upload_ip_address', 45)->nullable();
            $table->text('upload_user_agent')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['attachable_type', 'attachable_id']);
            $table->index(['collection']);
            $table->index(['mime_type']);
            $table->index(['created_at']);
            $table->index(['file_hash']);
            $table->index(['uploaded_by', 'uploaded_by_type']);
            $table->index(['upload_ip_address']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('file_attachments');
    }
};
