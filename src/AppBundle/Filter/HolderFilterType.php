<?php
// PlaceFilterType.php
namespace AppBundle\Filter;

use Symfony\Component\Form\FormBuilderInterface;

use Lexik\Bundle\FormFilterBundle\Filter\Query\QueryInterface;
use Lexik\Bundle\FormFilterBundle\Filter\Form\Type as Filters;

class HolderFilterType
extends CrudFilterType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $this->addSearchFilter($builder, [
            'H.name',
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
                $expression = $filterQuery->getExpr()->in(/* $field */ 'H.countryCode', ':' . $paramName);

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


                $expression = $filterQuery->getExpr()->in('H.id', ':'.$paramName);
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
    }

    public function getBlockPrefix()
    {
        return 'holder_filter';
    }
}
