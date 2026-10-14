<?php

namespace App\Model;

/**
 * Totaux d'un devis, en centimes. La TVA est ventilée par taux (en points de base).
 */
final readonly class QuoteTotals
{
    /**
     * @param array<int, array{base: int, vat: int}> $vatBreakdown indexé par taux de TVA en points de base
     */
    public function __construct(
        public int $totalExcludingTax,
        public int $totalVat,
        public int $totalIncludingTax,
        public array $vatBreakdown,
    ) {
    }
}
