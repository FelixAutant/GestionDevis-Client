<?php

namespace App\Service;

use App\Entity\Quote;
use Dompdf\Dompdf;
use Dompdf\Options;
use Twig\Environment;

final class QuotePdfGenerator
{
    public function __construct(private readonly Environment $twig)
    {
    }

    /**
     * Rend le devis en PDF (binaire).
     */
    public function render(Quote $quote): string
    {
        $html = $this->twig->render('quote/pdf.html.twig', [
            'quote' => $quote,
            'totals' => $quote->getTotals(),
        ]);

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans'); // gère accents et symbole €
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->setPaper('A4');
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->render();

        return (string) $dompdf->output();
    }

    public function filename(Quote $quote): string
    {
        return \sprintf('devis-%s.pdf', strtolower((string) $quote->getNumber()));
    }
}
