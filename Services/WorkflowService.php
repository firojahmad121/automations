<?php

namespace Webkul\UVDesk\AutomationBundle\Services;

use Doctrine\ORM\EntityManager;
use Doctrine\Common\Collections\Criteria;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Webkul\UVDesk\CoreBundle\Entity\Ticket;

class WorkflowService
{
    private $request;
    private $container;
    private $em;
    public $object;
    public $eventType = '';
    public $notePlaceholders = array();
    private $attachmentEvent = array('ticket.created','ticket.replyAgent','ticket.replyCustomer','ticket.replyByCollaborator','task.created','task.reply', 'ticket.forward.added');
    public $attachmentPlaceholderFlag = array('ticket.attachments' => 0,'task.attachments' => 0);
    private $SMTP_EMAIL = 'support@uvdesk.in';

    public function __construct(ContainerInterface $container, RequestStack $requestStack, EntityManager $entityManager)
    {
        $this->em = $entityManager;
        $this->container = $container;
        $this->request = $requestStack;
    }

    public function trigger($eventType, $object) {
        if($object instanceof Ticket && $object->getIsTrashed())
            return;

        $this->eventType = $eventType;
        $this->object = $object;
        $qb = $this->em->createQueryBuilder();
        $qb->select('wf')->from('UVDeskAutomationBundle:Workflow', 'wf');
        $qb->leftJoin('wf.workflowEvents', 'wfe');
        $qb->andwhere('wf.status = 1');
        $qb->andwhere('wf.isPredefind = 1');
        $qb->andwhere('wfe.event = :eventType');
        $qb->setParameter('eventType', $eventType);
        $qb->orderBy('wf.sortOrder', Criteria::ASC);

        if(count($getResult = $qb->getQuery()->getResult())) {
            foreach ($getResult as $event) {
                $count  = 0;
                $finalConditions = $this->getFinalConditions($event->getConditions());

                foreach ($finalConditions as $condition) {
                    if(isset($condition['type']))
                        if($this->checkCondition($condition))
                            $count++;

                    if(isset($condition['or'])) {
                        foreach ($condition['or'] as $orCondition) {
                            $flag = $this->checkCondition($orCondition);
                            if($flag)
                                $count++;
                        }
                    }
                }

                if(!count($finalConditions) || $count >= count($finalConditions))
                    $this->applyResponse($event , $this->object);
            }
        }

        // dump('here');
        // die;

        // /* cc bcc for replies*/
        // if(!empty($eventType) && in_array($eventType, ['ticket.replyAgent', 'ticket.replyCustomer']) && $object instanceof Ticket) {
        //     $this->sendCcBccMail($this->request->getCurrentRequest()->request->all(), $object);
        // }
        return;
    }

    public function getFinalConditions($conditions) {
        $finalConditions = array();
        if($conditions) {
            $tempKey = -1;
            foreach ($conditions as $key => $condition) {
                if(isset($condition['operation']) && $condition['operation'] != "&&") {
                    if(!isset($finalConditions[$tempKey]['or']))
                        $finalConditions[$tempKey]['or'] = array();
                    array_push($finalConditions[$tempKey]['or'], $condition);
                } else {
                    $tempKey++;
                    array_push($finalConditions, $condition);
                }
            }
        }

        return $finalConditions;
    }
    
