<?php declare(strict_types=1);

namespace App\ApiResource\Feedback;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use App\Enum\Feedback\FeedbackModule;
use App\Enum\Feedback\FeedbackType;
use App\State\Processor\Feedback\FeedbackProcessor;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Sending a short report from wherever the user is standing.
 *
 * Declares no `security:` expression on purpose. The api firewall is `lazy: true` and forces
 * nothing, so an anonymous visitor posts here exactly as they do to /api/contact, while a logged in
 * one is still resolved from their token and attached by the processor.
 */
#[ApiResource(
    shortName: 'Feedback',
    operations: [
        new Post(
            uriTemplate: '/feedbacks',
            openapi: false,
            name: 'api_feedback_post',
            processor: FeedbackProcessor::class,
        ),
        // Carries no identifier of its own, so without an item operation API Platform falls back to
        // a random .well-known/genid IRI on the response. Declared exactly as Contact does, for the
        // same reason. shortName sits on the resource rather than the operations so @type and
        // @context agree.
        new Get(openapi: false),
    ],
)]
class FeedbackResource
{
    /** Mirrored by assets/js/constants/feedback.js, pinned by FeedbackClientMirrorTest. */
    final public const int MESSAGE_MIN_LENGTH = 10;
    final public const int MESSAGE_MAX_LENGTH = 2000;

    #[Assert\NotBlank(message: 'feedback.type.required')]
    #[Assert\Choice(callback: [FeedbackType::class, 'values'], message: 'feedback.type.unknown')]
    public string $type;

    #[Assert\NotBlank(message: 'feedback.module.required')]
    #[Assert\Choice(callback: [FeedbackModule::class, 'values'], message: 'feedback.module.unknown')]
    public string $module;

    #[Assert\NotBlank(message: 'feedback.message.required')]
    #[Assert\Length(
        min: self::MESSAGE_MIN_LENGTH,
        max: self::MESSAGE_MAX_LENGTH,
        minMessage: 'feedback.message.too_short',
        maxMessage: 'feedback.message.too_long',
    )]
    public string $message;

    /**
     * Optional even without an account: a report carrying a section and a page is actionable without
     * a way to reply, and demanding one costs us the quick report this feature exists for.
     */
    #[Assert\Email(message: 'feedback.email.invalid')]
    #[Assert\Length(max: 255, maxMessage: 'feedback.email.too_long')]
    public ?string $email = null;

    /**
     * A path, never an absolute URL. An admin clicks this from the triage table, so accepting a full
     * URL would make the admin list an open redirect with a human in the loop.
     *
     * A leading slash is not enough on its own. `//evil.com` and `/\evil.com` are protocol relative:
     * the browser resolves both to another origin, and vue-router passes any string starting with a
     * slash through to the rendered href untouched, so a middle click, a ctrl click or just hovering
     * for the status bar leaves the site. Hence the lookahead refusing a second slash or a backslash,
     * and the backslash excluded from the body as well.
     *
     * Anchored with \z rather than $, which also matches before a trailing newline. The backslash is
     * written \x5C rather than escaped: in a single quoted PHP string a literal `\\` reaches PCRE as
     * one backslash, which escapes the `]` and leaves a regex that fails to compile, and a regex that
     * fails to compile rejects every value including the valid ones.
     */
    #[Assert\NotBlank(message: 'feedback.page_url.required')]
    #[Assert\Length(max: 255, maxMessage: 'feedback.page_url.too_long')]
    #[Assert\Regex(pattern: '#^/(?![/\x5C])[^\s\x5C]*\z#', message: 'feedback.page_url.invalid')]
    public string $pageUrl;

    /**
     * Attached by the processor only when the sender is an active member of that space, so a report
     * cannot be pinned onto somebody else's Band Space.
     */
    #[Assert\Uuid(message: 'feedback.band_space_id.invalid')]
    public ?string $bandSpaceId = null;
}
