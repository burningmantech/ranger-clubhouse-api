<?php

namespace Tests\Unit;

use App\Models\SurveyQuestion;
use Tests\TestCase;

class SurveyQuestionTest extends TestCase
{
    /**
     * When no options are defined, the raw response value is returned unchanged.
     */
    public function test_response_to_option_label_returns_raw_value_when_options_empty(): void
    {
        $question = new SurveyQuestion();
        $question->options = '';

        $this->assertSame('7', $question->responseToOptionLabel('7'));
    }

    /**
     * A valid 1-based index maps to the matching option label.
     */
    public function test_response_to_option_label_returns_label_for_valid_index(): void
    {
        $question = new SurveyQuestion();
        $question->options = "Poor\nOkay\nGreat";

        $this->assertSame('Poor', $question->responseToOptionLabel(1));
        $this->assertSame('Okay', $question->responseToOptionLabel(2));
        $this->assertSame('Great', $question->responseToOptionLabel(3));
    }

    /**
     * Non-positive, non-numeric, and out-of-range values fall back safely instead of
     * indexing a negative or missing offset.
     */
    public function test_response_to_option_label_falls_back_for_invalid_values(): void
    {
        $question = new SurveyQuestion();
        $question->options = "Poor\nOkay\nGreat";

        $this->assertSame('0', $question->responseToOptionLabel(0));
        $this->assertSame('', $question->responseToOptionLabel(''));
        $this->assertSame('abc', $question->responseToOptionLabel('abc'));
        $this->assertSame('99', $question->responseToOptionLabel(99));
    }
}
