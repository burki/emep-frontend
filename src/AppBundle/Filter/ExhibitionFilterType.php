<?php
// ExhibitionFilterType.php
namespace AppBundle\Filter;


use Symfony\Component\Form\FormBuilderInterface;

use Lexik\Bundle\FormFilterBundle\Filter\Query\QueryInterface;
use Lexik\Bundle\FormFilterBundle\Filter\Form\Type as Filters;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;


class ExhibitionFilterType
extends CrudFilterType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $this->addSearchFilter($builder, [
            // Exhibition.title,Exhibition.title_short,Exhibition.title_translit,Exhibition.title_alternate,Exhibition.subtitle,
            // Location.name,Location.place,Person.lastname,Person.firstname,Exhibition.organizer,Exhibition.organizing_committee,Exhibition.description,Exhibition.comment_internal
            'E.title', 'E.titleTransliterated', 'E.titleAlternate',
            'L.name', 'L.placeLabel',
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
                $expression = $filterQuery->getExpr()->in(/* $field */ 'P.countryCode', ':'.$paramName);

                // expression parameters
                $parameters = [
                    $paramName => [ $values['value'], \Doctrine\DBAL\Connection::PARAM_STR_ARRAY ],
                ];
                return $filterQuery->createCondition($expression, $parameters);
            },
        ]);

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



        $builder->add('id', Filters\ChoiceFilterType::class, [
            'choices' => [ 'select ids' => 'true'] + $options['data']['ids'],
            'multiple' => true,
            'apply_filter' => function (QueryInterface $filterQuery, $field, $values) {



                if (empty($values['value'])) {
                    return null;
                }

                $paramName = sprintf('p_%s', str_replace('.', '_', $field));

                // expression that represent the condition


                $expression = $filterQuery->getExpr()->in('E.id', ':'.$paramName);
                // expression parameters
                $parameters = [
                    $paramName => [ $values['value'], \Doctrine\DBAL\Connection::PARAM_STR_ARRAY ],
                ];

                // check if it should be filtered by ids as well
                if (in_array("true", $values['value'])) {
                    return $filterQuery->createCondition($expression, $parameters);
                }

                // returns empty array if it shouldn't be filtered yet ---> for paging
                return [];

            },
        ]);



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
        return 'exhibition_filter';
    }
}
