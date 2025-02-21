<?php

// LocationFilterType.php

namespace AppBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Tetranz\Select2EntityBundle\Form\Type\Select2EntityType;

class LocationFilterType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('geoname', ChoiceType::class, [
            'choices' => $options['data']['choices']['location_geoname'],
            'multiple' => true,
            'required' => false,
            'label' => 'Country / City',
            'attr' => [
                'data-placeholder' => '- all - ',
                'class' => 'select2',
            ],
        ]);

        $builder->add('type', ChoiceType::class, [
            'choices' => $options['data']['choices']['location_type'],
            'multiple' => true,
            'required' => false,
            'label' => 'Type',
            'attr' => [
                'data-placeholder' => '- all - ',
                'class' => 'select2',
            ],
        ]);

        $builder->add('location', Select2EntityType::class, [
            'multiple' => true,
            'label' => 'Venue',
            'remote_route' => 'search-select-location',
            'class' => '\AppBundle\Entity\Location',
            'primary_key' => 'id',
            'text_property' => 'name',
            'minimum_input_length' => 2,
            'page_limit' => 10,
            'allow_clear' => false,
            'delay' => 25,
            'cache' => true,
            'cache_timeout' => 60000, // if 'cache' is true
            'language' => 'en',
            'placeholder' => '- all -',
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'location';
    }
}