    public function checkCondition($condition) {
        $flag = false;

        switch ($condition['type']) {
            case 'from_mail':
                if(isset($condition['value']) && $this->object instanceof Ticket)
                    $flag = $this->match($condition['match'],$this->object->getCustomer()->getEmail(),$condition['value']);
                break;
            case 'to_mail':
                if(isset($condition['value']) && $this->object instanceof Ticket && $this->object->getMailbox())
                    $flag = $this->match($condition['match'],$this->object->getMailbox()->getEmail(),$condition['value']);
                break;
            case 'subject':
                if(isset($condition['value']) && ($this->object instanceof Ticket || $this->object instanceof Task))
                    $flag = $this->match($condition['match'],$this->object->getSubject(),$condition['value']);
                break;
            case 'description':
                if(isset($condition['value']) && $this->object instanceof Ticket) {
                    $createThread = $this->container->get('ticket.service')->getCreateReply($this->object->getId(),false);
                    $createThread['reply'] = rtrim(strip_tags($createThread['reply']), "\n" );

                    $flag = $this->match($condition['match'],rtrim($createThread['reply']),$condition['value']);
                } elseif(isset($condition['value']) && $this->object instanceof Task) {
                    $createThread = $this->container->get('task.service')->getCreateThread($this->object->getId(),false);
                    $flag = $this->match($condition['match'],$createThread['description'],$condition['value']);
                }
                break;
            case 'subject_or_description':
                if(isset($condition['value']) && $this->object instanceof Ticket) {
                    $flag = $this->match($condition['match'],$this->object->getSubject(),$condition['value']);
                    $createThread = $this->container->get('ticket.service')->getCreateReply($this->object->getId(),false);
                    if(!$flag) {
                        $createThread = $this->container->get('ticket.service')->getCreateReply($this->object->getId(),false);
                        $createThread['reply'] = rtrim(strip_tags($createThread['reply']), "\n" );
                        $flag = $this->match($condition['match'],$createThread['reply'],$condition['value']);
                    }
                } elseif(isset($condition['value']) && $this->object instanceof Task) {
                    $flag = $this->match($condition['match'],$this->object->getSubject(),$condition['value']);
                    if(!$flag) {
                        $createThread = $this->container->get('ticket.service')->getCreateReply($this->object->getId(),false);
                        $flag = $this->match($condition['match'],$createThread['description'],$condition['value']);
                    }
                }

                break;
            case 'priority':
                if(isset($condition['value']) && ($this->object instanceof Ticket || $this->object instanceof Task))
                    $flag = $this->match($condition['match'],$this->object->getPriority()->getId(),$condition['value']);
                break;
            case 'type':
                if(isset($condition['value']) && $this->object instanceof Ticket) {
                    $typeId = $this->object->getType() ? $this->object->getType()->getId() : 0;
                    $flag = $this->match($condition['match'],$typeId,$condition['value']);
                }
                break;
            case 'status':
                if(isset($condition['value']) && $this->object instanceof Ticket)
                    $flag = $this->match($condition['match'],$this->object->getStatus()->getId(),$condition['value']);
                break;
            case 'stage':
                if(isset($condition['value']) && $this->object instanceof Task)
                    $flag = $this->match($condition['match'],$this->object->getStage()->getId(),$condition['value']);
                break;
            case 'source':
                if(isset($condition['value']) && $this->object instanceof Ticket)
                    $flag = $this->match($condition['match'],$this->object->getSource(),$condition['value']);
                break;
            case 'created':
                if(isset($condition['value']) && ($this->object instanceof Ticket || $this->object instanceof Task)) {
                    $date = date_format($this->object->getCreatedAt(),"d-m-Y h:ia");
                    $flag = $this->match($condition['match'],$date,$condition['value']);
                }
                break;
            case 'agent':
                if(isset($condition['value']) && $this->object instanceof Ticket && $this->object->getAgent())
                    $flag = $this->match($condition['match'], $this->object->getAgent()->getId(), (($condition['value'] == 'actionPerformingAgent') ? ($this->container->get('user.service')->getCurrentUser() ? $this->container->get('user.service')->getCurrentUser()->getId() : 0) : $condition['value']));
                break;
            case 'agent_name':
                if(isset($condition['value']) && $this->object instanceof Task && $this->object->getAssignedAgent()) {
                    $detail = $this->object->getAssignedAgent()->getDetail();
                    $name = $detail['agent']->getName();
                    $flag = $this->match($condition['match'],$name,$condition['value']);
                }
                break;
            case 'agent_email':
                if(isset($condition['value']) && $this->object instanceof Task && $this->object->getAssignedAgent())
                    $flag = $this->match($condition['match'],$this->object->getAssignedAgent()->getEmail(),$condition['value']);
                break;
            case 'group':
                if(isset($condition['value']) && $this->object instanceof Ticket)  {
                    $groupId = $this->object->getGroup() ? $this->object->getGroup()->getId() : 0;
                    $flag = $this->match($condition['match'],$groupId,$condition['value']);
                }
                break;
            case 'team':
                if(isset($condition['value']) && $this->object instanceof Ticket)  {
                    $subGroupId = $this->object->getSubGroup() ? $this->object->getSubGroup()->getId() : 0;
                    $flag = $this->match($condition['match'],$subGroupId,$condition['value']);
                }
                break;
            case 'customer_name':
                if(isset($condition['value']) && $this->object instanceof Ticket) {
                    $lastThread = $this->container->get('ticket.service')->getTicketLastThread($this->object->getId());
                    $flag = $this->match($condition['match'],$lastThread->getFullname(),$condition['value']);
                }
                break;
            case 'customer_email':
                if(isset($condition['value']) && $this->object instanceof Ticket) {
                    $flag = $this->match($condition['match'],$this->object->getCustomer()->getEmail(),$condition['value']);
                }
                break;
            case strpos($condition['type'], 'customFields[') == 0:
                $ticketCfValues = $this->object->getCustomFieldValues()->getValues();
                $value = null;
                foreach($ticketCfValues as $cfValue) {
                    $mainCf = $cfValue->getTicketCustomFieldsValues();
                    if($condition['type'] == 'customFields[' . $mainCf->getId() . ']' ) {
                        if( in_array($mainCf->getFieldType(), ['select', 'radio', 'checkbox']) ) {
                           $value = json_decode($cfValue->getValue(), true);
                        } else {
                            $value = trim($cfValue->getValue(), '"');
                        }
                       break;
                    }
                }
                if(isset($condition['value']) && $this->object instanceof Ticket) {
                    $flag = $this->match($condition['match'], !empty($value) ? $value : '', $condition['value']);
                }
                break;
        }
        return $flag;
    }

