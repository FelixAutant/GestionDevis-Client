<?php

namespace App\DataFixtures;

use App\Entity\Client;
use App\Entity\Quote;
use App\Entity\QuoteLine;
use App\Entity\User;
use App\Enum\QuoteStatus;
use App\Service\QuoteNumberGenerator;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Jeu de données de démonstration (compte : demo@gestiondevis.test / demo-password).
 * Un second artisan est créé pour tester l'isolation des données.
 */
final class AppFixtures extends Fixture
{
    public const DEMO_EMAIL = 'demo@gestiondevis.test';
    public const DEMO_PASSWORD = 'demo-password';
    public const OTHER_EMAIL = 'autre@gestiondevis.test';

    /** @var array<string, array<int, int>> compteur de devis par artisan et par année */
    private array $sequences = [];

    public function __construct(private readonly UserPasswordHasherInterface $hasher)
    {
    }

    public function load(ObjectManager $manager): void
    {
        $demo = $this->createUser($manager, self::DEMO_EMAIL, 'Dupont Rénovation', '12345678901234', "12 rue des Artisans\n64600 Anglet", '05 59 00 00 00');
        $other = $this->createUser($manager, self::OTHER_EMAIL, 'Martin Plomberie', null, null, null);

        $clients = [];
        foreach ([
            ['Famille Lefèvre', 'lefevre@example.com', '06 11 22 33 44', '8 allée des Pins', '64200', 'Biarritz'],
            ['SCI Les Cèdres', 'contact@sci-cedres.example', '05 59 11 22 33', '25 avenue de la Mer', '64100', 'Bayonne'],
            ['Boulangerie Etcheverry', 'boulangerie@example.com', '05 59 44 55 66', '3 place du Marché', '64600', 'Anglet'],
        ] as [$name, $email, $phone, $address, $zip, $city]) {
            $clients[] = (new Client())->setOwner($demo)->setName($name)->setEmail($email)->setPhone($phone)
                ->setAddress($address)->setPostalCode($zip)->setCity($city);
        }
        $foreign = (new Client())->setOwner($other)->setName('Client de Martin')->setCity('Pau');
        foreach ([...$clients, $foreign] as $client) {
            $manager->persist($client);
        }

        $this->sequences = [];

        $this->createQuote($manager, $demo, $clients[0], 'Rénovation salle de bain', QuoteStatus::Accepted, 'first day of this month -2 months', [
            ['Dépose de l\'existant et évacuation', 100, 'forfait', 45000, 1000],
            ['Pose de carrelage sol et murs', 1250, 'm²', 6500, 1000],
            ['Fourniture et pose meuble vasque', 100, 'u', 89000, 2000],
        ]);
        $this->createQuote($manager, $demo, $clients[1], 'Peinture cage d\'escalier', QuoteStatus::Sent, 'today -10 days', [
            ['Préparation des supports', 800, 'h', 4200, 2000],
            ['Peinture murs et plafonds (2 couches)', 6500, 'm²', 1850, 2000],
        ]);
        $this->createQuote($manager, $demo, $clients[2], 'Remplacement vitrine', QuoteStatus::Draft, 'today', [
            ['Dépose vitrine existante', 100, 'forfait', 32000, 2000],
            ['Fourniture et pose vitrine aluminium', 100, 'forfait', 245000, 2000],
        ]);
        $this->createQuote($manager, $demo, $clients[0], 'Terrasse bois', QuoteStatus::Rejected, 'today -45 days', [
            ['Terrasse en pin traité', 2400, 'm²', 9500, 1000],
        ]);

        $this->createQuote($manager, $other, $foreign, 'Chauffe-eau', QuoteStatus::Draft, 'today', [
            ['Remplacement chauffe-eau 200 L', 100, 'u', 120000, 1000],
        ]);

        $manager->flush();
    }

    /**
     * Numérotation par artisan et par année, comme le fait QuoteNumberGenerator.
     *
     * @param list<array{string, int, ?string, int, int}> $lines description, quantité, unité, prix unitaire, TVA
     */
    private function createQuote(ObjectManager $manager, User $owner, Client $client, string $title, QuoteStatus $status, string $date, array $lines): void
    {
        $quote = (new Quote())->setOwner($owner)->setClient($client)->setTitle($title)->setStatus($status);
        $issue = new \DateTimeImmutable($date);
        $quote->setIssueDate($issue)->setValidUntil($issue->modify('+30 days'));
        $year = (int) $issue->format('Y');
        $key = $owner->getUserIdentifier();
        $sequence = $this->sequences[$key][$year] = ($this->sequences[$key][$year] ?? 0) + 1;
        $quote->setNumber(\sprintf('%s-%d-%04d', QuoteNumberGenerator::PREFIX, $year, $sequence));
        foreach ($lines as [$desc, $qty, $unit, $price, $vat]) {
            $quote->addLine((new QuoteLine())->setDescription($desc)->setQuantity($qty)->setUnit($unit)->setUnitPrice($price)->setVatRate($vat));
        }
        $manager->persist($quote);
    }

    private function createUser(ObjectManager $manager, string $email, string $company, ?string $siret, ?string $address, ?string $phone): User
    {
        $user = (new User())->setEmail($email)->setCompanyName($company)->setSiret($siret)->setAddress($address)->setPhone($phone);
        $user->setPassword($this->hasher->hashPassword($user, self::DEMO_PASSWORD));
        $manager->persist($user);

        return $user;
    }
}
