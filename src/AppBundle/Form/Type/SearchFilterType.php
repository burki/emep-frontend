<?php

// SearchFilterType.php

namespace AppBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class SearchFilterType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('search', TextType::class);

        // entity filters
        $builder->add('catentry', ItemExhibitionFilterType::class, $options);
        $builder->add('exhibition', ExhibitionFilterType::class, $options);
        $builder->add('location', LocationFilterType::class, $options);
        $builder->add('organizer', OrganizerFilterType::class, $options);
        $builder->add('person', PersonFilterType::class, $options);
        $builder->add('place', PlaceFilterType::class, $options);

        // currently only for export
        $builder->add('holder', HolderFilterType::class, $options);

        // submit
        $builder->add('submit', SubmitType::class, [
            'label' => 'Search',
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'filter';
    }
}
