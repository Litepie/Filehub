# Migration Guide - Document Fields Update

## New Fields Added

The following fields have been added to the `file_attachments` table to support document metadata:

- `document_type` (string, nullable) - Type of document (e.g., 'letterhead', 'license', 'certificate')
- `title` (string, nullable) - Document title
- `description` (text, nullable) - Document description
- `document_number` (string, nullable) - Document reference/identification number
- `issue_date` (date, nullable) - Document issue date
- `expiry_date` (date, nullable) - Document expiry date

## Running the Migration

To apply the new fields to your database, run:

```bash
php artisan migrate
```

This will execute the migration: `2024_01_02_000000_add_document_fields_to_file_attachments_table.php`

## Usage

### Storing Files with Document Metadata

```php
use Litepie\FileHub\Facades\FileHub;

$attachment = FileHub::attach($model, $uploadedFile, 'documents', [
    'document_type' => 'letterhead',
    'title' => 'Company Letterhead',
    'description' => 'Official company letterhead document',
    'document_number' => 'LH-2024-001',
    'issue_date' => '2024-11-25',
    'expiry_date' => '2025-12-19',
]);
```

### Updating Document Metadata

```php
$attachment = FileAttachment::find($id);
$attachment->update([
    'document_type' => 'letterhead',
    'title' => 'Updated Title',
    'description' => 'Updated description',
    'document_number' => 'DOC-123',
    'issue_date' => '2025-11-25',
    'expiry_date' => '2025-12-19',
]);
```

### API Request Example

When uploading via API, send these fields:

```json
{
  "file": "(binary file data)",
  "document_type": "letterhead",
  "title": "Company Letterhead",
  "description": "Official company document",
  "document_number": "LH-2024-001",
  "issue_date": "2025-11-25",
  "expiry_date": "2025-12-19"
}
```

### API Response

The response will now include these fields:

```json
{
  "id": 1,
  "title": "Company Letterhead",
  "description": "Official company document",
  "document_type": "letterhead",
  "document_number": "LH-2024-001",
  "issue_date": "2025-11-25",
  "expiry_date": "2025-12-19",
  "file_name": "letterhead.pdf",
  "file_size": 46300,
  "file_size_human": "45.21 KB",
  "mime_type": "application/pdf",
  "url": "http://localhost/storage/...",
  "uploaded_at": "2025-11-24T11:36:57+00:00"
}
```

## Backward Compatibility

- All new fields are **nullable**, so existing records will continue to work
- The `document_type` field will fall back to the `collection` value if not explicitly set
- The `title` field will fall back to `original_filename` if not set
- Existing code will continue to work without modifications

## Querying Documents

```php
// Find documents by type
$letterheads = FileAttachment::where('document_type', 'letterhead')->get();

// Find documents by number
$document = FileAttachment::where('document_number', 'LH-2024-001')->first();

// Find expiring documents
$expiring = FileAttachment::where('expiry_date', '<=', now()->addDays(30))->get();

// Find documents issued in a date range
$documents = FileAttachment::whereBetween('issue_date', ['2024-01-01', '2024-12-31'])->get();
```

## Rolling Back

If you need to remove these fields, run:

```bash
php artisan migrate:rollback
```

This will remove all the added fields and their indexes.
