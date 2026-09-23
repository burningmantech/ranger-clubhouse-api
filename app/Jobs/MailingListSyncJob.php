<?php

namespace App\Jobs;

use App\Lib\GoogleGroups;
use App\Mail\MailingListChangesMail;
use App\Models\ActionLog;
use App\Models\ErrorLog;
use App\Models\Person;
use Google\Service\Exception as GoogleServiceException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class MailingListSyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    const string ADD = 'add';
    const string REMOVE = 'remove';

    /**
     * Create a new job instance.
     *
     * @param Person $person
     * @param string $reason why the changes are being made (e.g., "email changed")
     * @param array $operations list of ['action' => 'add'|'remove', 'group' => group email, 'email' => member email]
     * @param int|null $userId who initiated the change
     */

    public function __construct(public Person $person,
                                public string $reason,
                                public array  $operations,
                                public ?int   $userId = null)
    {
    }

    /**
     * Apply the Google Groups membership changes, and send a confirmation email summarizing the results.
     * Each operation is independent - a failure is reported in the confirmation email and does not stop the others.
     */

    public function handle(GoogleGroups $groups): void
    {
        $results = [];
        foreach ($this->operations as $op) {
            try {
                $status = $op['action'] == self::ADD
                    ? $groups->addMember($op['group'], $op['email'])
                    : $groups->removeMember($op['group'], $op['email']);
                $results[] = [...$op, 'success' => true, 'status' => $status];
            } catch (Throwable $e) {
                ErrorLog::recordException($e, 'mailing-list-sync-exception', [
                    'person_id' => $this->person->id,
                    ...$op
                ]);
                $results[] = [...$op, 'success' => false, 'status' => self::errorMessage($e)];
            }
        }

        ActionLog::record($this->userId, 'mailing-list-sync', $this->reason, ['results' => $results], $this->person->id);

        if (empty(setting('MailingListUpdateRequestEmail'))) {
            ErrorLog::record('mailing-list-sync-exception', [
                'message' => 'MailingListUpdateRequestEmail is not set',
                'person_id' => $this->person->id,
                'results' => $results,
            ]);
            return;
        }

        mail_send(new MailingListChangesMail($this->person, $this->reason, $results));
    }

    /**
     * A Google API exception's message is the raw JSON response - pull out the human-readable part.
     *
     * @param Throwable $e
     * @return string
     */

    private static function errorMessage(Throwable $e): string
    {
        if ($e instanceof GoogleServiceException) {
            $message = $e->getErrors()[0]['message'] ?? null;
            if ($message) {
                return "{$message} ({$e->getCode()})";
            }
        }

        return $e->getMessage();
    }
}
