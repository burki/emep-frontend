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
        $builder->add('country', ChoiceType::class, [
            'choices' => [ '- all - ' => '' ] + $options['data']['choices']['location_country'],
            'required' => false,
        ]);

        $builder->add('type', ChoiceType::class, [
            'label' => 'Type',
            // 'multiple' => true,
            'choices' => [ '- all - ' => '' ] + $options['data']['choices']['location_type'],
            /*
            'attr' => [
                'data-placeholder' => '- all - ',
            ],
            */
            'required' => false,
        ]);
    }

    public function getBlockPrefix()
    {
        return 'location';
    }
}
