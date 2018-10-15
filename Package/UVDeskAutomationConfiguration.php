<?php

namespace Webkul\UVDesk\AutomationBundle\Package;

use Webkul\UVDesk\PackageManager\Extensions\HelpdeskExtension;
use Webkul\UVDesk\PackageManager\ExtensionOptions\HelpdeskExtension\Section as HelpdeskSection;

class UVDeskAutomationConfiguration extends HelpdeskExtension
{
    const WORKFLOW_BRICK_SVG = <<<SVG
<path fill-rule="evenodd" d="M25.783,21.527L10.245,6.019,6.016,10.248,21.524,25.756ZM37.512,6.019l6.119,6.119L6.016,49.783l4.229,4.229L47.89,16.4l6.119,6.119V6.019h-16.5ZM38.5,34.245l-4.229,4.229,9.389,9.389-6.149,6.149h16.5v-16.5L47.89,43.634Z" />
SVG;

    const PREPEARED_RESPONSE_BRICK_SVG = <<<SVG
<path fill-rule="evenodd" d="M25.783,21.527L10.245,6.019,6.016,10.248,21.524,25.756ZM37.512,6.019l6.119,6.119L6.016,49.783l4.229,4.229L47.89,16.4l6.119,6.119V6.019h-16.5ZM38.5,34.245l-4.229,4.229,9.389,9.389-6.149,6.149h16.5v-16.5L47.89,43.634Z" />
SVG;

    const TICKET_TYPE_BRICK_SVG = <<<SVG
<path fill-rule="evenodd" d="M6,44v5H22V44H6ZM6,11v5H33V11H6ZM33,54V49H54V44H33V39H28V54h5ZM17,23v5H6v5H17v5h5V23H17ZM54,33V28H28v5H54ZM39,21h5V16H54V11H44V6H39V21Z" />
SVG;

    const TAG_BRICK_SVG = <<<SVG
<path fill-rule="evenodd" d="M42.935,14.247A4.683,4.683,0,0,0,39,12H11a5.182,5.182,0,0,0-5.015,5.313V43.74A5.164,5.164,0,0,0,11.036,49l27.782,0.026a4.972,4.972,0,0,0,4.117-2.22L53.972,30.526Z" />
SVG;

    public function loadDashboardItems()
    {
        return [
            HelpdeskSection::AUTOMATION => [
                [
                    'name' => 'Workflows',
                    'route' => 'helpdesk_member_workflow_collection',
                    'brick_svg' => self::WORKFLOW_BRICK_SVG,
                ],
                [
                    'name' => 'Prepared Responses',
                    'route' => 'prepare_response_action',
                    'brick_svg' => self::PREPEARED_RESPONSE_BRICK_SVG,
                ],
                [
                    'name' => 'Ticket Types',
                    'route' => 'helpdesk_member_ticket_type_collection',
                    'brick_svg' => self::TICKET_TYPE_BRICK_SVG,
                ],
                [
                    'name' => 'Tags',
                    'route' => 'helpdesk_member_ticket_tag_collection',
                    'brick_svg' => self::TICKET_TYPE_BRICK_SVG,
                ],
            ],
        ];
    }

    public function loadNavigationItems()
    {
        return [];
    }
}
