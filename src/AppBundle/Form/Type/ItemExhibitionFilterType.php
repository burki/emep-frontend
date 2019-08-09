<?php
// ItemExhibitionFilterType.php

namespace AppBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;

use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

class ItemExhibitionFilterType
extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('type', ChoiceType::class, [
            'choices' => [ '- all - ' => '' ] + $options['data']['choices']['itemexhibition_type'],
            'multiple' => true,
            'required' => false,
            'label' => 'Type of Work',
            'attr' => [
                'data-placeholder' => '- all - ',
                'class' => 'select2',
            ],
        ]);

        $builder->add('forsale', ChoiceType::class, [
            'label' => 'For Sale',
            // 'multiple' => true,
            'choices' => [
                '- all - ' => '',
                'yes' => 'Y',
                'no' => 'N',
            ],
            /*
            'attr' => [
                'data-placeholder' => '- all - ',
            ],
            */
            'required' => false,
        ]);

        $builder->add('price_available', ChoiceType::class, [
            'label' => 'Price available',
            // 'multiple' => true,
            'choices' => [
                '- all - ' => '',
                'yes' => 'Y',
            ],
            /*
            'attr' => [
                'data-placeholder' => '- all - ',
            ],
            */
            'required' => false,
        ]);

        $builder->add('owner_available', ChoiceType::class, [
            'label' => 'Owner available',
            // 'multiple' => true,
            'choices' => [
                '- all - ' => '',
                'yes' => 'Y',
            ],
            /*
            'attr' => [
                'data-placeholder' => '- all - ',
            ],
            */
            'required' => false,
        ]);
    }

    public function getBlockPrefix()
    {
        return 'exhibition';
    }
}
