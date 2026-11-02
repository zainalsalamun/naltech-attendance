<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class SubmissionAttachment
{
    public function save(Model $row, array $data, ?UploadedFile $file, string $kind): Model
    {
        $newImage = null;
        unset($data['image']);
        try {
            return DB::transaction(function () use ($row, $data, $file, $kind, &$newImage) {
                if ($row->exists) {
                    $row = $row->newQuery()->whereKey($row->id)->lockForUpdate()->firstOrFail();
                }
                $oldImage = $row->image;
                $newImage = $this->store($file, $kind);
                $row->fill($data);
                if ($newImage) {
                    $row->image = $newImage;
                }
                $row->save();
                if ($newImage && $oldImage) {
                    DB::afterCommit(function () use ($oldImage) {
                        try {
                            $this->delete($oldImage);
                        } catch (\Throwable) { /* Retain private orphan for maintenance; never invalidate committed upload. */
                        }
                    });
                }

                return $row;
            });
        } catch (\Throwable $exception) {
            $this->delete($newImage);
            throw $exception;
        }
    }

    public function store(?UploadedFile $file, string $kind): ?string
    {
        if (! $file) {
            return null;
        }
        abort_unless(in_array($kind, ['permissions', 'reimbursements'], true), 422);
        $path = $file->store($kind, 'submission_private');
        abort_unless(is_string($path) && $path !== '', 503, 'Lampiran belum dapat disimpan. Coba lagi.');

        return 'private:'.$path;
    }

    public function privatePath(?string $image): ?string
    {
        return is_string($image) && preg_match('/^private:(permissions|reimbursements)\/[a-zA-Z0-9_-]+\.(jpg|jpeg|png|gif)$/D', $image)
            ? substr($image, 8) : null;
    }

    public function delete(?string $image): void
    {
        if ($path = $this->privatePath($image)) {
            Storage::disk('submission_private')->delete([$path, $path.'.sha256']);
        }
    }

    public function response(?string $image)
    {
        $path = $this->privatePath($image);
        abort_unless($path, 409, 'Lampiran lama menunggu pemindahan privat oleh pengelola.');
        $disk = Storage::disk('submission_private');
        abort_unless($disk->exists($path), 404);
        abort_unless(in_array($disk->mimeType($path), ['image/jpeg', 'image/png', 'image/gif'], true), 404);

        return $disk->response($path, 'bukti.'.pathinfo($path, PATHINFO_EXTENSION), [
            'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]);
    }
}
