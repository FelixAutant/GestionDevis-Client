<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

final class AppExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('money', $this->money(...)),
            new TwigFilter('quantity', $this->quantity(...)),
            new TwigFilter('vat_rate', $this->vatRate(...)),
        ];
    }

    /** Centimes → « 1 234,56 € ». */
    public function money(int $cents): string
    {
        $formatter = new \NumberFormatter('fr_FR', \NumberFormatter::CURRENCY);

        return (string) $formatter->formatCurrency($cents / 100, 'EUR');
    }

    /** Centièmes → « 1,5 ». */
    public function quantity(int $hundredths): string
    {
        $formatter = new \NumberFormatter('fr_FR', \NumberFormatter::DECIMAL);
        $formatter->setAttribute(\NumberFormatter::MIN_FRACTION_DIGITS, 0);
        $formatter->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, 2);

        return (string) $formatter->format($hundredths / 100);
    }

    /** Points de base → « 5,5 % ». */
    public function vatRate(int $basisPoints): string
    {
        $formatter = new \NumberFormatter('fr_FR', \NumberFormatter::DECIMAL);
        $formatter->setAttribute(\NumberFormatter::MIN_FRACTION_DIGITS, 0);
        $formatter->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, 2);

        return $formatter->format($basisPoints / 100)."\u{202F}%";
    }
}
