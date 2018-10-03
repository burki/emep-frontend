<?php
// LocationFilterType.php
namespace AppBundle\Filter;

use Symfony\Component\Form\FormBuilderInterface;

use Lexik\Bundle\FormFilterBundle\Filter\Query\QueryInterface;
use Lexik\Bundle\FormFilterBundle\Filter\Form\Type as Filters;

class LocationFilterType
extends CrudFilterType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $this->addSearchFilter($builder, [
            // Exhibition.title,Exhibition.title_short,Exhibition.title_translit,Exhibition.title_alternate,Exhibition.subtitle,
            // Location.name,Location.place,Person.lastname,Person.firstname,Exhibition.organizer,Exhibition.organizing_committee,Exhibition.description,Exhibition.comment_internal
            'L.name', 'L.placeLabel',
        ]);

        $builder->add('country', Filters\ChoiceFilterType::class, [
            'choices' => [ 'select country' => '' ] + $options['data']['country_choices'],
            'apply_filter' => function (QueryInterface $filterQuery, $field, $values) {
                if (empty($values['value'])) {
                    return null;
                }

                $paramName = sprintf('p_%s', str_replace('.', '_', $field));

                // expression that represent the condition
                $expression = $filterQuery->getExpr()->eq('P.countryCode', ':'.$paramName);

                // expression parameters
                $parameters = [ $paramName => $values['value'] ];

                return $filterQuery->createCondition($expression, $parameters);
            },
        ]);

        $builder->add('location_type', Filters\ChoiceFilterType::class, [
            'label' => 'Type',
            'multiple' => true,
            'choices' => $options['data']['location_type_choices'],
            'attr' => [
                'data-placeholder' => 'select location type',
            ],
            'apply_filter' => function (QueryInterface $filterQuery, $field, $values) {
                if (empty($values['value'])) {
                    return null;
                }

                $paramName = sprintf('l_%s', str_replace('.', '_', $field));

                // expression that represent the condition
                $expression = $filterQuery->getExpr()->in('L.type', ':'.$paramName);

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
        return 'location_filter';
    }
}
