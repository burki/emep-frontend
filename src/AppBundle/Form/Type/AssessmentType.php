<?php

namespace AppBundle\Form\Type;

use Symfony\Bridge\Doctrine\Form\Type\EntityType;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;

use Shapecode\Bundle\HiddenEntityTypeBundle\Form\Type\HiddenEntityType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

use Symfony\Component\Validator\Constraints as Assert;

class AssessmentType
extends AbstractType
{
    public function configureOptions(\Symfony\Component\OptionsResolver\OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => \AppBundle\Entity\UserItem::class,
            'show_default' => 'not assessed yet',
            'style_choices' => [],
        ]);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('show', ChoiceType::class, [
                'label' => 'Show',
                'choices' => [
                    'not assessed yet' => 'not assessed yet',
                    'assessed' => 'assessed',
                    'no consensus' => 'no consensus',
                    'all' => '',
                ],
                'data' => $options['show_default'],
                'attr' => [
                    'onChange' => 'this.form.submit()',
                ],
            ])
            ->add('item', HiddenEntityType::class, [
                'class' => \AppBundle\Entity\Item::class,
            ])
            ->add('style', EntityType::class, [
                'label' => 'Style',
                'class' => \AppBundle\Entity\Term::class,
                'choices' => $options['style_choices'],
                'choice_label' => 'name',
                'expanded' => true,
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Send',
            ])
        ;
    }

    public function getName()
    {
        return 'assessmenttype';
    }
}
