<?php

namespace App\Tests\Functional;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Twig\Environment;

/**
 * Les pages d'erreur personnalisées ne s'affichent qu'en production (debug désactivé) :
 * on vérifie donc le rendu du gabarit directement.
 */
final class ErrorPageTest extends KernelTestCase
{
    /**
     * @return iterable<string, array{int, string}>
     */
    public static function statusCodes(): iterable
    {
        yield '403' => [403, 'Accès refusé'];
        yield '404' => [404, 'Page introuvable'];
        yield '500' => [500, 'Une erreur est survenue'];
    }

    #[DataProvider('statusCodes')]
    public function testErrorPageShowsAFriendlyMessage(int $statusCode, string $title): void
    {
        $html = static::getContainer()->get(Environment::class)
            ->render('@Twig/Exception/error.html.twig', ['status_code' => $statusCode, 'status_text' => '']);

        self::assertStringContainsString("<h1>$title</h1>", $html);
        self::assertStringContainsString('Retour au tableau de bord', $html);
    }
}
