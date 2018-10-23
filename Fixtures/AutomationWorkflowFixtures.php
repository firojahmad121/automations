<?php

namespace Webkul\UVDesk\AutomationBundle\Fixtures;

use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\Bundle\FixturesBundle\Fixture as DoctrineFixture;
use Webkul\UVDesk\AutomationBundle\Entity as AutomationEntities;

class AutomationWorkflowFixtures extends DoctrineFixture
{
    private static $seeds = [
        [
            'name' => 'Ticket reply by agent',
            'description' => 'Ticket Reply added by agent',
            'conditions' => 'N;',
            'actions' => 'a:1:{i:0;a:2:{s:4:"type";s:13:"mail_customer";s:5:"value";s:5:"ARTCT";}}',
            'emailTemplate' => 'ARTCT',
            'status' => '1',
            'sort_order' => '8',
            'events' => ['ticket.replyAgent']
        ],
        [
            'name' => 'Ticket reply by customer',
            'description' => 'Ticket reply by customer ',
            'conditions' => 'N;',
            'actions' => 'a:1:{i:0;a:2:{s:4:"type";s:10:"mail_agent";s:5:"value";a:2:{s:3:"for";a:1:{i:0;s:13:"assignedAgent";}s:5:"value";s:3:"CRT";}}}',
            'emailTemplate' => 'CRT',
            'status' => '1',
            'sort_order' => '9',
            'events' => ['ticket.replyCustomer']
        ],
        [
            'name' => 'Account validation of customer ',
            'description' => 'Account validation of customer ',
            'conditions' => 'N;',
            'actions' => 'a:1:{i:0;a:2:{s:4:"type";s:13:"mail_customer";s:5:"value";s:2:"AA";}}',
            'emailTemplate' => 'AA',
            'status' => '1',
            'sort_order' => '14',
            'events' => ['customer.created']
        ],
        [
            'name' => 'Account activation of agent',
            'description' => 'Account activation of agent',
            'conditions' => 'N;',
            'actions' => 'a:1:{i:0;a:2:{s:4:"type";s:10:"mail_agent";s:5:"value";a:2:{s:3:"for";a:1:{i:0;s:9:"baseAgent";}s:5:"value";s:2:"AA";}}}',
            'emailTemplate' => 'AA',
            'status' => '1',
            'sort_order' => '13',
            'events' => ['agent.created']
        ],
        [
            'name' => 'Member added in task',
            'description' => 'Member added in task',
            'conditions' => 'N;',
            'actions' => 'a:1:{i:0;a:2:{s:4:"type";s:12:"mail_members";s:5:"value";s:3:"MAT";}}',
            'emailTemplate' => 'MAT',
            'status' => '1',
            'sort_order' => '6',
            'events' => ['task.memberAdded']
        ],
        [
            'name' => 'Customer Forgot password ',
            'description' => 'Customer Forgot password ',
            'conditions' => 'N;',
            'actions' => 'a:1:{i:0;a:2:{s:4:"type";s:13:"mail_customer";s:5:"value";s:3:"CFP";}}',
            'emailTemplate' => 'CFP',
            'status' => '1',
            'sort_order' => '11',
            'events' => ['customer.forgotPassword']
        ],
        [
            'name' => 'Collaborator Added In A Ticket',
            'description' => 'Collaborator Added In A Ticket',
            'conditions' => 'N;',
            'actions' => 'a:1:{i:0;a:2:{s:4:"type";s:10:"mail_agent";s:5:"value";a:2:{s:3:"for";a:1:{i:0;s:13:"assignedAgent";}s:5:"value";s:4:"CATT";}}}',
            'emailTemplate' => 'CATT',
            'status' => '1',
            'sort_order' => '1',
            'events' => ['ticket.collaboratorAdded']
        ],
        [
            'name' => 'Collaborator Added A Reply',
            'description' => 'Reply added by collaborator',
            'conditions' => 'N;',
            'actions' => 'a:2:{i:0;a:2:{s:4:"type";s:10:"mail_agent";s:5:"value";s:3:"CAR";}i:1;a:2:{s:4:"type";s:13:"mail_customer";s:5:"value";s:3:"CAR";}}',
            'emailTemplate' => 'CAR',
            'status' => '1',
            'sort_order' => '2',
            'events' => ['ticket.replyByCollaborator']
        ],
        [
            'name' => 'Task Created',
            'description' => 'A new task assigned to you.',
            'conditions' => 'N;',
            'actions' => 'a:1:{i:0;a:2:{s:4:"type";s:10:"mail_agent";s:5:"value";a:2:{s:3:"for";a:1:{i:0;s:13:"assignedAgent";}s:5:"value";s:2:"TC";}}}',
            'emailTemplate' => 'TC',
            'status' => '1',
            'sort_order' => '7',
            'events' => ['task.created']
        ],
        [
            'name' => 'Member Reply In task',
            'description' => 'Member Reply In task',
            'conditions' => 'N;',
            'actions' => 'a:1:{i:0;a:2:{s:4:"type";s:12:"mail_members";s:5:"value";s:3:"MRT";}}',
            'emailTemplate' => 'MRT',
            'status' => '1',
            'sort_order' => '5',
            'events' => ['task.reply']
        ],
        [
            'name' => 'Agent Forgot Password',
            'description' => 'Agent Forgot Password. ',
            'conditions' => 'N;',
            'actions' => 'a:1:{i:0;a:2:{s:4:"type";s:10:"mail_agent";s:5:"value";a:2:{s:3:"for";a:1:{i:0;s:9:"baseAgent";}s:5:"value";s:3:"AFP";}}}',
            'emailTemplate' => 'AFP',
            'status' => '1',
            'sort_order' => '12',
            'events' => ['agent.forgotPassword']
        ],
        [
            'name' => 'Assign ticket to Admin when ticket generated (for starting, please update this) ',
            'description' => 'Ticket generated by customer ',
            'conditions' => 'N;',
            'actions' => 'a:1:{i:0;a:2:{s:4:"type";s:12:"assign_agent";s:5:"value";s:1:"%";}}',
            'emailTemplate' => false,
            'assign_agent' => true,
            'status' => '1',
            'sort_order' => NULL,
            'events' => ['ticket.created']
        ],
        [
            'name' => 'Ticket Assign Mail',
            'description' => 'Ticket Assign Mail',
            'conditions' => 'N;',
            'actions' => 'a:1:{i:0;a:2:{s:4:"type";s:10:"mail_agent";s:5:"value";a:2:{s:3:"for";a:1:{i:0;s:13:"assignedAgent";}s:5:"value";s:2:"TA";}}}',
            'emailTemplate' => 'TA',
            'status' => '1',
            'sort_order' => NULL,
            'events' => ['ticket.agent']
        ],
        [
            'name' => 'Ticket generated by customer',
            'description' => 'Ticket generated by customer',
            'conditions' => 'N;',
            'actions' => 'a:1:{i:0;a:2:{s:4:"type";s:13:"mail_customer";s:5:"value";s:4:"TGBC";}}',
            'emailTemplate' => 'TGBC',
            'status' => '1',
            'sort_order' => NULL,
            'events' => ['ticket.created']
        ],
        [
            'name' => 'Mail when collaborator added',
            'description' => 'Mail when collaborator added',
            'conditions' => 'N;',
            'actions' => 'a:1:{i:0;a:2:{s:4:"type";s:22:"mail_last_collaborator";s:5:"value";s:3:"MTC";}}',
            'emailTemplate' => 'MTC',
            'status' => '1',
            'sort_order' => NULL,
            'events' => ['ticket.collaboratorAdded']
        ],
        [
            'name' => 'Transfer tickets from deleted agent',
            'description' => 'Transfer tickets from deleted agent',
            'conditions' => 'N;',
            'actions' => 'a:1:{i:0;a:2:{s:4:"type";s:15:"ticket_transfer";s:5:"value";s:18:"responsePerforming";}}',
            'emailTemplate' => false,
            'status' => '1',
            'sort_order' => NULL,
            'events' => ['agent.deleted']
        ],
        [
            'name' => 'Ticket reply by agent - update ticket status',
            'description' => 'Ticket reply by agent - update ticket status',
            'conditions' => 'a:2:{i:0;a:3:{s:4:"type";s:6:"status";s:5:"match";s:5:"isNot";s:5:"value";s:1:"6";}i:2;a:4:{s:4:"type";s:6:"status";s:5:"match";s:5:"isNot";s:9:"operation";s:2:"&&";s:5:"value";s:1:"5";}}',
            'actions' => 'a:1:{i:0;a:2:{s:4:"type";s:6:"status";s:5:"value";s:1:"6";}}',
            'emailTemplate' => false,
            'status' => '1',
            'sort_order' => NULL,
            'events' => ['ticket.replyAgent']
        ],
        [
            'name' => 'Ticket reply by customer - update ticket status',
            'description' => 'Ticket reply by customer - update ticket status',
            'conditions' => 'a:2:{i:0;a:3:{s:4:"type";s:6:"status";s:5:"match";s:5:"isNot";s:5:"value";s:1:"1";}i:1;a:4:{s:4:"type";s:6:"status";s:5:"match";s:5:"isNot";s:9:"operation";s:2:"&&";s:5:"value";s:1:"5";}}',
            'actions' => 'a:1:{i:0;a:2:{s:4:"type";s:6:"status";s:5:"value";s:1:"1";}}',
            'emailTemplate' => false,
            'status' => '1',
            'sort_order' => NULL,
            'events' => ['ticket.replyCustomer','ticket.replyByCollaborator']
        ]
    ];

