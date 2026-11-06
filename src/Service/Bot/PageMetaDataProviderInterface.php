<?php declare(strict_types=1);

namespace App\Service\Bot;

/**
 * A provider whose meta is also served in the page itself, to every visitor and search engine, not only to the
 * share bots. Its texts must follow the page locale: an English page never gets a French description.
 */
interface PageMetaDataProviderInterface extends BotMetaDataProviderInterface
{
}
