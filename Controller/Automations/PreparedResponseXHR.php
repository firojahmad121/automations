<?php

namespace Webkul\UVDesk\AutomationBundle\Controller\Automations;

use Doctrine\Common\Collections\Criteria;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Routing\Router;
use Webkul\UVDesk\AutomationBundle\Form;
use Webkul\UVDesk\AutomationBundle\Entity;
use Symfony\Component\Security\Core\SecurityContextInterface;


class PreparedResponseXHR extends Controller
{
    const ROLE_REQUIRED_MANUAL = 'ROLE_AGENT_MANAGE_WORKFLOW_MANUAL';
    const LIMIT = 20;
    const WORKFLOW_MANUAL = 0;
    const WORKFLOW_AUTOMATIC = 1;
    const NAME_LENGTH = 100;
    const DESCRIPTION_LENGTH = 200;

    public function prepareResponseListXhr(Request $request)
    {
        if(!$this->get('user.service')->checkPermission('ROLE_AGENT_MANAGE_WORKFLOW_MANUAL'))
        {          
            return $this->redirect($this->generateUrl('helpdesk_member_dashboard'));
            exit;
        }
        $json = [];
        $repository = $this->getDoctrine()->getRepository('UVDeskAutomationBundle:PreparedResponses');
        $json = $repository->getPreparesResponses($request->query, $this->container);
        $response = new Response(json_encode($json));
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }

    public function prepareResponseDeleteXhr(Request $request)
    {
        if(!$this->get('user.service')->checkPermission('ROLE_AGENT_MANAGE_WORKFLOW_MANUAL'))
        {          
            return $this->redirect($this->generateUrl('helpdesk_member_dashboard'));
            exit;
        }
        $json = [];      
        if($request->getMethod() == "DELETE") {
            $em = $this->getDoctrine()->getManager();
            $id = $request->attributes->get('id');
            $preparedResponses = $em->getRepository('UVDeskAutomationBundle:PreparedResponses')->find($id);

            $em->remove($preparedResponses);
            $em->flush();

            $json['alertClass'] = 'success';
            $json['alertMessage'] = 'Success ! Prepared response removed successfully.';
        }

        $response = new Response(json_encode($json));
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }
}
