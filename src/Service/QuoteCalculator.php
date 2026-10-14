<?php

namespace App\Service;

use App\Entity\QuoteLine;
use App\Model\QuoteTotals;

/**
 * Calcul des totaux d'un devis en arithmétique entière (centimes).
 *
 * La TVA est calculée sur la base HT cumulée de chaque taux, puis arrondie une
 * seule fois par taux : c'est la méthode attendue sur une facture/un devis, et
 * elle évite l'accumulation d'écarts d'arrondi ligne par ligne.
 */
final class QuoteCalculator
{
    /**
     * @param iterable<QuoteLine> $lines
     */
    public static function compute(iterable $lines): QuoteTotals
    {
        /** @var array<int, int> $bases */
        $bases = [];
        foreach ($lines as $line) {
            $bases[$line->getVatRate()] = ($bases[$line->getVatRate()] ?? 0) + $line->getTotalExcludingTax();
        }
        ksort($bases);

        $breakdown = [];
        $totalHt = 0;
        $totalVat = 0;
        foreach ($bases as $rate => $base) {
            $vat = intdiv($base * $rate + 5000, 10000);
            $breakdown[$rate] = ['base' => $base, 'vat' => $vat];
            $totalHt += $base;
            $totalVat += $vat;
        }

        return new QuoteTotals($totalHt, $totalVat, $totalHt + $totalVat, $breakdown);
    }
}
