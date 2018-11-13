<?php
// ExhibitionFilterType.php

namespace AppBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;

use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

class ExhibitionFilterType
extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('date', YearRangeType::class, [
            'label' => '(Starting) Year of Exhibition',
        ]);

        $builder->add('type', ChoiceType::class, [
            'label' => 'Type',
            // 'multiple' => true,
            'choices' => [ '- all - ' => '' ] + $options['data']['choices']['exhibition_type'],
            /*
            'attr' => [
                'data-placeholder' => '- all - ',
            ],
            */
            'required' => false,
        ]);

        $builder->add('organizer_type', ChoiceType::class, [
            'label' => 'Type of Organizing Body',
            // 'multiple' => true,
            'choices' => [ '- all - ' => '' ] + $options['data']['choices']['exhibition_organizer_type'],
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