    public function applyResponse($event , $object) {
        if($object instanceof Ticket && $object->getIsTrashed())
            return;

        $this->object = $object;

        $translator = $this->container->get('translator');

        foreach($event->getActions() as $action) {
            switch ($action['type']) {
                case 'mail_customer':
                    if ($object instanceof Ticket) {
                        $mailbox = $object->getMailbox();
                        $thread = $object->createdThread;

                        // dump($mailbox->getEmail());
                        // dump($mailbox->getName());
                        // dump($mailbox->getPassword());
                        // dump($object);
                        // dump($thread);
                        // die;
                        // $createThread = $this->container->get('ticket.service')->getCreateReply($object);

                        // $mailData = [];
                        // $mailData['references'] = $createThread['messageId'];
                        // $mailData['replyTo'] = $object->getUniqueReplyTo();
                        // $mailData['email'] = $object->getCustomer()->getEmail();
                        // $mailData['subject'] = 'New Reply Added';
                        // $mailData['message'] = 'New Ticket Reply Added';
                        // $mailData['mailbox'] = $object->getMailbox();

                        $messageId = $this->sendMail('RE: ' . $object->getSubject(), $thread->getMessage(), $object->getCustomer()->getEmail(), $mailbox, [
                            'In-Reply-To' => $object->getUniqueReplyTo(),
                            'References' => $object->getReferenceIds(),
                        ]);

                        if (!empty($messageId)) {
                            $thread->setMessageId($messageId);

                            $this->em->persist($thread);
                            $this->em->flush();
                        }

                        // $emailTemplate = $this->container->get('email.service')->getEmailTemplate($action['value'],$object->getCompany()->getId());
                        // if($emailTemplate) {
                        //     $placeHolderValues = $this->container->get('ticket.service')->getTicketPlaceholderValues($object);

                        //     $mailData = array();
                        //     $createThread = $this->container->get('ticket.service')->getCreateReply($object->getId(),false);
                        //     $mailData['references'] = $createThread['messageId'];
                        //     $mailData['replyTo'] = $object->getUniqueReplyTo();

                        //     if(!$email = $object->getCustomer()->getEmail())
                        //         break;

                        //     $mailData['email'] = $email;

                        //     $requestParam = $this->request->getCurrentRequest()->request->all();
                        //     // $collaboratorEmails = array();
                        //     //
                        //     // if(isset($requestParam['cccol'])) {
                        //     //     $collaboratorEmails = $requestParam['cccol'];
                        //     // }
                        //     //
                        //     // $mailData['cc'] = isset($requestParam['cc']) ? array_merge((array) $requestParam['cc'], $collaboratorEmails) : $collaboratorEmails;
                        //     //
                        //     // if(isset($requestParam['bcc'])) {
                        //     //     $mailData['bcc'] = $requestParam['bcc'];
                        //     // }

                        //     $this->updateAttachmentPlaceholderFlag($emailTemplate->getMessageInline());

                        //     $placeHolderValues = $this->container->get('ticket.service')->getTicketPlaceholderValues($object,'customer');

                        //     $emailService = $this->container->get('email.service');
                        //     if($this->eventType == 'ticket.replyAgent')
                        //         $emailService->emailTrackingId = $object->currentThread ? $object->currentThread->getId() : 0;

                        //     $mailData['subject'] = $emailService->getProcessedSubject($emailTemplate->getSubject(),$placeHolderValues);
                        //     $mailData['message'] = $emailService->getProcessedTemplate($emailTemplate->getMessageInline(),$placeHolderValues);

                        //     if (filter_var($mailData['email'], FILTER_VALIDATE_EMAIL)) {
                        //         $msgId = $this->sendMail($mailData);
                        //         $currentThread = $object->currentThread;
                        //         if($msgId && $currentThread && $currentThread->getThreadType() !== 'create') {
                        //             $currentThread->setMessageId('<' . $msgId . '>');
                        //             $currentThread->setMailStatus('pending');
                        //             $this->em->persist($currentThread);
                        //             $this->em->flush();
                        //         }
                        //     }
                        // } else {
                        //     // Email Template Not Found. Disable Workflow/Prepared Response
                        //     $this->disableEvent($event, $object);
                        // }
                    } elseif($object instanceof User) {
                        $emailTemplate = $this->container->get('email.service')->getEmailTemplate($action['value']);
                        if(!$emailTemplate && $this->eventType == "customer.forgotPassword")
                            $emailTemplate = $this->container->get('email.service')->getEmailTemplate('CFA');
                        else if(!$emailTemplate && $this->eventType == "customer.created") {
                            $emailTemplate = $this->container->get('email.service')->getEmailTemplate('AA');
                            if(false !== strpos('@marketplace.amazon.', $object->getEmail())) {
                                break;
                            }
                        }

                        $mailData = array();
                        $mailData['email'] = $object->getEmail();

                        $placeHolderValues = $this->container->get('user.service')->getUserPlaceholderValues($object,'customer');
                        $mailData['subject'] = $this->container->get('email.service')
                                                    ->getProcessedSubject($emailTemplate->getSubject(),$placeHolderValues);
                        $mailData['message'] = $this->container->get('email.service')
                                                    ->getProcessedTemplate($emailTemplate->getMessageInline(),$placeHolderValues);

                        $this->sendMail($mailData);
                    }
                    break;
                default:
                    break;
            }
        }
    }

