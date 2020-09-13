<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;

class DocumentController extends ApiController
{
    /**
     * Display a document listing
     *
     * @return \Illuminate\Http\JsonResponse
     * @throws AuthorizationException
     */

    public function index()
    {
        $this->authorize('index', [ Document::class ]);

        return $this->success(Document::findAll(), null, 'document');
    }

    /**
     * Store a document
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     * @throws AuthorizationException
     */

    public function store()
    {
        $this->authorize('store', [ Document::class ]);
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
     * @param  \App\Models\Document  $document
     * @return \Illuminate\Http\JsonResponse
     * @throws AuthorizationException
     */

    public function show(Document $document)
    {
        $this->authorize('show', $document);
        return $this->success($document);
    }

    /**
     * Update a document
     *
     * @param  \App\Models\Document  $document
     * @return \Illuminate\Http\JsonResponse
     * @throws AuthorizationException
     */

    public function update(Document $document)
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
     * @param  \App\Models\Document  $document
     * @return \Illuminate\Http\JsonResponse
     * @throws AuthorizationException
     */
    public function destroy(Document $document)
    {
        $this->authorize('destroy', $document);
        $document->delete();
        return $this->restDeleteSuccess();
    }
}
