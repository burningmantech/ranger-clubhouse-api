<?php

namespace App\Lib;

use App\Exceptions\UnacceptableConditionException;
use App\Models\ErrorLog;
use Orhanerday\OpenAi\OpenAi;

class AIHandlesExtract
{
    const string AI_PROMPT = <<<__HERE__
Extract all names or handles from the input text and return them as a JSON object under the "handles" field as an array.
For each handle:
- Remove all punctuation except dashes (-), single quotes ('), and periods (.).
- Remove the word "Ranger".
- Apply proper capitalization.
- If the handle is a combination of multiple recognizable words (not a single proper word), split them into separate capitalized words.

Format the output as:
{
  "handles": [
    "First Handle",
    "Second-Handle",
    "Name.O'Neil"
  ]
}

Only return the JSON. Do not include explanations or extra text.
__HERE__;

    /**
     * Extract a list of supplied handles into a machine-readable format.
     *
     * @param string $text
     * @return mixed
     * @throws UnacceptableConditionException
     */

    public static function execute(string $text) : array
    {
        $token = setting('ChatGPTToken');
        if (empty($token)) {
            throw new UnacceptableConditionException('AI credential token not defined.');
        }

        $ai = new OpenAi($token);

        $result = $ai->chat([
            'model' => 'gpt-4o-mini',
            'messages' => [
                [
                    "role" => "user",
                    "content" => self::AI_PROMPT . "\n\n" . $text
                ],
            ],
            'response_format' => ['type' => 'json_object'],
            'temperature' => 0.2,
            'max_tokens' => 4096,
            'frequency_penalty' => 0,
            'presence_penalty' => 0,
        ]);

        $chat = json_decode($result);

        if ($chat->error ?? null) {
            ErrorLog::record('chatgpt-error', [
                'text' => $text,
                'response' => $chat,
            ]);
            throw new UnacceptableConditionException($chat->error->message);
        }

        if (empty($chat->choices[0]->message->content)) {
            ErrorLog::record('chatgpt-malformed-response', [
                'handles' => $text,
                'response' => $chat,
            ]);
            throw new UnacceptableConditionException("ChatGPT returned an unexpected response.");
        }

        $content = json_decode($chat->choices[0]->message->content);

        if (empty($content->handles)) {
            ErrorLog::record('chatgpt-malformed-response', [
                'handles' => $text,
                'response' => $chat,
            ]);
            throw new UnacceptableConditionException("ChatGPT returned an unexpected response.");
        }

        return $content->handles;
    }
}