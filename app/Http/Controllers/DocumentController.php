<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentController extends ApiController
{
    /**
     * Display a document listing
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function index(): JsonResponse
    {
        $this->authorize('index', [Document::class]);

        return $this->success(Document::findAll(), null, 'document');
    }

    /**
     * Store a document
     *
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function store(): JsonResponse
    {
        $this->authorize('store', [Document::class]);
        $personId = $this->user->id;
        $document = new Document;
        $this->fromRest($document);
        $document->person_create_id = $personId;
        $document->person_update_id = $personId;

        if (!$document->save()) {
            return $this->restError($document);
        }

        return $this->success($document);
    }

    /**
     * Display a document
     *
     * @param Document $document
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function show(Document $document): JsonResponse
    {
        $this->authorize('show', $document);
        return $this->success($document);
    }

    /**
     * Update a document
     *
     * @param Document $document
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function update(Document $document): JsonResponse
    {
        $this->authorize('update', $document);
        $this->fromRest($document);
        if ($document->isDirty()) {
            $document->person_update_id = $this->user->id;
        }

        if (!$document->save()) {
            return $this->restError($document);
        }

        return $this->success($document);
    }

    /**
     * Remove the document
     *
     * @param Document $document
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function destroy(Document $document): JsonResponse
    {
        $this->authorize('destroy', $document);
        $document->delete();
        return $this->restDeleteSuccess();
    }
}
