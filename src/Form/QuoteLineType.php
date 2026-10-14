<?php

namespace App\Form;

use App\Entity\QuoteLine;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<QuoteLine>
 */
final class QuoteLineType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('description', TextType::class, ['label' => 'Désignation'])
            // La quantité est stockée en centièmes : MoneyType sans devise fait la conversion.
            ->add('quantity', MoneyType::class, [
                'label' => 'Qté',
                'currency' => false,
                'divisor' => 100,
                'input' => 'integer',
                'html5' => true,
                'attr' => ['step' => '0.01', 'min' => '0.01', 'data-role' => 'quantity'],
            ])
            ->add('unit', TextType::class, ['label' => 'Unité', 'required' => false, 'attr' => ['placeholder' => 'h, m², u…']])
            ->add('unitPrice', MoneyType::class, [
                'label' => 'PU HT (€)',
                'currency' => false,
                'divisor' => 100,
                'input' => 'integer',
                'html5' => true,
                'attr' => ['step' => '0.01', 'min' => '0', 'data-role' => 'unit-price'],
            ])
            ->add('vatRate', ChoiceType::class, [
                'label' => 'TVA',
                'choices' => ['20 %' => 2000, '10 %' => 1000, '5,5 %' => 550, '0 %' => 0],
                'attr' => ['data-role' => 'vat-rate'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => QuoteLine::class]);
    }
}