    public function load(ObjectManager $entityManager)
    {
        $availableWorkflows = $entityManager->getRepository('UVDeskAutomationBundle:Workflow')->findAll();

        if (empty($availableWorkflows)) {
            foreach (self::$seeds as $baseEvent) {

                $workflow = new AutomationEntities\Workflow();
                $workflow->setConditions([]);
                $workflow->setDateAdded(new \DateTime);
                $workflow->setDateUpdated(new \DateTime);
                $workflow_actions = unserialize($baseEvent['actions']);

                // $companyTemplate = $this->container->get('email.service')->getEmailTemplate($companyBaseEvent['emailTemplate'], $company->getId());
    
                // $emailTemplateId = null;
                // if ($companyBaseEvent['emailTemplate'] && !empty($companyTemplate)){
                //     $emailTemplateId = $companyTemplate->getId();
                // }


                foreach ($workflow_actions as $key => $action) {
                    if (1 == 1){
                        $workflow_actions[$key]['value'] = 3;
                    } 
                }

                // foreach ($workflow_actions as $key => $action) {
                //     if (isset($action['type']) && $action['type'] == 'assign_agent'){
                //         $workflow_actions[$key]['value'] = $user->getId();
                //     } 
                //     elseif (!empty($emailTemplateId)) {
                //         if (is_array($action['value'])) {
                //             $workflow_actions[$key]['value']['value'] = $emailTemplateId;
                //         } else {
                //             $workflow_actions[$key]['value'] = $emailTemplateId;
                //         }
                //     }
                // }
    
                $workflow->setIsPredefind(true);
                $workflow->setActions($workflow_actions);
                $workflow->setName($baseEvent['name']);
                $workflow->setStatus($baseEvent['status']);
                $workflow->setSortOrder($baseEvent['sort_order']);
                $workflow->setDescription($baseEvent['description']);
                
                $entityManager->persist($workflow);
                $entityManager->flush();
    
                foreach ($baseEvent['events'] as $eventValue) {
                    $eventObj = new AutomationEntities\WorkflowEvents();
                    $eventObj->setEventId($workflow->getId());
                    $eventObj->setEvent($eventValue);
                    $eventObj->setWorkflow($workflow);
                    $entityManager->persist($eventObj);
                }
                $entityManager->flush();
            }
        }
    }
}