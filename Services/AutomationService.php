<?php

namespace Webkul\UVDesk\AutomationBundle\Services;

use Doctrine\ORM\EntityManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Webkul\UVDesk\AutomationBundle\Event\ActivityEvent;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Webkul\UVDesk\AutomationBundle\EventListener\WorkflowSubscriber;

class AutomationService
{
	protected $container;
	protected $requestStack;
    protected $entityManager;
    protected $eventDispatcher;

	public function __construct(ContainerInterface $container, RequestStack $requestStack, EntityManager $entityManager)
	{
		$this->container = $container;
		$this->requestStack = $requestStack;
        $this->entityManager = $entityManager;
        
        $this->eventDispatcher = new EventDispatcher();
        $this->event = new ActivityEvent($this->container);

        // Add Listeners
        $this->eventDispatcher->addSubscriber(new WorkflowSubscriber());
    }
    
    public function trigger(array $params)
    {
        try {
            if ($params['entity']) {
                $this->event->setParameters($params);
                $this->eventDispatcher->dispatch($params['event'], $this->event);
            }
        } catch(\Exception $e) {
            if ($this->container->get('kernel')->getEnvironment() == 'dev') {
                dump($e->getMessage());
                die;
            }
        }
    }
}
