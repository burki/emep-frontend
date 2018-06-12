<?php
// MapFilterType.php
namespace AppBundle\Filter;

use Symfony\Component\Form\FormBuilderInterface;

use Lexik\Bundle\FormFilterBundle\Filter\Form\Type as Filters;

class MapFilterType
extends \Symfony\Component\Form\AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('location-type', Filters\ChoiceFilterType::class, [
            'label' => 'Type of Venue',
            'multiple' => true,
            'choices' => /* [ '- all - ' => '' ] + */ $options['data']['type_choices'],
        ]);
    }

    public function getBlockPrefix()
    {
        return 'map_filter';
    }
}
