<?php

namespace Tests\Unit;

use App\Domains\Evaluation\Enums\QuestionType;
use Tests\TestCase;

class QuestionTypeEnumTest extends TestCase
{
    public function test_has_17_cases(): void
    {
        $this->assertCount(17, QuestionType::cases());
    }

    public function test_values_returns_all_string_values(): void
    {
        $values = QuestionType::values();

        $this->assertContains('boolean', $values);
        $this->assertContains('select_one', $values);
        $this->assertContains('select_multiple', $values);
        $this->assertContains('slider', $values);
        $this->assertContains('map', $values);
        $this->assertCount(17, $values);
    }

    public function test_from_known_value(): void
    {
        $this->assertSame(QuestionType::Boolean, QuestionType::from('boolean'));
        $this->assertSame(QuestionType::SelectOne, QuestionType::from('select_one'));
        $this->assertSame(QuestionType::SelectMultiple, QuestionType::from('select_multiple'));
    }

    public function test_try_from_unknown_value_returns_null(): void
    {
        $this->assertNull(QuestionType::tryFrom('unknown_type'));
        $this->assertNull(QuestionType::tryFrom(''));
        $this->assertNull(QuestionType::tryFrom('Boolean')); // case-sensitive
    }

    public function test_has_options_is_true_for_choice_types(): void
    {
        $this->assertTrue(QuestionType::SelectOne->hasOptions());
        $this->assertTrue(QuestionType::SelectMultiple->hasOptions());
        $this->assertTrue(QuestionType::Radio->hasOptions());
        $this->assertTrue(QuestionType::Checkbox->hasOptions());
    }

    public function test_has_options_is_false_for_non_choice_types(): void
    {
        $this->assertFalse(QuestionType::Boolean->hasOptions());
        $this->assertFalse(QuestionType::Text->hasOptions());
        $this->assertFalse(QuestionType::Number->hasOptions());
        $this->assertFalse(QuestionType::Slider->hasOptions());
        $this->assertFalse(QuestionType::Date->hasOptions());
    }

    public function test_is_input_false_for_structural_types(): void
    {
        $this->assertFalse(QuestionType::Info->isInput());
        $this->assertFalse(QuestionType::Hidden->isInput());
        $this->assertFalse(QuestionType::Custom->isInput());
        $this->assertFalse(QuestionType::Map->isInput());
        $this->assertFalse(QuestionType::Dynamic->isInput());
    }

    public function test_is_input_true_for_data_collection_types(): void
    {
        $this->assertTrue(QuestionType::Boolean->isInput());
        $this->assertTrue(QuestionType::Text->isInput());
        $this->assertTrue(QuestionType::Number->isInput());
        $this->assertTrue(QuestionType::Slider->isInput());
        $this->assertTrue(QuestionType::File->isInput());
    }
}
