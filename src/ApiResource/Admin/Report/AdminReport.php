<?php declare(strict_types=1);

namespace App\ApiResource\Admin\Report;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\Provider\Admin\Report\AdminReportCollectionProvider;
use App\State\Provider\Admin\Report\AdminReportItemProvider;
use DateTimeInterface;
use Symfony\Component\Validator\Constraints as Assert;

/** The moderation queue « Signalements » (#1116). */
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/admin/reports',
            openapi: new Operation(tags: ['Admin Reports']),
            paginationEnabled: true,
            paginationItemsPerPage: 25,
            security: 'is_granted("ROLE_ADMIN")',
            name: 'api_admin_reports_list',
            provider: AdminReportCollectionProvider::class,
            parameters: [
                'status' => new QueryParameter(key: 'status', constraints: [new Assert\Choice(choices: [self::STATUS_PENDING, self::STATUS_RESOLVED], message: 'Statut inconnu')]),
            ],
        ),
        new Get(
            uriTemplate: '/admin/reports/{id}',
            openapi: new Operation(tags: ['Admin Reports']),
            security: 'is_granted("ROLE_ADMIN")',
            name: 'api_admin_reports_get',
            provider: AdminReportItemProvider::class,
        ),
    ],
    normalizationContext: ['skip_null_values' => false],
)]
class AdminReport
{
    final public const string STATUS_PENDING = 'pending';
    final public const string STATUS_RESOLVED = 'resolved';

    /** The content is as reported. */
    final public const string LIVE_UNCHANGED = 'unchanged';
    /** The content still exists but reads differently from the snapshot. */
    final public const string LIVE_EDITED = 'edited';
    /** The content is gone, deleted or offline. */
    final public const string LIVE_REMOVED = 'removed';

    #[ApiProperty(identifier: true)]
    public string $id;

    public string $targetType;
    public string $targetId;
    public string $reason;
    public ?string $details = null;
    public string $snapshotText;

    /** @var array<string, scalar|null> */
    public array $snapshotContext = [];

    /** @var array{id: string, username: string} */
    public array $reporter;

    /** @var array{id: string, username: string, is_suspended: bool, is_admin: bool}|null */
    public ?array $targetAuthor = null;

    /** Pending reports on the same target, this one included. */
    public int $pendingReportCount = 0;

    /** Only on the detail: comparing with the live content costs a lookup per report. */
    public ?string $liveState = null;

    public DateTimeInterface $creationDatetime;
    public ?DateTimeInterface $resolutionDatetime = null;
    public ?string $resolvedByUsername = null;
    public ?string $outcome = null;
}
