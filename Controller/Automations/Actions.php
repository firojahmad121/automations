<?php
namespace Webkul\UVDesk\AutomationBundle\Controller\Automations;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Routing\Router;

class Actions extends Controller
{    
    const ROLE_REQUIRED_AUTO = 'ROLE_AGENT_MANAGE_WORKFLOW_AUTOMATIC';
    const ROLE_REQUIRED_MANUAL = 'ROLE_AGENT_MANAGE_WORKFLOW_MANUAL';

    public function trans($text)
    {
        return $this->container->get('translator')->trans($text);
    }

    public function getEntity(Request $request) 
    {

        $json = '{}';
        $results = $jsonResults = $partResults = array();
        $error = false;
        // try {
        //     $this->isAuthorized(self::ROLE_REQUIRED_MANUAL);
        // } catch(\Exception $e) {
        //     $this->isAuthorized(self::ROLE_REQUIRED_AUTO);
        // }

        if($request->isXmlHttpRequest()) {
            if($request->getMethod() == 'GET' && (in_array($request->attributes->get('entity'), [
                'TicketPriority', 'TicketType', 'TicketStatus', 
                'tag', 'cc', 'bcc', // 'note',
                'assign_agent', 'assign_group', 'assign_team',
                'mail_agent', 'mail_group', 'mail_team', 'mail_customer',
                'mail_members',
                'ticket_transfer', 'task_transfer',
                'mail_last_member',
                'mail_last_collaborator'
                //'delete_ticket', 'mark_spam',
                ]) || ($this->isEcommerceEntity($request->attributes->get('entity')) )) ) {
                
                switch ($request->attributes->get('entity')) {
                    case 'TicketStatus':
                        $results = $this->getDoctrine()
                                        ->getRepository('UVDeskCoreBundle:'.ucfirst($request->attributes->get('entity')))
                                        ->findBy(
                                            array(
                                                    // 'companyId' => $this->getCurrentCompany()->getId()
                                                )
                                        );
                      
                        foreach ($results as $key => $result) {
                            $jsonResults[] = [
                                        'id' => $result->getId(),
                                        'name' => $result->getCode(),
                                     ];
                        }
                        $json = json_encode($jsonResults);
                        $results = [];
                        break;
                    case 'TicketPriority':
                        $results = $this->getDoctrine()
                                        ->getRepository('UVDeskCoreBundle:'.ucfirst($request->attributes->get('entity')))
                                        ->findBy(
                                            array(
                                                    // 'companyId' => $this->getCurrentCompany()->getId()
                                                )
                                        );

                        foreach ($results as $key => $result) {
                            $jsonResults[] = [
                                        'id' => $result->getId(),
                                        'name' => $this->trans($result->getCode()),
                                     ];
                        }
                        $json = json_encode($jsonResults);
                        $results = [];
                        break;
                    case 'TicketType':
                        $results = $this->container->get('ticket.service')->getTypes();
                        break;
                    case 'assign_group':
                        $results = $this->container->get('user.service')->getSupportGroups();
                        break;
                    case 'assign_team':
                        $results = $this->container->get('user.service')->getSupportTeams();
                        break;
                    case 'ticket_transfer':
                    case 'task_transfer':
                    case 'assign_agent':
                        $results = $this->container->get('user.service')->getAgentsPartialDetails();
                        $jsonResults[] = ['id' => $userId = $this->getUser()->getId(), 'name' => $this->trans('me')];
                        $jsonResults[] = ['id' => 'responsePerforming', 'name' => $this->trans('action.responsePerforming.agent')];
                        foreach ($results as $key => $result) {
                            if($userId != $result['id'])
                                $jsonResults[] = [
                                            'id'    => $result['id'],
                                            'name'  => $result['name'],
                                         ];
                        }
                        $json = json_encode($jsonResults);
                        $results = [];
                        break;
                    case 'cc':
                    case 'bcc':
                        $results = $this->container->get('user.service')->getCustomersPartial();
                        $jsonResults[] = ['id' => 'responsePerforming', 'name' => $this->trans('action.responsePerforming.agent')];
                        foreach ($results as $key => $result) {
                            $jsonResults[] = [
                                        'id' => $result['email'],
                                        'name' => $result['email'],
                                     ];
                        }
                        $json = json_encode($jsonResults);
                        $results = [];
                        break;
                    case 'mail_customer':
                    case 'mail_agent':
                    case 'mail_group':
                    case 'mail_team':
                    case 'mail_members':
                    case 'mail_last_member':
                    case 'mail_last_collaborator':
                        $currentPlan = $this->get('user.service')->getCurrentPlan();
                        
                        $filter = array();
                        if($currentPlan && $currentPlan->getWorkflow() == 'predefind')
                            $filter['isPredefind'] = 1;
                        $results = $this->getDoctrine()
                                        ->getRepository('UVDeskCoreBundle:EmailTemplates')
                                        ->findBy($filter);
                                   
                        $emailTemplates = $json = [];
                        foreach ($results as $key => $result) {
                            $emailTemplates[] = [
                                'id' => $result->getId(),
                                'name' => $result->getName(),
                            ];
                        }
                        switch ($request->attributes->get('entity')) {
                            case 'mail_agent':
                                $agentResults = $this->container->get('user.service')->getAgentsPartialDetails();
                                $partResults[] = ['id' => $userId = $this->getUser()->getId(), 'name' => $this->trans('me')];
                                $partResults[] = ['id' => 'responsePerforming', 'name' => $this->trans('action.responsePerforming.agent')];
                                if(in_array($request->attributes->get('event'), ['ticket', 'task'])){
                                    $partResults[] = ['id' => 'assignedAgent', 'name' => $this->trans('action.assign.agent')];
                                }elseif(in_array($request->attributes->get('event'), ['agent'])){
                                    $partResults[] = ['id' => 'baseAgent', 'name' => $this->trans('action.created.agent')];
                                }
                                foreach ($agentResults as $key => $agentResult) {
                                    if($userId != $agentResult['id'])
                                        $partResults[] = [
                                                    'id' => $agentResult['id'],
                                                    'name' => $agentResult['name'],
                                                 ];
                                }
                                break;
                            case 'mail_group':
                                $groupResults = $this->container->get('user.service')->getSupportGroups();
                                $partResults[] = ['id' => 'assignedGroup', 'name' => $this->trans('action.ticketAssign.group')];
                                foreach ($groupResults as $groupResult) {
                                        $partResults[] = [
                                                    'id' => $groupResult['id'],
                                                    'name' => $groupResult['name'],
                                                 ];
                                }
                                break;
                            case 'mail_team':
                                $teamResults = $this->container->get('user.service')->getSupportTeams();
                                $partResults[] = ['id' => 'assignedTeam', 'name' => $this->trans('action.ticketAssign.team')];
                                foreach ($teamResults as $teamResult) {
                                        $partResults[] = [
                                                    'id' => $teamResult['id'],
                                                    'name' => $teamResult['name'],
                                                 ];
                                }
                                break;
                        }
                        if(in_array($request->attributes->get('entity'), ['mail_agent', 'mail_group', 'mail_team'])){
                            $json['templates'] = $emailTemplates;
                            $json['partResults'] = $partResults;
                            $json = json_encode($json);
                        }else
                            $json = json_encode($emailTemplates);
                        $results = [];
                        break;
                    case 'tag':
                        $results = $this->getDoctrine()
                                        ->getRepository('UVDeskCoreBundle:'.ucfirst($request->attributes->get('entity')))
                                        ->findBy(
                                            array(
                                                   // 'company' => $this->getCurrentCompany()->getId(),
                                                )
                                        );
                        break;
                    case $this->isEcommerceEntity($request->attributes->get('entity') ) ? true : false:
                        $customFields = $this->container->get('customfield.service')->getCustomFieldsArray('both');
                        $results = [];
                        foreach($customFields as $customField) {
                            $results[] = [
                                'id' => $customField['id'],
                                'name' => $this->trans('Value of field: ') . $customField['name'],
                            ];
                        }
                        break;
                    default:
                        $json = '{}';
                        break;
                }
                // if($partResults)
                    // $json = json_encode($partResults);
                if($results){
                    
                    $ignoredArray = ['description', 'color', 'company', 'createdAt', 'users', 'isActive',
                                     'companyId', 'subject', 'message', 'templateId', 'appEmailTemplates', 
                                     'tickets', 'subGroups', 'user', 'isPredefind'
                                    ];
                    // $json = [['id' => 'responsePerforming', 'name' => $this->trans('action.responsePerforming.agent')]];
                    $json = $this->get('default.service')->getSerializeObj($ignoredArray)->serialize($results, 'json');
                }
            } else {
                $error = true;
            }
        }

        if($error){
            $json = [];
            $json['alertClass'] = 'danger';
            $json['alertMessage'] = $this->get('translator')->trans('Warning! You are not allowed to perform this action.');
            $json = json_encode($json);
        }

        $response = new Response($json);
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }

    protected function isEcommerceEntity($entity)
    {
        return 0 === strpos($entity, 'add_order_to_' );
    }
}
