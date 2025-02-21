<?php

// PersonFilterType.php

namespace AppBundle\Filter;

use Symfony\Component\Form\FormBuilderInterface;
use Spiriit\Bundle\FormFilterBundle\Filter\Form\Type as Filters;
use Tetranz\Select2EntityBundle\Form\Type\Select2EntityType;

class PersonFilterType extends CrudFilterType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('search', Filters\TextFilterType::class, [
            'label' => false,
            'attr' => [
                'placeholder' => "search artists' name",
                'class' => 'text-field-class w-input search-input input-text-search',
            ],
        ]);

        $personClass = new class extends \Symfony\Component\Form\AbstractType {
            public function buildForm(FormBuilderInterface $builder, array $options)
            {
                $builder->add('gender', Filters\ChoiceFilterType::class, [
                    'choices' => [
                        'select gender' => '',
                        'female' => 'F',
                        'male' => 'M',
                    ],
                    'attr' => [
                        'data-placeholder' => 'select gender',
                        'class' => 'text-field-class w-select middle-selector',
                    ],
                ]);

                $builder->add('nationality', Filters\ChoiceFilterType::class, [
                    'choices' => [ 'select nationality' => '' ] + $options['data']['choices']['nationality'],
                    'multiple' => false,
                    'attr' => [
                        'data-placeholder' => 'select nationality',
                        'class' => 'text-field-class w-select end-selector',
                    ],
                ]);

                // copied over from Form/PersonFilterType, find a way to share
                $builder->add('person', Select2EntityType::class, [
                    'multiple' => true,
                    'label' => 'Artist',
                    'remote_route' => 'search-select-person',
                    'class' => '\AppBundle\Entity\Person',
                    'primary_key' => 'id',
                    'text_property' => 'fullName',
                    'minimum_input_length' => 2,
                    'page_limit' => 10,
                    'allow_clear' => false,
                    'delay' => 25,
                    'cache' => true,
                    'cache_timeout' => 60000, // if 'cache' is true
                    'language' => 'en',
                    'placeholder' => '- all -',
                ]);
            }

            public function getName()
            {
                return 'person';
            }
        };

        $builder->add('person', get_class($personClass), $options);
    }

    public function getBlockPrefix(): string
    {
        return 'filter';
    }
}