    // Disable Workflow/Prepared Responses
    public function disableEvent($event, $object)
    {
        return;
        if (get_class($event) == 'Webkul\CoreBundle\Entity\Events') {
            // Disable Workflow
            $event->setStatus(false);
            $this->em->persist($event);
            $this->em->flush();

            $this->container->get('event.manager')->trigger([
                'event' => 'system.workflow.disabled',
                'entity' => $object,
                'targetEntity' => $event
            ]);
        } elseif (get_class($event) == 'Webkul\CoreBundle\Entity\PreparedResponses') {
            // Disable Prepared Response
            $event->setStatus(false);
            $this->em->persist($event);
            $this->em->flush();

            $this->container->get('event.manager')->trigger([
                'event' => 'system.preparedreponse.disabled',
                'entity' => $object,
                'targetEntity' => $event
            ]);
        }
    }

    public function getAgentMails($for, $currentEmails)
    {
        $agentMails = [];
        foreach ($for as $agent) {
            if($agent == 'assignedAgent'){
                if(is_array($currentEmails))
                    $agentMails = array_merge($agentMails, $currentEmails);
                else
                    $agentMails[] = $currentEmails;
            }elseif($agent == 'responsePerforming' && is_object($currentUser = $this->container->get('security.context')->getToken()->getUser())) //add current user email if any
                $agentMails[] = $currentUser->getEmail();
            // elseif($agent == 'responsePerforming') {
            //     if(is_array($currentEmails))
            //         $agentMails = array_merge($agentMails, $currentEmails);
            //     else
            //         $agentMails[] = $currentEmails;
            // }
            elseif($agent == 'baseAgent'){ //add selected user email if any
                if(is_array($currentEmails))
                    $agentMails = array_merge($agentMails, $currentEmails);
                else
                    $agentMails[] = $currentEmails;
            }elseif((int)$agent){
                $qb = $this->em->createQueryBuilder();
                $email = $qb->select('u.email')->from('WebkulUserBundle:User', 'u')
                            ->andwhere("u.id = :userId")
                            ->setParameter('userId', $agent)
                            ->getQuery()->getResult()
                        ;
                if(isset($email[0]['email']))
                    $agentMails[] = $email[0]['email'];
            }
        }
        return array_filter($agentMails);
    }

