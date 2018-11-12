<?php
// YearRangeType.php
namespace AppBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;

use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;

use Symfony\Component\OptionsResolver\OptionsResolver;

# use ibanu\MainBundle\Form\DataTransformer\DurationToIntegerTransformer;

class YearRangeType
extends AbstractType
{

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        // $transformer = new DurationToIntegerTransformer();

        $builder->add(
            $builder->create('from', IntegerType::class, [
                'error_bubbling' => false,
                // 'empty_data' => 0,
                'label' => 'From',
            ])
        );
        $builder->add(
            $builder->create('until', IntegerType::class, [
                'error_bubbling' => false,
                //'empty_data' => 0,
                'label' => 'Until',
            ])
        );

        // $builder->addModelTransformer($transformer);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
         $resolver->setDefaults(array(
            /*
            'hours'          => range(0, 256),
            'minutes'        => range(0, 59),
            */
            'error_bubbling' => false,
            'compound'       => true,
            'required'       => false
        ));
    }

    public function getParent()
    {
        return TextType::class;
    }

    public function getName()
    {
        return 'range';
    }
}
