<?php

namespace App\Lib\BulkUpload\Handlers;

use App\Lib\BulkUpload\Record;
use App\Models\PersonTeamLog;
use App\Models\Team;
use Carbon\Carbon;
use Carbon\Exceptions\InvalidFormatException;

class TeamMembershipHandler implements Handler
{
    public function process(array $records, string $action, bool $commit, string $reason): void
    {
        $teamNames = [];
        foreach ($records as $record) {
            if (!$record->person || empty($record->data[0])) {
                continue;
            }
            $teamNames[] = trim($record->data[0]);
        }

        $teamsByTitle = Team::whereIn('title', array_unique($teamNames))
            ->get()
            ->keyBy('title');

        foreach ($records as $record) {
            $person = $record->person;
            if (!$person) {
                continue;
            }

            $data = $record->data;
            if (empty($data)) {
                $record->fail('missing team membership info');
                continue;
            }

            $teamName = trim($data[0]);
            if (empty($teamName)) {
                $record->fail('missing team name');
                continue;
            }

            $team = $teamsByTitle->get($teamName);
            if (!$team) {
                $record->fail("Team '$teamName' not found");
                continue;
            }

            if (empty($data[1])) {
                $record->fail('missing joined on date');
                continue;
            }

            try {
                $joinedOn = Carbon::parse($data[1]);
            } catch (InvalidFormatException) {
                $record->fail('Invalid joined on date');
                continue;
            }

            $leftOn = null;
            if (!empty($data[2])) {
                try {
                    $leftOn = Carbon::parse($data[2]);
                } catch (InvalidFormatException) {
                    $record->fail('Invalid left on date');
                    continue;
                }
            }

            if ($commit) {
                $history = new PersonTeamLog([
                    'person_id' => $person->id,
                    'team_id' => $team->id,
                    'joined_on' => $joinedOn,
                    'left_on' => $leftOn,
                ]);
                $history->save();
            }

            $record->succeed();
        }
    }
}
