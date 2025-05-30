<?php

namespace App\Http\Controllers;

use App\Lib\Agreements;
use App\Models\Document;
use App\Models\Person;
use App\Models\PersonEvent;
use App\Models\PersonRole;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;

class AgreementsController extends ApiController
{
    /**
     * Retrieve all documents the person might be eligible to view and sign.
     *
     * @param Person $person
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function index(Person $person): JsonResponse
    {
        $this->authorize('index', [Agreements::class, $person]);
        return response()->json(['agreements' => Agreements::retrieve($person)]);
    }

    /**
     * Show a particular document, and indicate if a signature was provided.
     *
     * @param Person $person
     * @param Document $document
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function show(Person $person, Document $document): JsonResponse
    {
        $this->authorize('show', [Agreements::class, $person]);

        $tag = $document->tag;
        if (!Agreements::canSignDocument($person, $tag, PersonEvent::firstOrNewForPersonYear($person->id, current_year()))) {
            return response()->json(['status' => 'not-available']);
        }

        return response()->json([
            'status' => 'available',
            'tag' => $document->tag,
            'title' => $document->description,
            'text' => $document->body,
            'signature' => Agreements::didSignDocument($person, $tag)
        ]);
    }

    /**
     * Sign the document.
     *
     * @param Person $person
     * @param Document $document
     * @return JsonResponse
     * @throws AuthorizationException
     */

    public function sign(Person $person, Document $document): JsonResponse
    {
        $this->authorize('sign', [Agreements::class, $person]);

        $params = request()->validate([
            'signature' => 'required|boolean'
        ]);

        Agreements::signAgreement($person, $document->tag, $params['signature']);

        if ($document->tag == Document::DEPT_NDA_TAG) {
            PersonRole::clearCache($person->id);
        }

        return $this->success();
    }
}