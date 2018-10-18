<?php

namespace Webkul\UVDesk\AutomationBundle\EventListener;

use Doctrine\ORM\EntityManager;
use Symfony\Component\EventDispatcher\GenericEvent;
use Webkul\UVDesk\AutomationBundle\Entity\Workflow;
use Symfony\Component\DependencyInjection\ContainerInterface;

class WorkflowListener
{
    private $container;
    private $entityManager;
    private $eventMap = [
        'ticket.created' => 'ticket.created',
        'ticket.deleted' => 'ticket.deleted',
        'ticket.thread.updated' => 'ticket.threadUpload',
        'ticket.priority.updated' => 'ticket.priority',
        'ticket.status.updated' => 'ticket.status',
        'ticket.type.updated' => 'ticket.type',
        'ticket.group.updated' => 'ticket.group',
        'ticket.team.updated' => 'ticket.team',
        'ticket.agent.updated' => 'ticket.agent',
        'ticket.collaborator.added' => 'ticket.collaboratorAdded',
        'ticket.note.added' => 'ticket.note',
        'ticket.reply.added.agent' => 'ticket.replyAgent',
        'ticket.reply.added.customer' => 'ticket.replyCustomer',
        'ticket.reply.added.collaborator' => 'ticket.replyByCollaborator',

        'task.created' => 'task.created',
        'task.updated' => 'task.updated',
        'task.deleted' => 'task.deleted',
        'task.member.added' => 'task.memberAdded',
        'task.member.deleted' => 'task.memberRemoved',
        'task.reply.added' => 'task.reply',

        'agent.created' => 'agent.created',
        'agent.updated' => 'agent.updated',
        'agent.deleted' => 'agent.deleted',
        'agent.forgot.password' => 'agent.forgotPassword',

        'customer.created' => 'customer.created',
        'customer.updated' => 'customer.updated',
        'customer.deleted' => 'customer.deleted',
        'customer.forgot.password' => 'customer.forgotPassword',
    ];

    public function __construct(ContainerInterface $container, EntityManager $entityManager)
    {
        $this->container = $container;
        $this->entityManager = $entityManager;
    }

    public function executeWorkflow(GenericEvent $event)
    {
        dump($event->getSubject());
        $workflowCollection = $this->entityManager->getRepository('UVDeskAutomationBundle:Workflow')->getEventWorkflows($event->getSubject());

        if (!empty($workflowCollection)) {
            foreach ($workflowCollection as $workflow) {
                $totalConditions = 0;
                $totalEvaluatedConditions = 0;

                foreach ($this->evaluateWorkflowConditions($workflow) as $workflowCondition) {
                    dump($workflowCondition);
                    // $totalEvaluatedConditions++;

                    // if (isset($workflowCondition['type']) && $this->checkCondition($workflowCondition)) {
                    //     $totalConditions++;
                    // }

                    // if (isset($workflowCondition['or'])) {
                    //     foreach ($workflowCondition['or'] as $orCondition) {
                    //         $flag = $this->checkCondition($orCondition);
                    //         if ($flag) {
                    //             $totalConditions++;
                    //         }
                    //     }
                    // }
                }

                dump($totalEvaluatedConditions > 0 || $totalConditions >= $totalEvaluatedConditions);
                die;

                // if ($totalEvaluatedConditions > 0 || $totalConditions >= $totalEvaluatedConditions) {
                //     $this->applyResponse($event , $this->object);
                // }
            }
        }

        die;
    }

    private function evaluateWorkflowConditions(Workflow $workflow)
    {
        $index = -1;
        $workflowConditions = [];

        foreach ($workflow->getConditions() as $condition) {
            if (!empty($condition['operation']) && $condition['operation'] != "&&") {
                if (!isset($finalConditions[$index]['or'])) {
                    $finalConditions[$index]['or'] = [];
                }

                $workflowConditions[$index]['or'][] = $condition;
            } else {
                $index++;
                $workflowConditions[] = $condition;
            }
        }

        return $workflowConditions;
    }
}
