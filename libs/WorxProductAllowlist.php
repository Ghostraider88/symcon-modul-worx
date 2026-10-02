<?php

declare(strict_types=1);

/**
 * Fail-closed product gate for the locally confirmed V1 mower setup.
 */
final class WorxProductAllowlist
{
    public static function matches(array $device, int $allowedProductID): bool
    {
        return $allowedProductID > 0
            && isset($device['product_id'])
            && is_int($device['product_id'])
            && $device['product_id'] === $allowedProductID;
    }
}