<?php

namespace Litepie\FileHub\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Litepie\FileHub\Models\FileAttachment;

class FileController extends Controller
{
    public function serve(Request $request, FileAttachment $attachment): Response|StreamedResponse
    {
        if (!$attachment->exists()) {
            abort(404, 'File not found');
        }

        $headers = [
            'Content-Type' => $attachment->mime_type,
            'Content-Length' => $attachment->size,
            'Cache-Control' => 'public, max-age=31536000',
            'Expires' => now()->addYear()->toRfc7231String(),
        ];

        // For images, serve directly
        if ($attachment->isImage()) {
            return new StreamedResponse(function () use ($attachment) {
                $stream = $attachment->getStream();
                fpassthru($stream);
                fclose($stream);
            }, 200, $headers);
        }

        // For other files, add content disposition
        $headers['Content-Disposition'] = 'inline; filename="' . $attachment->original_filename . '"';

        return new StreamedResponse(function () use ($attachment) {
            $stream = $attachment->getStream();
            fpassthru($stream);
            fclose($stream);
        }, 200, $headers);
    }

    public function download(Request $request, FileAttachment $attachment): StreamedResponse
    {
        if (!$attachment->exists()) {
            abort(404, 'File not found');
        }

        $headers = [
            'Content-Type' => 'application/octet-stream',
            'Content-Length' => $attachment->size,
            'Content-Disposition' => 'attachment; filename="' . $attachment->original_filename . '"',
        ];

        return new StreamedResponse(function () use ($attachment) {
            $stream = $attachment->getStream();
            fpassthru($stream);
            fclose($stream);
        }, 200, $headers);
    }

    public function variant(Request $request, FileAttachment $attachment, string $variant): Response|StreamedResponse
    {
        if (!$attachment->exists() || !$attachment->hasVariants()) {
            abort(404, 'File or variant not found');
        }

        $variantData = $attachment->getVariant($variant);
        if (!$variantData || !Storage::disk($attachment->disk)->exists($variantData['path'])) {
            abort(404, 'Variant not found');
        }

        $headers = [
            'Content-Type' => $attachment->mime_type,
            'Content-Length' => $variantData['size'],
            'Cache-Control' => 'public, max-age=31536000',
            'Expires' => now()->addYear()->toRfc7231String(),
        ];

        return new StreamedResponse(function () use ($attachment, $variantData) {
            $stream = Storage::disk($attachment->disk)->readStream($variantData['path']);
            fpassthru($stream);
            fclose($stream);
        }, 200, $headers);
    }
}