    public function updateAttachmentPlaceholderFlag($templateMsg)
    {
        foreach ($this->attachmentPlaceholderFlag as $placeHolder => $flag) {
            if (strpos($templateMsg, $placeHolder) !== false)
                $this->attachmentPlaceholderFlag[$placeHolder] = 1;
        }
    }

    public function getWorkflowList()
    {
        $qb = $this->em->createQueryBuilder();
        $qb->select('wf')->from('UVDeskAutomationBundle:Workflow', 'wf')
                ->leftJoin('wf.workflowEvents','wfe');

        $currentPlan = $this->container->get('user.service')->getCurrentPlan();
        if($currentPlan && !$currentPlan->getTasks())
            $qb->andwhere("wfe.event NOT LIKE 'task.%'");

        if($currentPlan && $currentPlan->getWorkflow() == 'predefind')
            $qb->andwhere('wf.isPredefind = 1');

        $qb->addOrderBy('wf.sortOrder', 'ASC');

        return $qb->getQuery()->getResult();
    }

    public function sendMail($data,$mailbox, array $headers = [])
    {
        if (!empty($data['email']) && 'hello@uvdesk.in' == $data['email']) {
            return;
        }

        $transport = (new \Swift_SmtpTransport('smtp.gmail.com', 465, 'ssl'))->setUsername($mailbox->getEmail())->setPassword($mailbox->getPassword());
        $mailer = new \Swift_Mailer($transport);

        // Set Message Id
        $mailboxDomain = substr($mailbox->getEmail(), strrpos($mailbox->getEmail(), '@'));
        // $headers['Message-ID'] = time() . '.' . uniqid() . $mailboxDomain;
        // $uniqueMessageId = time() . '.' . uniqid() . $mailboxDomain;
        // 'Message-ID'

        // dump($uniqueMessageId);
        // die;

        // Create a message
        $message = (new \Swift_Message($data['subject']))
            ->setFrom([$mailbox->getEmail() => $mailbox->getName()])
            ->setTo($data['email'])
            ->setBody($data['message'], 'text/html');
        
        $swiftHeaders = $message->getHeaders();
        foreach ($headers as $headerName => $headerValue) {
            $swiftHeaders->addTextHeader($headerName, $headerName);
        }

        // dump($mailbox->getEmail());
        // dump(substr($mailbox->getEmail(), strrpos($mailbox->getEmail(), '@') + 1));

        // die;

        // $headers->addTextHeader('In-Reply-To', '<WeRPdDpDXHr1auSCbUDCH6j9nHM5ezyQRX_-uBX50DEL7uJtPRFXV_oKTyUuSG-4UiC6OvmbWacC-XrDVdTqZ4zcy8pa4aR5rwkPuUsHUzM=@protonmail.com>');
        // $headers->addTextHeader('References', $data['references']);
        // dump($swiftHeaders);
        // die;

        try {
            $messageId = $message->getId();
            $mailer->send($message);

            return "<$messageId>";
        } catch (\Exception $e) {
            dump($e);
        }

        return null;

        // die;
        // $message->setFrom(array($mailbox->getEmail() => $mailbox->getName()));
      
        // $messageId = null;
        // $from = $data['email'] ? $data['email'] : $this->SMTP_EMAIL;
        // $fromName = $from;

        // $mailer = $this->container->get('mailer');
        // $message = $mailer->createMessage()
        //                 ->setSubject($this->container->get('translator')->trans($data['subject']))
        //                 ->setFrom(array($from => $fromName))
        //                 ->setTo($data['email'])
        //                 ->setBody(
        //                     $data['message'],
        //                     'text/html'
        //                 );

        // if(isset($data['references']) && isset($data['replyTo'])) {
        //     $message->setReplyTo($data['replyTo']);
        //     $headers = $message->getHeaders();
        //     $headers->addParameterizedHeader(
        //         'references', $data['references']
        //     );

        //     $msgId = $message->getHeaders()->get('Message-ID');
        //     $id = time() . '.' . uniqid() . $this->container->getParameter('email.domain');
        //     $messageId = $id;
        //     $msgId->setId($id);
        // }

        // if(isset($this->eventType) && (in_array($this->eventType,$this->attachmentEvent) || 'manual' == $this->eventType) ) {
        //     if($this->object instanceof Ticket) {
        //         if($this->attachmentPlaceholderFlag['ticket.attachments']) {
        //             if($this->object->currentThread)
        //                 $thread = $this->object->currentThread;
        //             else
        //                 $thread = $this->container->get('ticket.service')->getTicketLastThread($this->object->getId());

        //             $attachments = $this->em->getRepository('WebkulCoreBundle:Attachment')->findBy(array('thread'=>$thread->getId()));
        //             $fileService = $this->container->get('file.service');
        //             foreach ($attachments as $attachment) {
        //                 if($attachment->getFileSystem()) {
        //                     $fileService->setRequiredFileSystem($attachment->getFileSystem());
        //                     $attachmentTemp = \Swift_Attachment::newInstance($fileService->readUpload($attachment->getPath()), $attachment->getName(),null);
        //                     $message->attach($attachmentTemp);
        //                 }
        //             }
        //         }
        //     } else if($this->object instanceof Task) {
        //         if($this->attachmentPlaceholderFlag['task.attachments']) {
        //             if($this->object->currentThread) {
        //                 $thread = $this->object->currentThread;
        //             } else {
        //                 foreach ($this->object->getThreads() as $taskThread) {
        //                     $thread = $taskThread;
        //                     break;
        //                 }
        //             }

        //             $attachments = $this->em->getRepository('WebkulCoreBundle:Attachment')->findBy(array('taskThread'=>$thread->getId()));

        //             $fileService = $this->container->get('file.service');
        //             foreach ($attachments as $attachment) {
        //                 if($attachment->getFileSystem()) {
        //                     $fileService->setRequiredFileSystem($attachment->getFileSystem());
        //                     $attachmentTemp = \Swift_Attachment::newInstance($fileService->readUpload($attachment->getPath()), $attachment->getName(),null);
        //                     $message->attach($attachmentTemp);
        //                 }
        //             }
        //         }
        //     }
        // }

        // $validEvents = array('ticket.created','ticket.replyAgent','ticket.replyCustomer','ticket.agent','ticket.replyByCollaborator','ticket.collaboratorAdded', 'ticket.forward.added');
        // if($this->object instanceof Ticket && isset($this->eventType) && in_array($this->eventType,$validEvents)) {
        //     $referenceIds = htmlspecialchars_decode($this->object->getReferenceIds() ." <".$id.">");
        //     $this->object->setReferenceIds($referenceIds);
        //     $this->em->persist($this->object);
        //     $this->em->flush();
        //     if($mailbox = $this->object->getMailbox())
        //         $message->setFrom(array($mailbox->getEmail() => $mailbox->getName()));
        //     if(isset($data['bcc']) && $this->eventType != 'ticket.agent')
        //         $message->setBcc($data['bcc']);

        //     if(isset($data['cc']) && $this->eventType != 'ticket.agent')
        //         $message->setCc($data['cc']);
        // }

        // try {
        //     $mailer->send($message);
        // } catch(\Exception $e) {
        //     $logger = $this->container->get('logger');
        //     $logger->error('Email send error : '.$e->getMessage());
        //     $messageId = null;
        // }

        // return $messageId;
    }

