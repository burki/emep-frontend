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
        $builder->add('search', Filters\TextFilterType::class, [
            'label' => false,
            'attr' => [
                'placeholder' => "search name",
                'class' => 'text-field-class w-input search-input input-text-search',
            ],
        ]);

        $locationClass = new class extends \Symfony\Component\Form\AbstractType {
            public function buildForm(FormBuilderInterface $builder, array $options)
            {
                $country_geoname_choices = $options['data']['country_choices'];
                foreach ($country_geoname_choices as $label => $cc) {
                    $country_geoname_choices[$label] = 'cc:' . $cc;
                }

                $builder->add('geoname', Filters\ChoiceFilterType::class, [
                    'choices' => [ 'select country' => '' ] + $country_geoname_choices,
                    'multiple' => false,
                    'attr' => [
                        'data-placeholder' => 'select country',
                        'class' => 'text-field-class w-select middle-selector',
                    ],
                ]);

                $builder->add('type', Filters\ChoiceFilterType::class, [
                    'label' => 'Type',
                    'multiple' => false,
                    'choices' => [ $options['data']['location_type_placeholder'] => '' ] + $options['data']['location_type_choices'],
                    'attr' => [
                        'data-placeholder' => 'select location type',
                        'class' => 'text-field-class w-select middle-selector',
                    ],
                ]);

                $builder->add('id', Filters\ChoiceFilterType::class, [
                    'choices' => [ 'select ids' => 'true'] + $options['data']['ids'],
                    'multiple' => true,
                ]);
            }

            public function getName()
            {
                return 'location';
            }
        };

        $builder->add('location', get_class($locationClass), $options);
    }

    public function getBlockPrefix()
    {
        return 'filter';
    }
}
