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


                $expression = $filterQuery->getExpr()->in('E.id', ':'.$paramName);
                // expression parameters
                $parameters = [
                    $paramName => [ $values['value'], \Doctrine\DBAL\Connection::PARAM_STR_ARRAY ],
                ];

                // check if it should be filtered by ids as well
                if (in_array("true", $values['value'])) {
                    echo "true enthalten";
                    return $filterQuery->createCondition($expression, $parameters);
                }

                // returns empty array if it shouldn't be filtered yet ---> for paging
                return [];

            },
        ]);



        //$builder->addEventListener(\AppBundle\Filter\FormEvents::PRE_SET_DATA, function (FormEvent $event) {

        /* $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) {

            $form = $event->getForm();


            $ids = $event->getData();
            $pos = array_search('true', $ids);



            if($pos){
                unset($ids[$pos]);
            }

            if( count($ids) > 0 ){
                $form->remove('id');
                //$form->add('id', ['ids' => $ids]);
                //$form->add('name', TextType::class);
            }
            /* if (CONDITION) {
                $builder->remove('task');
                $builder->add('task', TYPE, $NEW_OPTIONS_ARRAY);
            }*/

        // });
    }

    public function getBlockPrefix()
    {
        return 'exhibition_filter';
    }
}
