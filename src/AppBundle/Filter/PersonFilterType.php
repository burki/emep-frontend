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
                $country_geoname_choices = $options['data']['country_choices'];
                foreach ($country_geoname_choices as $label => $cc) {
                    $country_geoname_choices[$label] = 'cc:' . $cc;
                }

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
                    'choices' => [ 'select nationality' => '' ] + $options['data']['choices'],
                    'multiple' => false,
                    'attr' => [
                        'data-placeholder' => 'select nationality',
                        'class' => 'text-field-class w-select middle-selector',
                    ],
                ]);

                $builder->add('id', Filters\ChoiceFilterType::class, [
                    'choices' => [ 'select ids' => 'true'] + $options['data']['ids'],
                    'multiple' => true,
                ]);


                $builder->add('birthDate', Filters\DateRangeFilterType::class, [
                    'left_date_options'  => [ 'years' => range($options['data']['birthyears'][0], $options['data']['birthyears'][1]) ],
                    'right_date_options' => [ 'years' => range($options['data']['birthyears'][0], $options['data']['birthyears'][1]) ],
                ]);

                $builder->add('deathDate', Filters\DateRangeFilterType::class, [
                    'left_date_options'  => [ 'years' => range($options['data']['deathyears'][0], $options['data']['deathyears'][1]) ],
                    'right_date_options' => [ 'years' => range($options['data']['deathyears'][0], $options['data']['deathyears'][1]) ],
                ]);
            }

            public function getName()
            {
                return 'person';
            }
        };

        $builder->add('person', get_class($personClass), $options);


        $builder->add('organizer_type', Filters\ChoiceFilterType::class, [
            'label' => 'Type of Organizing Body',
            'multiple' => true,
            'choices' => $options['data']['organizer_type_choices'],
            'attr' => [
                'data-placeholder' => 'select type of organizing body',
            ],
            'apply_filter' => function (QueryInterface $filterQuery, $field, $values) {
                if (empty($values['value'])) {
                    return null;
                }

                $paramName = sprintf('e_%s', str_replace('.', '_', $field));

                // expression that represent the condition
                $expression = $filterQuery->getExpr()->in('E.organizerType', ':'.$paramName);

                // expression parameters
                $parameters = [
                    $paramName => [ $values['value'], \Doctrine\DBAL\Connection::PARAM_STR_ARRAY ],
                ];

                return $filterQuery->createCondition($expression, $parameters);
            },
        ]);



        $builder->add('country', Filters\ChoiceFilterType::class, [
            'choices' => [ 'select country' => '' ] + $options['data']['country_choices'],
            'multiple' => true,
            'apply_filter' => function (QueryInterface $filterQuery, $field, $values) {
                if (empty($values['value'])) {
                    return null;
                }

                $paramName = sprintf('p_%s', str_replace('.', '_', $field));

                // expression that represent the condition
                $expression = $filterQuery->getExpr()->in('Pl.countryCode', ':'.$paramName);

                // expression parameters
                $parameters = [
                    $paramName => [ $values['value'], \Doctrine\DBAL\Connection::PARAM_STR_ARRAY ],
                ];
                return $filterQuery->createCondition($expression, $parameters);
            },
        ]);

    }

    public function getBlockPrefix()
    {
        return 'filter';
    }
}
