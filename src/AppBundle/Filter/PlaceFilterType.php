<?php
// PlaceFilterType.php
namespace AppBundle\Filter;

use Symfony\Component\Form\FormBuilderInterface;

use Lexik\Bundle\FormFilterBundle\Filter\Query\QueryInterface;
use Lexik\Bundle\FormFilterBundle\Filter\Form\Type as Filters;

class PlaceFilterType
extends CrudFilterType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $this->addSearchFilter($builder, [
            'P.name', 'P.alternateName',
        ]);

        $builder->add('country', Filters\ChoiceFilterType::class, [
            'choices' => [ 'select country' => '' ] + $options['data']['choices'],
            'apply_filter' => function (QueryInterface $filterQuery, $field, $values) {
                if (empty($values['value'])) {
                    return null;
                }

                $paramName = sprintf('p_%s', str_replace('.', '_', $field));

                // expression that represent the condition
                $expression = $filterQuery->getExpr()->eq(/* $field */ 'P.countryCode', ':'.$paramName);

                // expression parameters
                $parameters = [ $paramName => $values['value'] ];

                return $filterQuery->createCondition($expression, $parameters);
            },
        ]);
    }

    public function getBlockPrefix()
    {
        return 'place_filter';
    }
}
