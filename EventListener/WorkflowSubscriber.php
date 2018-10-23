<?php

namespace Webkul\UVDesk\AutomationBundle\EventListener;

use Webkul\UVDesk\AutomationBundle\Event\ActivityEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

Class WorkflowSubscriber implements EventSubscriberInterface
{
    private $container;
    private $em;
    private $event;
    private $eventName;
    private $entity;
    private $targetEntity;
    private $user;
    private $userType;
    private $company;
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

    public static function getSubscribedEvents()
    {
        return [
            'ticket.created' => 'triggerWorkflow',
            'ticket.updated' => 'triggerWorkflow',
            'ticket.deleted' => 'triggerWorkflow',
            'ticket.reply.added' => 'replyAdded',
            'ticket.note.added' => 'triggerWorkflow',
            'ticket.forward.added' => 'forwardReply',
            'ticket.priority.updated' => 'triggerWorkflow',
            'ticket.group.updated' => 'triggerWorkflow',
            'ticket.team.updated' => 'triggerWorkflow',
            'ticket.agent.updated' => 'triggerWorkflow',
            'ticket.status.updated' => 'triggerWorkflow',
            'ticket.type.updated' => 'triggerWorkflow',
            'ticket.collaborator.added' => 'triggerWorkflow',
            'ticket.collaborator.deleted' => 'triggerWorkflow',
            'ticket.thread.updated' => 'triggerWorkflow',

            'task.created' => 'triggerWorkflow',
            'task.updated' => 'triggerWorkflow',
            'task.deleted' => 'triggerWorkflow',
            'task.reply.added' => 'triggerWorkflow',
            'task.member.added' => 'triggerWorkflow',
            'task.member.deleted' => 'triggerWorkflow',

            'agent.created' => 'triggerWorkflow',
            'agent.updated' => 'triggerWorkflow',
            'agent.deleted' => 'triggerWorkflow',
            'agent.forgot.password' => 'triggerWorkflow',

            'customer.created' => 'triggerWorkflow',
            'customer.updated' => 'triggerWorkflow',
            'customer.deleted' => 'triggerWorkflow',
            'customer.forgot.password' => 'triggerWorkflow',
        ];
    }

    private function setEvent($event) {
        $this->container = $event->getContainer();
        $this->em = $this->container->get('doctrine.orm.entity_manager');
        $this->event = $event;
        $this->eventName = $event->getEventName();
        $this->entity = $event->getEntity();
        $this->targetEntity = $event->getTargetEntity();
        $this->user = $event->getCurrentUser();
        $this->userType = $event->getUserType();
    }

    public function triggerWorkflow(ActivityEvent $event)
    {
        dump($event);
        die;

        $this->setEvent($event);
        $workflowService = $this->container->get('workflow.service');
        if($placeholdes = $event->getNotePlaceholders())
            $workflowService->notePlaceholders = $placeholdes;

        if (!empty($this->eventMap[$this->eventName])) {
            $workflowService->trigger($this->eventMap[$this->eventName], $this->entity);
        }
    }

    public function replyAdded(ActivityEvent $event) {
        $this->setEvent($event);
        $event = "";

        if($this->userType == 'agent') {
            $event = $this->eventMap[$this->eventName.'.'.$this->userType];
        } else {
            if($this->entity->getCustomer() == $this->user)
                $event = $this->eventMap[$this->eventName.'.customer'];
            else
                $event = $this->eventMap[$this->eventName.'.collaborator'];
        }

        $this->container->get('workflow.service')->trigger($event, $this->entity);
    }

    public function forwardReply(ActivityEvent $event) {
        
        $this->setEvent($event);
        $createThread = $this->container->get('ticket.service')->getCreateReply($this->entity->getId(), false);
        $mailData = array(
                        'email' => $this->targetEntity->getReplyTo(),
                        'subject' => $event->getSubject() ?: 'Frw: '.$this->entity->getSubject(),
                        'message' => $this->targetEntity->getReply(),
                        'references' => $createThread['messageId']
                    );
        if($this->entity->getMailbox())
            $mailData['replyTo'] = $this->entity->getUniqueReplyTo();
        if($this->targetEntity->getCc())
            $mailData['cc'] = $this->targetEntity->getCc();
        if($this->targetEntity->getBcc())
            $mailData['bcc'] = $this->targetEntity->getBcc();

        $workflowService = $this->container->get('workflow.service');
        $workflowService->object = $this->entity;
        $workflowService->eventType = $this->eventName;
        $workflowService->attachmentPlaceholderFlag['ticket.attachments'] = 1;
        $workflowService->sendMail($mailData);
    }
}
