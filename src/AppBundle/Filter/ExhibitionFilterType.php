<?php
// ExhibitionFilterType.php
namespace AppBundle\Filter;

use Symfony\Component\Form\FormBuilderInterface;

use Lexik\Bundle\FormFilterBundle\Filter\Form\Type as Filters;

use Tetranz\Select2EntityBundle\Form\Type\Select2EntityType;

class ExhibitionFilterType
extends CrudFilterType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('search', Filters\TextFilterType::class, [
            'label' => false,
            'attr' => [
                'placeholder' => 'search exhibition title',
                'class' => 'text-field-class w-input search-input input-text-search',
            ],
        ]);

        $locationClass = new class extends \Symfony\Component\Form\AbstractType {
            public function buildForm(FormBuilderInterface $builder, array $options)
            {

                // $country_geoname_choices = $options['data']['country_choices'];
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
            }

            public function getName()
            {
                return 'location';
            }
        };

        $builder->add('location', get_class($locationClass), $options);

        $exhibitionClass = new class extends \Symfony\Component\Form\AbstractType {
            public function buildForm(FormBuilderInterface $builder, array $options)
            {
                $builder->add('organizer_type', Filters\ChoiceFilterType::class, [
                    'label' => 'Type of Organizing Body',
                    'multiple' => false,
                    // 'choices' => [ 'select type of organizing body' => '' ] + $options['data']['organizer_type_choices'],
                    'choices' => [ 'select type of organizing body' => '' ] + $options['data']['choices']['exhibition_organizer_type'],
                    'attr' => [
                        'data-placeholder' => 'select type of organizing body',
                        'class' => 'text-field-class w-select end-selector',
                    ],
                ]);

                // copied over from Form/ExhibitionFilterType, find a way to share
                $builder->add('exhibition', Select2EntityType::class, [
                    'multiple' => true,
                    'label' => 'Exhibition',
                    'remote_route' => 'search-select-exhibition',
                    'class' => '\AppBundle\Entity\Exhibition',
                    'primary_key' => 'id',
                    'text_property' => 'title',
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

            public function getName()
            {
                return 'exhibition';
            }
        };

        $builder->add('exhibition', get_class($exhibitionClass), $options);
    }

    public function getBlockPrefix()
    {
        return 'filter';
    }
}
