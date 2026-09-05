<?php declare(strict_types=1);

namespace App\Service\Builder\Admin\Feedback;

use App\ApiResource\Admin\Feedback\AdminFeedback;
use App\Entity\Feedback\Feedback;

/**
 * Shared by the triage list and the single report view, so the two can never disagree about what a
 * report looks like.
 */
readonly class AdminFeedbackBuilder
{
    public function buildFromEntity(Feedback $feedback): AdminFeedback
    {
        $resource = new AdminFeedback();
        $resource->id = (string) $feedback->id;
        $resource->type = $feedback->type->value;
        $resource->typeLabel = $feedback->type->label();
        $resource->module = $feedback->module->value;
        $resource->moduleLabel = $feedback->module->label();
        $resource->message = $feedback->message;
        $resource->email = $feedback->email;
        $resource->username = $feedback->user?->username;
        $resource->bandSpaceName = $feedback->bandSpace?->name;
        $resource->pageUrl = $feedback->pageUrl;
        $resource->userAgent = $feedback->userAgent;
        $resource->status = $feedback->status->value;
        $resource->statusLabel = $feedback->status->label();
        $resource->creationDatetime = $feedback->creationDatetime;

        return $resource;
    }

    /**
     * @param list<Feedback> $feedbacks
     *
     * @return list<AdminFeedback>
     */
    public function buildFromEntities(array $feedbacks): array
    {
        return array_map($this->buildFromEntity(...), $feedbacks);
    }
}
