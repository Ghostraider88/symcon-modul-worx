<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../libs/WorxProductAllowlist.php';

final class WorxProductAllowlistTest extends TestCase
{
    public function testAllowsOnlyAnExactPositiveIntegerProductId(): void
    {
        self::assertTrue(WorxProductAllowlist::matches(['product_id' => 42], 42));
        self::assertFalse(WorxProductAllowlist::matches(['product_id' => 42], 43));
    }

    public function testEmptyAllowlistFailsClosed(): void
    {
        self::assertFalse(WorxProductAllowlist::matches(['product_id' => 42], 0));
    }

    public function testMissingOrNonIntegerProductIdFailsClosed(): void
    {
        self::assertFalse(WorxProductAllowlist::matches([], 42));
        self::assertFalse(WorxProductAllowlist::matches(['product_id' => '42'], 42));
        self::assertFalse(WorxProductAllowlist::matches(['product_id' => null], 42));
    }
}