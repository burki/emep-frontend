<?php
// ExhibitionFilterType.php

namespace AppBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;

use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

use Tetranz\Select2EntityBundle\Form\Type\Select2EntityType;

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
            'label' => 'Type of Exhibition',
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
                'data-placeholder' => '- all -',
                'class' => 'select2',
            ],
        ]);

        $builder->add('flags', ChoiceType::class, [
            'multiple' => true,
            'required' => false,
            'choices' => $options['data']['choices']['exhibition_flags'],
            'label' => 'Additional Information',
            'attr' => [
                'data-placeholder' => '- all -',
                'class' => 'select2',
            ],
        ]);

        $builder->add('exhibition', Select2EntityType::class, [
            'multiple' => true,
            'label' => 'Exhibition',
            'remote_route' => 'search-select-exhibition',
            'class' => '\AppBundle\Entity\Exhibition',
            'primary_key' => 'id',
            'text_property' => 'title',
            'minimum_input_length' => 2,
            'page_limit' => 20, // so Nus shows despite many Turnus Exhibhitions
            'allow_clear' => false,
            'delay' => 25,
            'cache' => true,
            'cache_timeout' => 60000, // if 'cache' is true
            'language' => 'en',
            'placeholder' => '- all -',
        ]);
    }

    public function getBlockPrefix()
    {
        return 'exhibition';
    }
}
