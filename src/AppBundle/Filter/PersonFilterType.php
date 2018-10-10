<?php
// PersonFilterType.php
namespace AppBundle\Filter;

use Symfony\Component\Form\FormBuilderInterface;
use Lexik\Bundle\FormFilterBundle\Filter\Form\Type as Filters;

class PersonFilterType
extends CrudFilterType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $this->addSearchFilter($builder, [
            'P.familyName', 'P.givenName',
            'P.birthPlaceLabel', 'P.deathPlaceLabel',
            'P.ulan', 'P.gnd',
            'P.variantName',
            // name_variant_ulan,occupation,cv
        ]);

        $builder->add('gender', Filters\ChoiceFilterType::class, [
            'choices' => [
                'select gender' => '',
                'female' => 'F',
                'male' => 'M',
            ],
        ]);

        $builder->add('nationality', Filters\ChoiceFilterType::class, [
            'choices' => [ 'select nationality' => '' ] + $options['data']['choices'],
            'multiple' => true
        ]);
    }

    public function getBlockPrefix()
    {
        return 'person_filter';
    }
}
