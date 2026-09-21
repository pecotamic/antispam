<?php

namespace Tests\Unit;

use Tests\TestCase;

class InvalidPatternCrashTest extends TestCase
{
    public function test_an_invalid_pattern_crashes_instead_of_being_ignored(): void
    {
        config()->set('pecotamic.antispam.rules.patterns.expressions', ['no-delimiters']);

        $this->expectException(\Throwable::class);

        $this->antispam()->rejects($this->submission(['message' => 'hi']));
    }
}