    protected function sendCcBccMail($requestParam, Ticket $object)
    {
        $placeHolderValues = $this->container->get('ticket.service')->getTicketPlaceholderValues($object,'customer');
        /* send cc/bccc/ccol Data */
        $ccData = [];
        if(!empty($requestParam['cccol'])) {
            $ccData['cc'] = $requestParam['cccol'];
        }
        if(!empty($requestParam['cc'])) {
            if(!empty($ccData['cc'])) {
                $ccData['cc'] = array_merge($requestParam['cc'], $ccData['cc']);
            } else {
                $ccData['cc'] = $requestParam['cc'];
            }
        }
        if(!empty($requestParam['bcc'])) {
            $ccData['bcc'] = $requestParam['bcc'];
        }
        if(!empty($ccData)) {
            $mailer = $this->container->get('mailer');
            $values = [];
            $values['message'] = $placeHolderValues['ticket.threadMessage'];

            $emailTemplate = $this->container->get('templating')->render('WebkulAdminBundle:Default\MailTemplates:plainCcTemplate.html.twig', $values);

            $message = $mailer->createMessage()
                            ->setSubject($this->container->get('translator')->trans("Reply notification for ticket #%ticket.id%", ['%ticket.id%' => $placeHolderValues['ticket.id'] ]) )
                            ->setFrom($this->container->get('user.service')->getCurrentCompany()->getSupportEmail())
                            // ->setTo($ccData)
                            ->setBody(
                                $emailTemplate,
                                'text/html'
                            );
            if(!empty($ccData['cc'])) {
                $message->setCc($ccData['cc']);
            }
            if(!empty($ccData['bcc'])) {
                $message->setBcc($ccData['bcc']);
            }
            try {
                // Add attachments
                $fileService = $this->container->get('file.service');
                $thread = $this->container->get('ticket.service')->getTicketLastThread($object->getId());

                $attachments = $this->em->getRepository('WebkulCoreBundle:Attachment')->findBy(array('thread'=>$thread->getId()));

                foreach ($attachments as $attachment) {
                    if($attachment->getFileSystem()) {
                        $fileService->setRequiredFileSystem($attachment->getFileSystem());
                        $attachmentTemp = \Swift_Attachment::newInstance($fileService->readUpload($attachment->getPath()), $attachment->getName(),null);
                        $message->attach($attachmentTemp);
                    }
                }
                
                $message->setReplyTo($object->getUniqueReplyTo());
                $mailer->send($message);
            } catch(\Exception $e) {
            }
        }

    }

