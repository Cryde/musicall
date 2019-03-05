<?php

namespace App\Form;

use App\Entity\Publication;
use App\Entity\PublicationSubCategory;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PublicationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('title', TextType::class, ['attr' => ['placeholder' => 'Titre'], 'required' => false])
            ->add('shortDescription')
            ->add('content', TextareaType::class, ['attr' => ['class' => 'js-st-instance'], 'required' => false])
            ->add('subCategory', EntityType::class, [
                'class'        => PublicationSubCategory::class,
                'choice_label' => 'title',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Publication::class,
        ]);
    }
}
