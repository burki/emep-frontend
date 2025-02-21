<?php

// MapFilterType.php

namespace AppBundle\Filter;

use Symfony\Component\Form\FormBuilderInterface;
use Spiriit\Bundle\FormFilterBundle\Filter\Form\Type as Filters;

class MapFilterType extends \Symfony\Component\Form\AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('location-type', Filters\ChoiceFilterType::class, [
            'label' => $options['data']['type_label'],
            'multiple' => true,
            'choices' => $options['data']['type_choices'],
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'map_filter';
    }
}
