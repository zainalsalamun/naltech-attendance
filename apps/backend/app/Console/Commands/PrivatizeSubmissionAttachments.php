<?php

namespace App\Console\Commands;

use App\Models\Permission;
use App\Models\Reimbursement;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PrivatizeSubmissionAttachments extends Command
{
    protected $signature = 'submissions:privatize-attachments {--apply : Copy, verify, update references and remove public copies}';

    protected $description = 'Preview or move legacy submission proofs into private storage (run during maintenance)';

    public function handle(): int
    {
        $public = Storage::disk('public');
        $private = Storage::disk('submission_private');
        $groups = [];
        $invalid = 0;
        foreach ([Permission::class => 'permissions', Reimbursement::class => 'reimbursements'] as $model => $kind) {
            foreach ($model::withoutGlobalScopes()->whereNotNull('image')->cursor() as $row) {
                if (str_starts_with($row->image, 'private:')) {
                    if (! app(\App\Services\SubmissionAttachment::class)->privatePath($row->image)) {
                        $invalid++;
                    }

                    continue;
                }
                $prefix = $kind === 'permissions' ? 'permissions/' : 'assets/reimbursements/';
                $name = $kind === 'permissions' && ! str_contains($row->image, '/') ? $row->image : substr($row->image, strlen($prefix));
                $source = $prefix.$name;
                if (! preg_match('/^[a-zA-Z0-9_.-]+\.(jpg|jpeg|png|gif)$/D', $name)
                    || ! in_array($row->image, [$name, $source], true)
                    || ($kind !== 'permissions' && $row->image !== $source)) {
                    $invalid++;

                    continue;
                }
                $target = $kind.'/legacy-'.hash('sha256', $source).'.'.strtolower(pathinfo($name, PATHINFO_EXTENSION));
                if ((! $public->exists($source) && ! $private->exists($target))
                    || (! $public->exists($source) && (! $private->exists($target.'.sha256')
                        || trim($private->get($target.'.sha256')) !== hash('sha256', $private->get($target))))
                    || ($public->exists($source) && $public->size($source) > 2 * 1024 * 1024)) {
                    $invalid++;

                    continue;
                }
                $groups[$source]['target'] = $target;
                $groups[$source]['rows'][] = [$model, $row->id, $row->image];
            }
        }
        $this->info('Legacy files: '.count($groups).'; invalid/missing: '.$invalid.'.');
        if ($invalid) {
            $this->error('Resolve invalid/missing references before applying. No changes made.');

            return self::FAILURE;
        }
        if (! $this->option('apply')) {
            $this->info('Preview only. Run --apply during maintenance after backup.');

            return self::SUCCESS;
        }
        foreach ($groups as $source => $group) {
            try {
                $target = $group['target'];
                $bytes = $public->exists($source) ? $public->get($source) : $private->get($target);
                if (! is_string($bytes) || strlen($bytes) > 2 * 1024 * 1024) {
                    throw new \RuntimeException;
                }
                if (! $private->exists($target) && ! $private->put($target, $bytes)) {
                    throw new \RuntimeException;
                }
                if (hash('sha256', $private->get($target)) !== hash('sha256', $bytes)) {
                    throw new \RuntimeException;
                }
                if (! $private->put($target.'.sha256', hash('sha256', $bytes))) {
                    throw new \RuntimeException;
                }
                // Keep verified private bytes even if a later DB commit fails; rerun can recover.
                DB::transaction(function () use ($group, $target, $source, $public) {
                    foreach ($group['rows'] as [$model, $id, $old]) {
                        $row = $model::withoutGlobalScopes()->whereKey($id)->lockForUpdate()->first();
                        if ($row && $row->image === $old) {
                            $row->image = 'private:'.$target;
                            $row->save();
                        } elseif ($row && $row->image !== 'private:'.$target) {
                            throw new \RuntimeException;
                        }
                    }
                    if ($public->exists($source) && ! $public->delete($source)) {
                        throw new \RuntimeException;
                    }
                });
            } catch (\Throwable) {
                $this->error('Transfer incomplete. Verified private copies retained; inspect and rerun during maintenance. No file details logged.');

                return self::FAILURE;
            }
        }
        $this->info('Legacy references moved; verified public copies removed.');

        return self::SUCCESS;
    }
}
