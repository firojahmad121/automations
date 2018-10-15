<?php

namespace Webkul\UVDesk\AutomationBundle\Controller\Automations;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Webkul\UVDesk\AutomationBundle\Form\DefaultForm;
use Webkul\UVDesk\AutomationBundle\Entity;

class Workflow extends Controller
{

    const ROLE_REQUIRED_AUTO = 'ROLE_AGENT_MANAGE_WORKFLOW_AUTOMATIC';
    const ROLE_REQUIRED_MANUAL = 'ROLE_AGENT_MANAGE_WORKFLOW_MANUAL';
    const LIMIT = 20;
    const WORKFLOW_MANUAL = 0;
    const WORKFLOW_AUTOMATIC = 1;
    const NAME_LENGTH = 100;
    const DESCRIPTION_LENGTH = 200;
    
    public function listWorkflowCollection(Request $request)
    {
        return $this->render('@UVDeskAutomation//Default//workflowList.html.twig');
    }

    // Creating workflow
    public function createWorkflow(Request $request)
    {
        $error = $formData = $formerror = [];
        $entityManager = $this->getDoctrine()->getManager();

        $workflowEventType = (!empty($workflow) && $workflow->getWorkflowEvents()[0]) ? current(explode('.', $workflow->getWorkflowEvents()[0]->getEvent())) : false;

        $form = $this->createForm(DefaultForm::class);

        if($request->request->all()) {
            $form->submit($request->request->all());
        }

        if ($form->isSubmitted()) {
            // if(!$request->attributes->get('id')) {
            //     try {
            //         $this->isAuthorized('workflows_addaction');
            //     } catch(\Exception $e) {
            //         $this->addFlash('warning', $this->get('translator')->trans('Warning! Upgrade plan to use workflows.') );

            //     return $this->redirectToRoute('workflows_action');
            //     }
            // }

            $formData = $request->request;
            $workflowClass = 'Webkul\UVDesk\AutomationBundle\Entity\Workflow';
            $workflowActionsArray = $request->request->get('actions');

            
            // if (!trim($formData->get('name')) || (strlen($formData->get('name')) > self::NAME_LENGTH)) {
            //     $error['name'] = $this->translate('Warning! Please add valid Name! Length must not be greater than %length%', ['%length%' => self::NAME_LENGTH]);
            // }
            if (strlen($formData->get('description')) > self::DESCRIPTION_LENGTH) {
                $error['description'] = $this->translate('Warning! Please add valid Description! Length must not be greater than %desc%', ['%desc%' => self::DESCRIPTION_LENGTH]);
            }

            if (!empty($workflowActionsArray)) {
                foreach ($workflowActionsArray as $key => $action) {
                    if (!$action['type']) {
                        unset($workflowActionsArray[$key]);
                    }
                }
            }

            if (empty($workflowActionsArray)) {
                $error['actions'] = $this->translate('Warning! Please add valid Actions!');
            }

            // Remove blank values from arrays
            $workflowEventsArray = $request->request->get('events');
            if (!empty($workflowEventsArray)) {
                foreach ($workflowEventsArray as $key => $event) {
                    if (!$event['event']) {
                        unset($workflowEventsArray[$key]);
                    }
                }
            }

            if (empty($workflowEventsArray)) {
                $error['events'] = $this->translate('Warning! Please add valid Events!');
            }

            $workflowConditionsArray = $request->request->get('conditions');
            if ($workflowConditionsArray) {
                foreach ($workflowConditionsArray as $key => $condition) {
                    if (!$condition['type']) {
                        unset($workflowConditionsArray[$key]);
                    }
                }
            }

            // if ($workflowEventType && array_values($workflowEventsArray)[0]['event'] != $workflowEventType) {
            //     if ($this->get('user.service')->getCurrentPlan()->getSKU() == 'free') {
            //         $error['events'] = $this->translate('Warning! In Free Plan you can not change Events!');
            //     }
            // }

            if (empty($error)) {
                // Check if new workflow and old one belong to the same class
                if (!empty($workflow) && $workflow instanceof $workflowClass) {
                    $newWorkflow = $workflow;
                } else {
                    $newWorkflow = new $workflowClass;
                    if (!empty($workflow)) {
                        $entityManager->remove($workflow);
                        $entityManager->flush();
                    }
                }

                $newWorkflow->setName($formData->get('name'));
                $newWorkflow->setDescription($formData->get('description'));
                $newWorkflow->setStatus($formData->get('status') == 'on' ? true : false);
                $newWorkflow->setActions($workflowActionsArray);
                $newWorkflow->setDateAdded(new \Datetime);
                $newWorkflow->setDateUpdated(new \Datetime);
                

                $formDataGetEvents = array_unique($formData->get('events'), SORT_REGULAR);
                if ($newWorkflow->getWorkflowEvents()) {
                    foreach ($newWorkflow->getWorkflowEvents() as $newWorkflowEvent) {
                        if ($thisKey = array_search(['event' => current($exNewEventEvent = explode('.', $newWorkflowEvent->getEvent())), 'trigger' => end($exNewEventEvent)], $formDataGetEvents)) {
                            unset($formDataGetEvents[$thisKey]);
                        } else {
                            $entityManager->remove($newWorkflowEvent);
                            $entityManager->flush();
                        }
                    }
                }

                $newWorkflow->setConditions($workflowConditionsArray);

                $entityManager->persist($newWorkflow);
                $entityManager->flush();

                foreach ($formDataGetEvents as $eventEvents) {
                    $event = new Entity\WorkflowEvents;
                    $event->setEvent($eventEvents['event'] . '.' . $eventEvents['trigger']);
                    $event->setWorkflow($newWorkflow);
                    $event->setEventId($newWorkflow->getId());
                    $entityManager->persist($event);
                    $entityManager->flush();
                }

                $this->addFlash('success', $request->attributes->get('id')
                    ? $this->get('translator')->trans('Success! Workflow has been updated successfully.')
                    :  $this->get('translator')->trans('Success! Workflow has been added successfully.')
                );

                return $this->redirectToRoute('helpdesk_member_workflow_collection');
            }

            $formData = [
                'type' => $request->request->get('type'),
                'name' => $request->request->get('name'),
                'description' => $request->request->get('description'),
                'status' => $request->request->get('status'),
                'events' => $request->request->get('events'),
                'actions' => $request->request->get('actions'),
                'conditions' => $request->request->get('conditions'),
            ];
        }
      
        return $this->render('@UVDeskAutomation//Default//createWorkflow.html.twig', array(
            'form' => $form->createView(),
            'error' => $error,
            'formerror' => $formerror,
            'formData' => $formData,
            //'list_items' => $this->getListItems($request),
            //'information_items' => $this->getRightSidebarInfoItems($request),
            //'workflowAction' => $this->get('user.service')->checkCompanyPermission('workflow')
        ));
    }

