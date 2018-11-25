<?php
// PersonFilterType.php
namespace AppBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;

use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;

class PersonFilterType
extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
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

        $builder->add('deathdate', YearRangeType::class, [
            'label' => 'Year of Death',
        ]);
    }

    public function getName()
    {
        return 'person';
    }
}
