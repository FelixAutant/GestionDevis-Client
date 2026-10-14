<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\QuoteRepository;

/**
 * Numérotation séquentielle par artisan et par année : DV-2026-0001, DV-2026-0002…
 * Les devis envoyés ne sont jamais supprimables, ce qui garantit une suite sans trou
 * côté documents émis.
 */
final class QuoteNumberGenerator
{
    public const PREFIX = 'DV';

    public function __construct(private readonly QuoteRepository $quotes)
    {
    }

    public function next(User $owner, \DateTimeImmutable $issueDate): string
    {
        $prefix = \sprintf('%s-%s-', self::PREFIX, $issueDate->format('Y'));
        $last = $this->quotes->findLastNumber($owner, $prefix);
        $sequence = null === $last ? 1 : ((int) substr($last, \strlen($prefix))) + 1;

        return $prefix.str_pad((string) $sequence, 4, '0', \STR_PAD_LEFT);
    }
}