    public function match($condition,$haystack,$needle) {
        //remove tags
        if('string' == gettype($haystack)) {
            $haystack = strip_tags($haystack);
        }

        switch ($condition) {
            case 'is':
                return is_array($haystack) ? in_array($needle, $haystack) : $haystack == $needle;
            case 'isNot':
                return is_array($haystack) ? !in_array($needle, $haystack) : $haystack != $needle;
            case 'contains':
                return strripos($haystack,$needle) !== false ? true : false;
            case 'notContains':
                return strripos($haystack,$needle) === false ? true : false;
            case 'startWith':
                return $needle === "" || strripos($haystack, $needle, -strlen($haystack)) !== FALSE;
            case 'endWith':
                return $needle === "" || (($temp = strlen($haystack) - strlen($needle)) >= 0 && stripos($haystack, $needle, $temp) !== FALSE);
            case 'before':
                $createdTimeStamp = date('Y-m-d',strtotime($haystack));
                $conditionTimeStamp = date('Y-m-d',strtotime($needle." 23:59:59"));
                return $createdTimeStamp < $conditionTimeStamp ? true : false;
            case 'beforeOn':
                $createdTimeStamp = date('Y-m-d',strtotime($haystack));
                $conditionTimeStamp = date('Y-m-d',strtotime($needle." 23:59:59"));
                return ($createdTimeStamp < $conditionTimeStamp || $createdTimeStamp == $conditionTimeStamp) ? true : false;
            case 'after':
                $createdTimeStamp = date('Y-m-d',strtotime($haystack));
                $conditionTimeStamp = date('Y-m-d',strtotime($needle." 23:59:59"));
                return $createdTimeStamp > $conditionTimeStamp ? true : false;
            case 'afterOn':
                $createdTimeStamp = date('Y-m-d',strtotime($haystack));
                $conditionTimeStamp = date('Y-m-d',strtotime($needle." 23:59:59"));
                return $createdTimeStamp > $conditionTimeStamp || $createdTimeStamp == $conditionTimeStamp ? true : false;

            case 'beforeDateTime':
                $createdTimeStamp = date('Y-m-d h:i:s',strtotime($haystack));
                $conditionTimeStamp = date('Y-m-d h:i:s',strtotime($needle));
                return $createdTimeStamp < $conditionTimeStamp ? true : false;
            case 'beforeDateTimeOn':
                $createdTimeStamp = date('Y-m-d h:i:s',strtotime($haystack));
                $conditionTimeStamp = date('Y-m-d h:i:s',strtotime($needle));
                return ($createdTimeStamp < $conditionTimeStamp || $createdTimeStamp == $conditionTimeStamp) ? true : false;
            case 'afterDateTime':
                $createdTimeStamp = date('Y-m-d h:i:s',strtotime($haystack));
                $conditionTimeStamp = date('Y-m-d h:i:s',strtotime($needle));
                return $createdTimeStamp > $conditionTimeStamp ? true : false;
            case 'afterDateTimeOn':
                $createdTimeStamp = date('Y-m-d h:i:s',strtotime($haystack));
                $conditionTimeStamp = date('Y-m-d h:i:s',strtotime($needle));
                return $createdTimeStamp > $conditionTimeStamp || $createdTimeStamp == $conditionTimeStamp ? true : false;

            case 'beforeTime':
                $createdTimeStamp = date('Y-m-d H:i A',strtotime('2017-01-01'.$haystack));
                $conditionTimeStamp = date('Y-m-d H:i A',strtotime('2017-01-01'.$needle));
                return $createdTimeStamp < $conditionTimeStamp ? true : false;
            case 'beforeTimeOn':
                $createdTimeStamp = date('Y-m-d H:i A',strtotime('2017-01-01'.$haystack));
                $conditionTimeStamp = date('Y-m-d H:i A',strtotime('2017-01-01'.$needle));
                return ($createdTimeStamp < $conditionTimeStamp || $createdTimeStamp == $conditionTimeStamp) ? true : false;
            case 'afterTime':
                $createdTimeStamp = date('Y-m-d H:i A',strtotime('2017-01-01'.$haystack));
                $conditionTimeStamp = date('Y-m-d H:i A',strtotime('2017-01-01'.$needle));
                return $createdTimeStamp > $conditionTimeStamp ? true : false;
            case 'afterTimeOn':
                $createdTimeStamp = date('Y-m-d H:i A',strtotime('2017-01-01'.$haystack));
                $conditionTimeStamp = date('Y-m-d H:i A',strtotime('2017-01-01'.$needle));
                return $createdTimeStamp > $conditionTimeStamp || $createdTimeStamp == $conditionTimeStamp ? true : false;

            case 'greaterThan':
                return !is_array($haystack) && $needle > $haystack;
            case 'lessThan':
                return !is_array($haystack) && $needle < $haystack;
        }
    }

    protected function isEcommerceAction($action)
    {
        return 0 === strpos($action, 'add_order_to_' ) ? true : false;
    }
}
