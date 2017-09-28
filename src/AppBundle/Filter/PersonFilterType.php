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
                '- all - ' => '',
                'female' => 'F',
                'male' => 'M',
            ],
        ]);

        $builder->add('nationality', Filters\ChoiceFilterType::class, [
            'choices' => [ '- all - ' => '' ] + $options['data']['choices'],
        ]);
    }

    public function getBlockPrefix()
    {
        return 'person_filter';
    }
}
