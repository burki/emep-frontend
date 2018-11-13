<?php
// LocationFilterType.php
namespace AppBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;

use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

class OrganizerFilterType
extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('geoname', ChoiceType::class, [
            'choices' => [ '- all - ' => '' ] + $options['data']['choices']['organizer_geoname'],
            'required' => false,
            'label' => 'Country / City',
        ]);

        $builder->add('type', ChoiceType::class, [
            'label' => 'Type',
            // 'multiple' => true,
            'choices' => [ '- all - ' => '' ] + $options['data']['choices']['organizer_type'],
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
        return 'organizer';
    }
}
