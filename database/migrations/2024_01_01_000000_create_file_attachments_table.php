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
            
            // Attachable (polymorphic) - explicitly defined to avoid naming conflicts
            $table->unsignedBigInteger('attachable_id');
            $table->string('attachable_type');
            
            $table->string('collection')->default('default');
            $table->string('document_type')->nullable();
            $table->string('filename');
            $table->string('original_filename');
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->string('document_number')->nullable();
            $table->date('issue_date')->nullable();
            $table->date('expiry_date')->nullable();
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

            // Explicit indexes with globally unique names
            $table->index(['attachable_type', 'attachable_id'], 'file_attachments_attachable_morph_idx');
            $table->index('collection', 'file_attachments_collection_idx');
            $table->index('document_type', 'file_attachments_document_type_idx');
            $table->index('document_number', 'file_attachments_document_number_idx');
            $table->index('issue_date', 'file_attachments_issue_date_idx');
            $table->index('expiry_date', 'file_attachments_expiry_date_idx');
            $table->index('mime_type', 'file_attachments_mime_type_idx');
            $table->index('created_at', 'file_attachments_created_at_idx');
            $table->index('file_hash', 'file_attachments_file_hash_idx');
            $table->index(['uploaded_by', 'uploaded_by_type'], 'file_attachments_uploaded_by_morph_idx');
            $table->index('upload_ip_address', 'file_attachments_upload_ip_idx');
            $table->index('filename', 'file_attachments_filename_idx');
            $table->index('disk', 'file_attachments_disk_idx');
            $table->index('size', 'file_attachments_size_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('file_attachments');
    }
};
