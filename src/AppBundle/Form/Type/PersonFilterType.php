<?php
// PersonFilterType.php
namespace AppBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;

use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;

use Tetranz\Select2EntityBundle\Form\Type\Select2EntityType;

class PersonFilterType
extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('gender', ChoiceType::class, [
            'choices' => [
                '- all - ' => '',
                'female' => 'F',
                'male' => 'M',
            ],
            'required' => false,
            'attr' => [
                'class' => 'select2',
            ],
        ]);

        $builder->add('nationality', ChoiceType::class, [
            'choices' => $options['data']['choices']['nationality'],
            'multiple' => true,
            'required' => false,
            'attr' => [
                'data-placeholder' => '- all - ',
                'class' => 'select2',
            ],
        ]);

        $builder->add('birthdate', YearRangeType::class, [
            'label' => 'Year of Birth',
        ]);

        $builder->add('birthplace', Select2EntityType::class, [
            'multiple' => true,
            'label' => 'Place of Birth',
            'remote_route' => 'search-select-birthplace',
            'class' => '\AppBundle\Entity\Place',
            'primary_key' => 'tgn',
            'text_property' => 'nameLocalized',
            'minimum_input_length' => 2,
            'page_limit' => 30,
            'allow_clear' => false,
            'delay' => 25,
            'cache' => true,
            'cache_timeout' => 60000, // if 'cache' is true
            'language' => 'en',
            'placeholder' => '- all -',
        ]);

        $builder->add('deathdate', YearRangeType::class, [
            'label' => 'Year of Death',
        ]);

        $builder->add('deathplace', Select2EntityType::class, [
            'multiple' => true,
            'label' => 'Place of Death',
            'remote_route' => 'search-select-deathplace',
            'class' => '\AppBundle\Entity\Place',
            'primary_key' => 'tgn',
            'text_property' => 'nameLocalized',
            'minimum_input_length' => 2,
            'page_limit' => 30,
            'allow_clear' => false,
            'delay' => 25,
            'cache' => true,
            'cache_timeout' => 60000, // if 'cache' is true
            'language' => 'en',
            'placeholder' => '- all -',
        ]);

        $builder->add('additional', ChoiceType::class, [
            'multiple' => true,
            'required' => false,
            'choices' => $options['data']['choices']['person_additional'],
            'label' => 'Additional',
            'attr' => [
                'data-placeholder' => '- all -',
                'class' => 'select2',
            ],
        ]);

        $builder->add('person', Select2EntityType::class, [
            'multiple' => true,
            'label' => 'Artist',
            'remote_route' => 'search-select-person',
            'class' => '\AppBundle\Entity\Person',
            'primary_key' => 'id',
            'text_property' => 'fullName',
            'minimum_input_length' => 2,
            'page_limit' => 30,
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
        return 'person';
    }
}
