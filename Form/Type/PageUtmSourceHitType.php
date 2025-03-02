<?php declare(strict_types=1);

namespace MauticPlugin\MauticFactorialBundle\Form\Type;

use Mautic\CacheBundle\Cache\CacheProvider;
use Mautic\LeadBundle\Entity\LeadField;
use Mautic\LeadBundle\Entity\LeadFieldRepository;
use Mautic\LeadBundle\Model\ListModel;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

/**
 * @extends AbstractType<array<mixed>>
 */
class PageUtmSourceHitType extends AbstractType
{
    private TagAwareAdapterInterface $cache;

    public function __construct(
        private TranslatorInterface      $translator,
        private ListModel                $listModel,
        private LeadFieldRepository      $leadFieldRepository,
        private Environment              $twig,
        private EventDispatcherInterface $dispatcher,
        CacheProvider                    $cacheProvider,
    )
    {
        $this->cache = $cacheProvider->getCacheAdapter();
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('utm_source', ChoiceType::class, [
            'choices'    => [
                'Facebook'    => 'facebook',
                'Xing'        => 'xing',
                'Mastodon'    => 'mastodon',
                'Google'      => 'google',
                'Instagram'   => 'instagram',
                'LinkedIn'    => 'linkedin',
                'X (Twitter)' => 'twitter',
                'Email'       => 'email',

            ],
            'label'      => 'mautic.page.point.label.utm_source',
            'label_attr' => ['class' => 'control-label'],
            'attr'       => [
                'class' => 'form-control',
            ],
            'multiple'   => true,
        ]);

        $builder->add('operator', ChoiceType::class, [
            'choices' => [
                $this->translator->trans('Equals')     => 'in',
                $this->translator->trans('Not equals') => 'not_in',
            ],
        ]);
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars['fields'] = $this->listModel->getChoiceFields(); // currently unsus

        $javascriptContent = $this->twig->render('@MauticFactorial/js/point.html.twig');

        $this->dispatcher->addListener('kernel.response', function ($event) use ($javascriptContent) {
            $response = $event->getResponse();
            try {
                $content = json_decode($response->getContent(), true);
                if ($content['success'] == 1 && !str_contains($content['html'], 'factorialPointJavascript')) {
                    $content['html'] .= $javascriptContent;
                    $response->setContent(json_encode($content));
                    $event->setResponse($response);
                }
            } catch (\Exception $e) {
            }
        });
    }

    public function getBlockPrefix(): string
    {
        return 'pointaction_property_change';
    }
}
