<?php
// PlaceFilterType.php
namespace AppBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;

use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

use Tetranz\Select2EntityBundle\Form\Type\Select2EntityType;

class PlaceFilterType
extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('geoname', ChoiceType::class, [
            'choices' => $options['data']['choices']['place_geoname'],
            'multiple' => true,
            'required' => false,
            'label' => 'Country',
            'attr' => [
                'data-placeholder' => '- all countries - ',
                'class' => 'select2',
            ],
        ]);
    }

    public function getBlockPrefix()
    {
        return 'place';
    }
}
