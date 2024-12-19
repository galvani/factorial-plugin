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
class PointPropertyChangeType extends AbstractType
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
        $builder->add('lead_field', ChoiceType::class, [
            'choices'    => array_filter($this->getFieldList()),
            'label'      => 'mautic.lead.field.form.choose',
            'label_attr' => ['class' => 'control-label'],
            'attr'       => [
                'class' => 'form-control',
            ],
        ]);

        $builder->add('operator', ChoiceType::class, [
            'choices' => [
                $this->translator->trans('Equals')           => 'in',
                $this->translator->trans('Not equals')       => 'not_in',
                $this->translator->trans('Greater or equal') => 'gte',
                $this->translator->trans('Less or equal')    => 'lte',
                $this->translator->trans('Between')          => 'between'
            ],
        ]);

        $adjustFields = function (FormEvent $event): void {
            $data = $event->getData();
            $form = $event->getForm();

            $name                = ($data['operator'] ?? null) === 'in' ? 'mautic.lead.field.point.values' : 'mautic.core.value';
            $secondValueRequired = ($data['operator'] ?? null) === 'between';

            $form->add('value1',
                       TextType::class,
                       [
                           'label'      => $name,
                           'label_attr' => ['class' => 'control-label'],
                           'required'   => true,
                           'attr'       => [
                               'class' => 'form-control',
                           ]
                       ]
            );

            $form->add('value2',
                       TextType::class,
                       [
                           'label'      => 'mautic.core.value',
                           'label_attr' => ['class' => 'control-label'],
                           'required'   => $secondValueRequired,
                           'attr'       => [
                               'class' => 'form-control',
                           ]
                       ]
            );
        };

        $builder->addEventListener(FormEvents::PRE_SET_DATA, $adjustFields);
        $builder->addEventListener(FormEvents::PRE_SUBMIT, $adjustFields);
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

    /**
     * @return array<LeadField>
     */
    private function getFieldList(array $filters = ['isPublished' => true]): array
    {
        return $this->cache->get('points.contact.fields', function (CacheItem $item) use ($filters) {
            $item->expiresAfter(new \DateInterval('PT5M'));

            $forceFilters = [];
            foreach ($filters as $col => $val) {
                $forceFilters[] = [
                    'column' => "f.{$col}",
                    'expr'   => 'eq',
                    'value'  => $val,
                ];
            }
            // Get a list of custom form fields
            $fields = $this->leadFieldRepository->getEntities(
                [
                    'filter'     => [
                        'force' => $forceFilters,
                    ],
                    'orderBy'    => 'f.label',
                    'orderByDir' => 'asc',
                ]);

            $leadFields = [];

            foreach ($fields as $f) {
                $fieldName = $this->translator->trans('mautic.lead.field.group.'.$f->getGroup());
                if ($f->getObject() === 'lead') {
                    $leadFields[$fieldName][$f->getLabel()] = $f->getAlias();
                } else {
                    $companyFields[$f->getLabel()] = $f->getAlias();
                }
            }

            $leadFields[ucfirst($this->translator->trans('company'))] = $companyFields;

            return $leadFields;
        });
    }
}
