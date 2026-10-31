<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\Reimbursement;
use App\Services\SubmissionAttachment;
use Illuminate\Http\Request;

class SubmissionAttachmentController extends Controller
{
    public function show(Request $request, string $kind, int $id, SubmissionAttachment $attachments)
    {
        $model = match ($kind) {
            'permission' => Permission::class,
            'reimbursement' => Reimbursement::class,
            default => abort(404),
        };
        $query = $model::query();
        if (! $request->is('api/*')) {
            $query->visibleTo($request->user());
        } elseif (! in_array($request->user()->role, ['admin', 'super admin', 'hrd'], true)) {
            $query->where('user_id', $request->user()->id);
        }
        $row = $query->findOrFail($id);
        abort_unless($row->image, 404);

        return $attachments->response($row->image);
    }
}
