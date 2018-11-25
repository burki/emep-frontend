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
            'multiple' => true,
            'required' => false,
            'choices' => $options['data']['choices']['exhibition_type'],
            'label' => 'Type',
            'attr' => [
                'data-placeholder' => '- all - ',
                'class' => 'select2',
            ],
        ]);

        $builder->add('organizer_type', ChoiceType::class, [
            'multiple' => true,
            'required' => false,
            'choices' => $options['data']['choices']['exhibition_organizer_type'],
            'label' => 'Type of Organizing Body',
            'attr' => [
                'data-placeholder' => '- all - ',
                'class' => 'select2',
            ],
        ]);
    }

    public function getBlockPrefix()
    {
        return 'exhibition';
    }
}
