<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Position;
use App\Models\Role;
use App\Models\Team;
use App\Models\TeamManager;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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
        $params = request()->validate([
            'tag' => 'sometimes|string'
        ]);

        $tag = $params['tag'] ?? null;
        if ($tag) {
            // Support hack for ember-data queryRecord().
            $document = Document::where('tag', $tag)->firstOrFail();
            $this->authorize('show', $document);
            return $this->success($document);
        }

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
        $personId = $this->user->id;

        $document = new Document;
        $this->fromRest($document);

        $entity = null;
        if (!empty($document->resource_type)) {
            // Authorization happens thru entity retrieval
            $entity = $this->retrieveResourceEntity($document->resource_type, $document->resource_entity_id);
            $document->tag = $entity->buildResourceTag();
            $document->description = $entity->buildResourceTitle();
        } else {
            $this->authorize('store', [Document::class]);
        }

        $document->person_create_id = $personId;
        $document->person_update_id = $personId;

        if (!$document->save()) {
            return $this->restError($document);
        }

        if ($entity) {
            $entity->resource_tag = $document->tag;
            $entity->auditReason = "Resource document creation";
            $entity->saveWithoutValidation();
        }

        return $this->success($document);
    }

    /**
     * Retrieve or setup to create a team or team's position resource document
     */

    public function resourceEdit(): JsonResponse
    {
        $params = request()->validate([
            'resource_type' => [
                'required',
                Rule::in(['team', 'position'])
            ],
            'resource_entity_id' => 'required|integer',
        ]);

        $type = $params['resource_type'];
        $entityId = $params['resource_entity_id'];

        $entity = $this->retrieveResourceEntity($type, $entityId);
        $tag = $entity->resource_tag;

        if (empty($tag)) {
            $tag = $entity->buildResourceTag();
            $document = null;
        } else {
            $document = Document::findIdOrTag($tag);
        }

        if (!$document) {
            $document = new Document;
            $document->description = $entity->buildResourceTitle();
            $document->tag = $tag;
        }

        // Pseudo-columns
        $document->resource_type = $type;
        $document->resource_entity_id = $entity->id;
        
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
     * Show a bookmark document. Does not require authorization. Only
     * allowed to show tags beginning with 'bookmark-'.
     *
     * @param string $id
     * @return JsonResponse
     */

    public function bookmark(string $id): JsonResponse
    {
        $document = Document::findIdOrTagOrFail("bookmark-{$id}");

        return response()->json([
            'content' => $document->body,
            'updated_at' => (string)($document->updated_at ?? $document->created_at),
            'refresh_time' => $document->refresh_time,
        ]);
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
        if (!empty($document->resource_type)) {
            $entity = $this->retrieveResourceEntity($document->resource_type, $document->resource_entity_id);
        } else {
            $this->authorize('update', $document);
            $entity = null;
        }

        $this->fromRest($document);
        if ($document->isDirty()) {
            $document->person_update_id = $this->user->id;
        }

        if (!$document->save()) {
            return $this->restError($document);
        }

        if ($entity) {
            $entity->resource_tag = $document->tag;
            $entity->auditReason = "Resource document update sync";
            $entity->saveWithoutValidation();
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

    public function resourceDelete(): JsonResponse
    {
        $params = request()->validate([
            'resource_type' => ['required', Rule::in(['team', 'position'])],
            'resource_entity_id' => 'required|integer',
        ]);

        $type = $params['resource_type'];
        $entityId = $params['resource_entity_id'];

        $entity = $this->retrieveResourceEntity($type, $entityId);

        $tag = $entity->resource_tag;

        if (empty($tag)) {
            throw ValidationException::withMessages(["The $type does not have a resource tag."]);
        }

        $document = Document::findIdOrTag($tag);
        if (!$document) {
            throw (new ModelNotFoundException)->setModel(Document::class, [$tag]);
        }

        $document->delete();
        $entity->resource_tag = null;
        $entity->auditReason = "Resource document deletion";
        $entity->saveWithoutValidation();

        return $this->success();
    }

    private function retrieveResourceEntity($type, $id): Model
    {
        if ($type === 'position') {
            $entity = Position::find($id);
        } else {
            $entity = Team::find($id);
        }

        if (!$entity) {
            throw (new ModelNotFoundException)->setModel($type == 'team' ? Team::class : Position::class);
        }

        // Determine if the person is a team manager  either for the team itself or for the team's position.

        if ($type === 'position') {
            $team = $entity->team;
            // In-Person trainers are special case since the training position is not associated with any team.
            if (!$team && $entity->id != Position::TRAINING) {
                throw new AuthorizationException('Position does not have an associated team.');
            }

            if (!$this->userHasRole([Role::TRAINER_RESOURCE_MANAGEMENT_BASE | $entity->id, Role::ADMIN])) {
                throw new AuthorizationException('You are not authorized to manage this position\'s document.');
            }
        } else {
            if (!TeamManager::isManager($entity->id, $this->user->id) && !$this->userHasRole(Role::ADMIN)) {
                throw new AuthorizationException('You are not this team\'s manager.');
            }

            if (!$this->userHasRole([Role::TEAM_RESOURCE_MANAGEMENT, Role::ADMIN])) {
                throw new AuthorizationException('You are not authorized to manage the team resource document.');
            }
        }

        return $entity;
    }
}
