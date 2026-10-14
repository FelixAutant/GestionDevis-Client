<?php

namespace App\Form;

use App\Entity\Client;
use App\Entity\Quote;
use App\Entity\User;
use App\Repository\ClientRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<Quote>
 */
final class QuoteType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var User $owner */
        $owner = $options['owner'];

        $builder
            ->add('client', EntityType::class, [
                'class' => Client::class,
                'choice_label' => 'name',
                'label' => 'Client',
                'placeholder' => 'Choisir un client…',
                // Un artisan ne peut associer un devis qu'à l'un de ses propres clients.
                'query_builder' => static fn (ClientRepository $repository) => $repository->searchQueryBuilder($owner),
            ])
            ->add('title', TextType::class, ['label' => 'Objet du devis', 'required' => false, 'attr' => ['placeholder' => 'Ex. Rénovation salle de bain']])
            ->add('issueDate', DateType::class, ['label' => 'Date du devis', 'widget' => 'single_text', 'input' => 'datetime_immutable'])
            ->add('validUntil', DateType::class, ['label' => 'Valable jusqu\'au', 'widget' => 'single_text', 'input' => 'datetime_immutable'])
            ->add('lines', CollectionType::class, [
                'entry_type' => QuoteLineType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'label' => false,
                'error_bubbling' => false,
            ])
            ->add('notes', TextareaType::class, ['label' => 'Conditions / remarques', 'required' => false, 'attr' => ['rows' => 4]]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Quote::class]);
        $resolver->setRequired('owner');
        $resolver->setAllowedTypes('owner', User::class);
    }
}
