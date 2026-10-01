<?php

declare(strict_types=1);

namespace App\Validator\BandSpace;

/**
 * The ceiling on every finance amount (#1045), in cents: 100 000 000,00 €.
 *
 * Enforced by the API, not only by the interface: the columns are BIGINT, and a value past PHP's
 * integer range would come back from the database as a string and break every `int` property.
 */
final class FinanceAmountLimit
{
    public const int MAX_CENTS = 10_000_000_000;
    public const string MESSAGE = 'Le montant ne peut pas dépasser 100 000 000 €';
}