    public function editWorkflow(Request $request)
    {

        $error = $formData = $formerror = [];
        $entityManager = $this->getDoctrine()->getManager();

        if ($request->attributes->get('id')) {

            $workflow = $entityManager->getRepository('UVDeskAutomationBundle:Workflow')->findOneBy([
                'id' => $request->attributes->get('id'),
            ]);

            if (!empty($workflow)) {
                $formData = [
                    'type' => self::WORKFLOW_AUTOMATIC,
                    'name' => $workflow->getName(),
                    'description' => $workflow->getDescription(),
                    'status' => $workflow->getStatus(),
                    'actions' => $workflow->getActions(),
                    'conditions' => $workflow->getConditions(),
                    'events' => [],
                ];

                foreach ($workflow->getWorkflowEvents() as $events) {
                    $formData['events'][] = [
                        'event' => current($eventEx = explode('.', $events->getEvent())),
                        'trigger' => end($eventEx),
                    ];
                }
            } else {
                // Workflow not found
                $this->noResultFound();
            }
        }

        $workflowEventType = (!empty($workflow) && $workflow->getWorkflowEvents()[0]) ? current(explode('.', $workflow->getWorkflowEvents()[0]->getEvent())) : false;

        $form = $this->createForm(DefaultForm::class);

        if($request->request->all()) {
            $form->submit($request->request->all());
        }

        if ($form->isSubmitted()) {
            // if(!$request->attributes->get('id')) {
            //     try {
            //         $this->isAuthorized('workflows_addaction');
            //     } catch(\Exception $e) {
            //         $this->addFlash('warning', $this->get('translator')->trans('Warning! Upgrade plan to use workflows.') );

            //     return $this->redirectToRoute('workflows_action');
            //     }
            // }

            $formData = $request->request;
            $workflowClass = 'Webkul\UVDesk\AutomationBundle\Entity\Workflow';
            $workflowActionsArray = $request->request->get('actions');

            if (strlen($formData->get('description')) > self::DESCRIPTION_LENGTH) {
                $error['description'] = $this->translate('Warning! Please add valid Description! Length must not be greater than %desc%', ['%desc%' => self::DESCRIPTION_LENGTH]);
            }

            if (!empty($workflowActionsArray)) {
                foreach ($workflowActionsArray as $key => $action) {
                    if (!$action['type']) {
                        unset($workflowActionsArray[$key]);
                    }
                }
            }

            if (empty($workflowActionsArray)) {
                $error['actions'] = $this->translate('Warning! Please add valid Actions!');
            }

            // Check Authorization for Automatic Workflow
            // $this->isAuthorized(self::ROLE_REQUIRED_AUTO);

            // Remove blank values from arrays
            $workflowEventsArray = $request->request->get('events');
            if (!empty($workflowEventsArray)) {
                foreach ($workflowEventsArray as $key => $event) {
                    if (!$event['event']) {
                        unset($workflowEventsArray[$key]);
                    }
                }
            }

            if (empty($workflowEventsArray)) {
                $error['events'] = $this->translate('Warning! Please add valid Events!');
            }

            $workflowConditionsArray = $request->request->get('conditions');
            if ($workflowConditionsArray) {
                foreach ($workflowConditionsArray as $key => $condition) {
                    if (!$condition['type']) {
                        unset($workflowConditionsArray[$key]);
                    }
                }
            }

            // if ($workflowEventType && array_values($workflowEventsArray)[0]['event'] != $workflowEventType) {
            //     if ($this->get('user.service')->getCurrentPlan()->getSKU() == 'free') {
            //         $error['events'] = $this->translate('Warning! In Free Plan you can not change Events!');
            //     }
            // }

            if (empty($error)) {
                // Check if new workflow and old one belong to the same class
                if (!empty($workflow) && $workflow instanceof $workflowClass) {
                    $newWorkflow = $workflow;
                } else {
                    $newWorkflow = new $workflowClass;
                    if (!empty($workflow)) {
                        $entityManager->remove($workflow);
                        $entityManager->flush();
                    }
                }

                $newWorkflow->setName($formData->get('name'));
                $newWorkflow->setDescription($formData->get('description'));
                $newWorkflow->setStatus($formData->get('status') == 'on' ? true : false);
                $newWorkflow->setActions($workflowActionsArray);
                $newWorkflow->setDateAdded(new \Datetime);
                $newWorkflow->setDateUpdated(new \Datetime);
                

                $formDataGetEvents = array_unique($formData->get('events'), SORT_REGULAR);
                if ($newWorkflow->getWorkflowEvents()) {
                    foreach ($newWorkflow->getWorkflowEvents() as $newWorkflowEvent) {
                        if ($thisKey = array_search(['event' => current($exNewEventEvent = explode('.', $newWorkflowEvent->getEvent())), 'trigger' => end($exNewEventEvent)], $formDataGetEvents)) {
                            unset($formDataGetEvents[$thisKey]);
                        } else {
                            $entityManager->remove($newWorkflowEvent);
                            $entityManager->flush();
                        }
                    }
                }

                $newWorkflow->setConditions($workflowConditionsArray);

                $entityManager->persist($newWorkflow);
                $entityManager->flush();

                foreach ($formDataGetEvents as $eventEvents) {
                    $event = new Entity\WorkflowEvents;
                    $event->setEvent($eventEvents['event'] . '.' . $eventEvents['trigger']);
                    $event->setWorkflow($newWorkflow);
                    $event->setEventId($newWorkflow->getId());
                    $entityManager->persist($event);
                    $entityManager->flush();
                }

                $this->addFlash('success', $request->attributes->get('id')
                    ? $this->get('translator')->trans('Success! Workflow has been updated successfully.')
                    :  $this->get('translator')->trans('Success! Workflow has been added successfully.')
                );

                return $this->redirectToRoute('helpdesk_member_workflow_collection');
            }

            $formData = [
                'type' => $request->request->get('type'),
                'name' => $request->request->get('name'),
                'description' => $request->request->get('description'),
                'status' => $request->request->get('status'),
                'events' => $request->request->get('events'),
                'actions' => $request->request->get('actions'),
                'conditions' => $request->request->get('conditions'),
            ];
        }
      
        return $this->render('@UVDeskAutomation//Default//editWorkflow.html.twig', array(
            'form' => $form->createView(),
            'error' => $error,
            'formerror' => $formerror,
            'formData' => $formData,
            //'list_items' => $this->getListItems($request),
            //'information_items' => $this->getRightSidebarInfoItems($request),
        ));
    }

    //Remove Workflow
    public function deleteWorkflow(Request $request)
    {

    } 
    public function translate($string,$params = array())
    {
        return $this->get('translator')->trans($string,$params);
    }
}
