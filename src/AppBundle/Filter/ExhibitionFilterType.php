<?php
// ExhibitionFilterType.php
namespace AppBundle\Filter;

use Symfony\Component\Form\FormBuilderInterface;

use Lexik\Bundle\FormFilterBundle\Filter\Query\QueryInterface;
use Lexik\Bundle\FormFilterBundle\Filter\Form\Type as Filters;

class ExhibitionFilterType
extends CrudFilterType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('search', Filters\TextFilterType::class, [
            'label' => false,
            'attr' => [
                'placeholder' => 'search exhibition title',
                'class' => 'text-field-class w-input search-input input-text-search',
            ],
        ]);

        $locationClass = new class extends \Symfony\Component\Form\AbstractType {
            public function buildForm(FormBuilderInterface $builder, array $options)
            {
                $country_geoname_choices = $options['data']['country_choices'];
                foreach ($country_geoname_choices as $label => $cc) {
                    $country_geoname_choices[$label] = 'cc:' . $cc;
                }

                $builder->add('geoname', Filters\ChoiceFilterType::class, [
                    'choices' => [ 'select country' => '' ] + $country_geoname_choices,
                    'multiple' => false,
                    'attr' => [
                        'data-placeholder' => 'select country',
                        'class' => 'text-field-class w-select middle-selector',
                    ],
                ]);
            }

            public function getName()
            {
                return 'location';
            }
        };

        $builder->add('location', get_class($locationClass), $options);

        $exhibitionClass = new class extends \Symfony\Component\Form\AbstractType {
            public function buildForm(FormBuilderInterface $builder, array $options)
            {
                $builder->add('organizer_type', Filters\ChoiceFilterType::class, [
                    'label' => 'Type of Organizing Body',
                    'multiple' => false,
                    'choices' => [ 'select type of organizing body' => '' ] + $options['data']['organizer_type_choices'],
                    'attr' => [
                        'data-placeholder' => 'select type of organizing body',
                        'class' => 'text-field-class w-select end-selector',
                    ],
                ]);

                $builder->add('id', Filters\ChoiceFilterType::class, [
                    'choices' => [ 'select ids' => 'true'] + $options['data']['ids'],
                    'multiple' => true,
                ]);
            }

            public function getName()
            {
                return 'exhibition';
            }
        };

        $builder->add('exhibition', get_class($exhibitionClass), $options);


        // QUERYING FOR OTHER MODELS

        // PERSON QUERYS



        $builder->add('startdate', Filters\DateRangeFilterType::class, [
                'left_date_options'  => array('years' => range($options['data']['years'][0], $options['data']['years'][1])),
                'right_date_options' => array('years' => range($options['data']['years'][0], $options['data']['years'][1]))
            ]
        );



        $builder->add('nationality', Filters\ChoiceFilterType::class, [
            'choices' => [ 'select nationality' => '' ] + $options['data']['country_choices'],
            'multiple' => true,
            'apply_filter' => function (QueryInterface $filterQuery, $field, $values) {
                if (empty($values['value'])) {
                    return null;
                }

                $paramName = sprintf('p_%s', str_replace('.', '_', $field));

                // expression that represent the condition
                $expression = $filterQuery->getExpr()->in('Person.nationality', ':'.$paramName);

                // expression parameters
                $parameters = [
                    $paramName => [ $values['value'], \Doctrine\DBAL\Connection::PARAM_STR_ARRAY ],
                ];

                return $filterQuery->createCondition($expression, $parameters);
            },
        ]);


        $builder->add('gender', Filters\ChoiceFilterType::class, [
            'choices' => [
                'female' => 'F',
                'male' => 'M',
            ],
            'multiple' => true,
            'apply_filter' => function (QueryInterface $filterQuery, $field, $values) {
                if (empty($values['value'])) {
                    return null;
                }

                $paramName = sprintf('p_%s', str_replace('.', '_', $field));

                // expression that represent the condition
                $expression = $filterQuery->getExpr()->in('Person.gender', ':'.$paramName);

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
