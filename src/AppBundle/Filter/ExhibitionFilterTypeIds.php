<?php
// ExhibitionFilterType.php
namespace AppBundle\Filter;

use Symfony\Component\Form\FormBuilderInterface;

use Lexik\Bundle\FormFilterBundle\Filter\Query\QueryInterface;
use Lexik\Bundle\FormFilterBundle\Filter\Form\Type as Filters;

class ExhibitionFilterTypeIds
extends CrudFilterType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {

        $builder->add('id', Filters\ChoiceFilterType::class, [
            'choices' => [ 'select ids' => '1085' ] + [],
            'multiple' => true,
            'apply_filter' => function (QueryInterface $filterQuery, $field, $values) {

                echo 'applying filter';
                echo 'values: ';
                print_r ($values['value']);
                print_r ($field);

                if (empty($values['value'])) {
                    return null;
                }

                $paramName = sprintf('p_%s', str_replace('.', '_', $field));

                print "\n";
                print "pramname: ";
                print_r($paramName);

                // expression that represent the condition
                $expression = $filterQuery->getExpr()->in(/* $field */ 'E.id', ':'.$paramName);

                // expression parameters
                $parameters = [
                    $paramName => [ $values['value'], \Doctrine\DBAL\Connection::PARAM_STR_ARRAY ],
                ];
                return $filterQuery->createCondition($expression, $parameters);
            },
        ]);

    }

}
