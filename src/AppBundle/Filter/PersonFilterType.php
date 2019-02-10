<?php
// PersonFilterType.php
namespace AppBundle\Filter;

use Symfony\Component\Form\FormBuilderInterface;

use Lexik\Bundle\FormFilterBundle\Filter\Form\Type as Filters;
use Lexik\Bundle\FormFilterBundle\Filter\Query\QueryInterface;


class PersonFilterType
extends CrudFilterType
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
            }

            public function getName()
            {
                return 'person';
            }
        };

        $builder->add('person', get_class($personClass), $options);
    }

    public function getBlockPrefix()
    {
        return 'filter';
    }
}
