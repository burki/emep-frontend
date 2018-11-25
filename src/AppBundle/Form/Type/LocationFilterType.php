<?php
// LocationFilterType.php
namespace AppBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;

use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

class LocationFilterType
extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
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
    }

    public function getBlockPrefix()
    {
        return 'location';
    }
}
