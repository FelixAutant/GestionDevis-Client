<?php

namespace App\Tests\Unit;

use App\Entity\QuoteLine;
use App\Service\QuoteCalculator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class QuoteCalculatorTest extends TestCase
{
    private function line(int $quantity, int $unitPrice, int $vatRate): QuoteLine
    {
        return (new QuoteLine())->setDescription('x')->setQuantity($quantity)->setUnitPrice($unitPrice)->setVatRate($vatRate);
    }

    public function testEmptyQuoteHasZeroTotals(): void
    {
        $totals = QuoteCalculator::compute([]);

        self::assertSame(0, $totals->totalExcludingTax);
        self::assertSame(0, $totals->totalVat);
        self::assertSame(0, $totals->totalIncludingTax);
        self::assertSame([], $totals->vatBreakdown);
    }

    public function testSingleLine(): void
    {
        // 2 × 100,00 € HT à 20 % => 200,00 HT, 40,00 TVA, 240,00 TTC
        $totals = QuoteCalculator::compute([$this->line(200, 10000, 2000)]);

        self::assertSame(20000, $totals->totalExcludingTax);
        self::assertSame(4000, $totals->totalVat);
        self::assertSame(24000, $totals->totalIncludingTax);
    }

    public function testVatIsBrokenDownPerRateAndSortedByRate(): void
    {
        $totals = QuoteCalculator::compute([
            $this->line(100, 10000, 2000),
            $this->line(100, 5000, 550),
            $this->line(100, 20000, 2000),
            $this->line(100, 1000, 0),
        ]);

        self::assertSame([0, 550, 2000], array_keys($totals->vatBreakdown));
        self::assertSame(['base' => 1000, 'vat' => 0], $totals->vatBreakdown[0]);
        self::assertSame(['base' => 5000, 'vat' => 275], $totals->vatBreakdown[550]);
        self::assertSame(['base' => 30000, 'vat' => 6000], $totals->vatBreakdown[2000]);
        self::assertSame(36000, $totals->totalExcludingTax);
        self::assertSame(6275, $totals->totalVat);
        self::assertSame(42275, $totals->totalIncludingTax);
    }

    /**
     * @return iterable<string, array{int, int, int}>
     */
    public static function lineRoundingProvider(): iterable
    {
        yield 'quantité entière' => [300, 1999, 5997];
        yield 'demi-heure' => [50, 4500, 2250];
        yield 'arrondi au centime supérieur (0,5 centime)' => [150, 1, 2];      // 1,5 × 0,01 € = 0,015 € => 0,02 €
        yield 'arrondi au centime inférieur' => [133, 1, 1];                    // 1,33 × 0,01 € = 0,0133 € => 0,01 €
        yield 'gros montant sans dérive flottante' => [1000, 1999, 19990];
    }

    #[DataProvider('lineRoundingProvider')]
    public function testLineTotalIsRoundedToTheCent(int $quantity, int $unitPrice, int $expected): void
    {
        self::assertSame($expected, $this->line($quantity, $unitPrice, 2000)->getTotalExcludingTax());
    }

    public function testVatIsRoundedOncePerRateNotPerLine(): void
    {
        // 3 lignes de 0,03 € HT à 20 % : arrondie ligne par ligne, la TVA ferait 3 × 0,01 € = 0,03 €.
        // Calculée une fois sur la base cumulée (0,09 €), elle vaut 0,018 € => 0,02 €.
        $lines = [$this->line(100, 3, 2000), $this->line(100, 3, 2000), $this->line(100, 3, 2000)];
        $totals = QuoteCalculator::compute($lines);

        self::assertSame(9, $totals->totalExcludingTax);
        self::assertSame(2, $totals->totalVat);
        self::assertSame(11, $totals->totalIncludingTax);
    }
}
