<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Http\Requests\StoreAttachmentRequest;
use App\Http\Requests\UpdateAttachmentRequest;
use App\Models\ChangeRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AttachmentController extends Controller
{
    private const TYPES = [
        'pdf' => ['application/pdf'],
        'word' => ['application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        'image' => ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
    ];

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $search = trim($request->string('search')->toString());

        $type = $request->string('type')->toString();
        $type = array_key_exists($type, self::TYPES) ? $type : null;

        $sort = $request->string('sort')->toString();
        $sort = in_array($sort, ['oldest', 'name', 'size'], true) ? $sort : 'newest';

        $morphClass = (new Attachment)->getMorphClass();

        $query = Attachment::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('note', 'like', "%{$search}%")
                        ->orWhereHas('media', fn($q) => $q->where('file_name', 'like', "%{$search}%"))
                        ->orWhereHas('changeRequest', fn($q) => $q->search($search));
                });
            })
            ->when($type, fn($query, $type) => $query->whereHas('media', fn($q) => $q->whereIn('mime_type', self::TYPES[$type])));

        // A one-value lookup in the media table, used to sort attachments by file name or size.
        $fromMedia = fn(string $column) => Media::query()
            ->select($column)
            ->whereColumn('media.model_id', 'attachments.id')
            ->where('media.model_type', $morphClass)
            ->limit(1);

        $attachments = (clone $query)
            ->with([
                'media',
                'changeRequest:id,project_id,title',
                'changeRequest.project:id,company_id,name',
                'changeRequest.project.company:id,client_id,name',
                'changeRequest.project.company.client:id,name',
            ])
            ->tap(fn($query) => match ($sort) {
                'oldest' => $query->oldest()->oldest('id'),
                'name' => $query->orderBy($fromMedia('file_name'))->latest('id'),
                'size' => $query->orderByDesc($fromMedia('size'))->latest('id'),
                default => $query->latest()->latest('id'),
            })
            ->paginate(12)
            ->withQueryString();

        return Inertia::render('attachments/Index', [
            'attachments' => $attachments,
            'filters' => ['search' => $search, 'type' => $type, 'sort' => $sort],
            'summary' => [
                'size' => (int) Media::query()
                    ->where('model_type', $morphClass)
                    ->whereIn('model_id', (clone $query)->select('attachments.id'))
                    ->sum('size'),
            ],
            'typeCounts' => $this->typeCounts($morphClass),
        ]);
    }

    /** How many files of each type exist overall (these counts ignore the filters on purpose). */
    private function typeCounts(string $morphClass): array
    {
        $byMime = Media::query()
            ->where('model_type', $morphClass)
            ->selectRaw('mime_type, COUNT(*) as total')
            ->groupBy('mime_type')
            ->toBase()
            ->pluck('total', 'mime_type');

        $counts = collect(self::TYPES)->map(
            fn(array $mimes) => (int) collect($mimes)->sum(fn(string $mime) => $byMime[$mime] ?? 0),
        );

        return ['all' => $counts->sum(), ...$counts->all()];
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreAttachmentRequest $request, ChangeRequest $changeRequest): RedirectResponse
    {
        DB::transaction(function () use ($request, $changeRequest) {
            $attachment = $changeRequest->attachments()->create([
                'note' => $request->validated('note'),
            ]);

            $attachment->addMediaFromRequest('file')->toMediaCollection('files');
        });

        return back()->with('success', 'Attachment added successfully!');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateAttachmentRequest $request, Attachment $attachment): RedirectResponse
    {
        DB::transaction(function () use ($request, $attachment) {
            $attachment->update(['note' => $request->validated('note')]);

            if ($request->hasFile('file')) {
                // The collection keeps a single file, so adding one replaces the old file.
                $attachment->addMediaFromRequest('file')->toMediaCollection('files');
            }
        });

        return back()->with('success', 'Attachment updated.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Attachment $attachment): RedirectResponse
    {
        $attachment->delete();

        return back()->with('success', 'Attachment deleted successfully!');
    }

    public function file(Attachment $attachment): BinaryFileResponse
    {
        $media = $attachment->getFirstMedia('files');

        abort_if($media === null, 404);

        $inline = str_starts_with($media->mime_type, 'image/') || $media->mime_type === 'application/pdf';

        return response()->download($media->getPath(), $media->file_name, [], $inline ? 'inline' : 'attachment');
    }
}
