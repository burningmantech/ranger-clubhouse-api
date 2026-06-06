<?php

namespace App\Lib;

use App\Models\PersonMessage;
use Illuminate\Database\Eloquent\Collection;

/**
 * Builds, enriches, and sorts the message threads displayed for a person. Extracted
 * from PersonMessage::findForPerson so the eager-load definitions, recency
 * enrichment, and sort live in one readable place.
 */
class PersonMessageThreadQuery
{
    /**
     * Relations (and their selected columns) loaded for both the root message and its
     * replies. Defined once so the two lists cannot drift apart.
     *
     * @var array<int, string>
     */
    private const array RELATION_COLUMNS = [
        'person:id,callsign',
        'creator_person:id,callsign',
        'creator_position:id,title',
        'sender_team:id,title,email',
        'sender_person:id,callsign',
    ];

    /**
     * Find all message threads for a person, enriched with recency state and sorted
     * unread-first then newest-first.
     *
     * @param int $personId
     * @return Collection
     */

    public function forPerson(int $personId): Collection
    {
        $rows = PersonMessage::where(function ($w) use ($personId) {
            $w->where('person_id', $personId)
                ->orWhere('sender_person_id', $personId);
        })->whereNull('reply_to_id')
            ->with($this->eagerLoads())
            ->orderBy('person_message.created_at', 'desc')
            ->get();

        foreach ($rows as $row) {
            $this->computeRecency($row, $personId);
        }

        return $rows->sort(function (PersonMessage $a, PersonMessage $b) {
            if ($a->recentDelivered !== $b->recentDelivered) {
                return $a->recentDelivered <=> $b->recentDelivered;
            }

            return $b->recentTimestamp <=> $a->recentTimestamp;
        })->values();
    }

    /**
     * Build the eager-load list for the root rows and their replies.
     *
     * @return array<int, string>
     */

    private function eagerLoads(): array
    {
        $loads = self::RELATION_COLUMNS;
        $loads[] = 'replies';
        foreach (self::RELATION_COLUMNS as $relation) {
            $loads[] = 'replies.' . $relation;
        }

        return $loads;
    }

    /**
     * Determine the thread's delivered state and most-recent timestamp. Unlike the
     * original implementation, the root message's own unread state is always
     * considered, not only when the thread has no replies.
     *
     * @param PersonMessage $row
     * @param int $personId
     * @return void
     */

    private function computeRecency(PersonMessage $row, int $personId): void
    {
        $rootIsUnreadInbound = $row->person_id == $personId
            && $row->sender_person_id != $personId
            && !$row->delivered;

        $delivered = !$rootIsUnreadInbound;
        $mostRecent = $row->created_at?->timestamp ?? 0;

        foreach ($row->replies as $reply) {
            if ($reply->person_id == $personId && !$reply->delivered) {
                $delivered = false;
            }

            $replyTimestamp = $reply->created_at?->timestamp ?? 0;
            if ($replyTimestamp > $mostRecent) {
                $mostRecent = $replyTimestamp;
            }
        }

        $row->recentDelivered = $delivered;
        $row->recentTimestamp = $mostRecent;
    }
}
