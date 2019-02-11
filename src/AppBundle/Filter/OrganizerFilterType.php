<?php
// LocationFilterType.php
namespace AppBundle\Filter;

use Symfony\Component\Form\FormBuilderInterface;

use Lexik\Bundle\FormFilterBundle\Filter\Query\QueryInterface;
use Lexik\Bundle\FormFilterBundle\Filter\Form\Type as Filters;

class OrganizerFilterType
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

        $organizerClass = new class extends \Symfony\Component\Form\AbstractType {
            public function buildForm(FormBuilderInterface $builder, array $options)
            {
                $country_geoname_choices = $options['data']['choices']['country'];
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
                    'choices' => [ 'select type of organizing body' => '' ] + $options['data']['choices']['location_type'],
                    'attr' => [
                        'data-placeholder' => 'select type of organizing body',
                        'class' => 'text-field-class w-select end-selector',
                    ],
                ]);
            }

            public function getName()
            {
                return 'organizer';
            }
        };

        $builder->add('organizer', get_class($organizerClass), $options);
    }

    public function getBlockPrefix()
    {
        return 'filter';
    }
}
