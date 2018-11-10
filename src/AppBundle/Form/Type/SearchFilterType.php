<?php
// SearchFilterType.php
namespace AppBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;

use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;


class SearchFilterType
extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('search', TextType::class);

        // entity filters
        $builder->add('person', PersonFilterType::class, $options);
        $builder->add('location', LocationFilterType::class, $options);

        // submit
        $builder->add('submit', SubmitType::class, [
            'label' => 'Search',
        ]);
    }

    public function getBlockPrefix()
    {
        return 'filter';
    }
}
