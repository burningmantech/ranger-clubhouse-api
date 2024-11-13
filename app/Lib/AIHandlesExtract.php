<?php

namespace App\Lib;

use App\Exceptions\UnacceptableConditionException;
use Orhanerday\OpenAi\OpenAi;

class AIHandlesExtract
{
    const string AI_PROMPT = "Identify and extract each name or handle from the following text, and display them in JSON format using the field \"handles\" as an array. Remove any punctuation except dashes, single quotes, or periods from each handle. Apply proper capitalization to each handle, and if a handle is confidently a combination of separate words (and not a single proper word), split it into individual words. List each callsign on a separate line within the array, including only the callsigns in the specified JSON structure, without any extra context or explanations.";

    public static function execute(string $text)
    {
        $token = setting('ChatGPTToken');
        if (empty($token)) {
            throw new UnacceptableConditionException('AI credential token not defined.');
        }

        $ai = new OpenAi($token);

        $result = $ai->chat([
            'model' => 'gpt-4o',
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
        $content = json_decode($chat->choices[0]->message->content);
        return $content->handles;
    }
}