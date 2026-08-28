<?php declare(strict_types=1);

namespace App\Tests\Unit\Http;

use App\Http\ReturnUrl;
use PHPUnit\Framework\TestCase;

/**
 * The other half of assets/js/utils/returnUrl.test.js. One parameter, `return_url`, is guarded by two
 * validators, and until #917 they disagreed: the backend accepted any leading `/`, so `//evil.example`
 * passed here and was refused by the SPA. Nothing was exploitable, because getReturnUrl() concatenates
 * a `/`-prefixed value onto a fixed origin, which anchors the authority. That is the caller saving the
 * check rather than the check being right.
 *
 * The relative cases below are deliberately the same ones the JS test lists, so a policy that drifts on
 * one side fails on the other. The same-host absolute branch has no JS counterpart: the SPA refuses
 * every absolute URL, the OAuth entry point accepts its own origin.
 */
class ReturnUrlTest extends TestCase
{
    private const string FRONTEND_URL = 'https://musicall.fr';

    public function test_a_same_origin_relative_path_is_safe(): void
    {
        self::assertTrue(ReturnUrl::isSafe('/', self::FRONTEND_URL));
        self::assertTrue(ReturnUrl::isSafe('/messages', self::FRONTEND_URL));
        self::assertTrue(ReturnUrl::isSafe('/band/invitation/abc123', self::FRONTEND_URL));
        self::assertTrue(ReturnUrl::isSafe('/band/1/tech-riders?rider=42#top', self::FRONTEND_URL));
    }

    public function test_a_protocol_relative_url_is_refused(): void
    {
        self::assertFalse(ReturnUrl::isSafe('//evil.example', self::FRONTEND_URL));
        self::assertFalse(ReturnUrl::isSafe('//evil.example/path?a=b', self::FRONTEND_URL));
    }

    /**
     * A browser normalises a backslash to a slash on a special scheme, so this resolves exactly like
     * the protocol relative form above while still reading as an internal path.
     */
    public function test_a_backslash_standing_in_for_the_second_slash_is_refused(): void
    {
        self::assertFalse(ReturnUrl::isSafe('/\\evil.example', self::FRONTEND_URL));
        self::assertFalse(ReturnUrl::isSafe('/\\/evil.example', self::FRONTEND_URL));
    }

    public function test_an_absolute_url_on_another_host_is_refused(): void
    {
        self::assertFalse(ReturnUrl::isSafe('https://evil.example', self::FRONTEND_URL));
        self::assertFalse(ReturnUrl::isSafe('http://evil.example/path', self::FRONTEND_URL));
        self::assertFalse(ReturnUrl::isSafe('https://musicall.fr.evil.example', self::FRONTEND_URL));
    }

    public function test_an_absolute_url_on_the_frontend_host_is_safe(): void
    {
        self::assertTrue(ReturnUrl::isSafe('https://musicall.fr', self::FRONTEND_URL));
        self::assertTrue(ReturnUrl::isSafe('https://musicall.fr/messages', self::FRONTEND_URL));
    }

    public function test_a_scheme_that_is_not_http_is_refused(): void
    {
        self::assertFalse(ReturnUrl::isSafe('javascript:alert(1)', self::FRONTEND_URL));
        self::assertFalse(ReturnUrl::isSafe('data:text/html,<script>', self::FRONTEND_URL));
        self::assertFalse(ReturnUrl::isSafe('mailto:someone@example.com', self::FRONTEND_URL));
    }

    /**
     * The three above carry no authority, so they are refused for having no host at all rather than for
     * their scheme. These do carry one, and parse_url reads the frontend's own host straight out of
     * them, so only the scheme check stands between them and a Location header.
     */
    public function test_a_foreign_scheme_on_the_frontend_host_is_refused(): void
    {
        self::assertFalse(ReturnUrl::isSafe('javascript://musicall.fr/%0aalert(1)', self::FRONTEND_URL));
        self::assertFalse(ReturnUrl::isSafe('ftp://musicall.fr/x', self::FRONTEND_URL));
        self::assertFalse(ReturnUrl::isSafe('file://musicall.fr/x', self::FRONTEND_URL));
    }

    /**
     * The URL parser deletes every ASCII tab, CR and LF from the whole input before parsing it, so each
     * of these reaches it as the protocol relative "//evil.example" while reading as an internal path.
     * The frontend rule refuses them too: assets/js/utils/returnUrl.test.js carries the same three.
     */
    public function test_a_tab_or_a_newline_standing_in_for_the_second_slash_is_refused(): void
    {
        self::assertFalse(ReturnUrl::isSafe("/\t/evil.example", self::FRONTEND_URL));
        self::assertFalse(ReturnUrl::isSafe("/\n/evil.example", self::FRONTEND_URL));
        self::assertFalse(ReturnUrl::isSafe("/\r/evil.example", self::FRONTEND_URL));
        // Not only in second position: the deletion applies to the whole input.
        self::assertFalse(ReturnUrl::isSafe("/messages\n//evil.example", self::FRONTEND_URL));
        self::assertFalse(ReturnUrl::isSafe("https://musicall\t.fr/x", self::FRONTEND_URL));
    }

    public function test_the_frontend_host_is_matched_whatever_its_case(): void
    {
        self::assertTrue(ReturnUrl::isSafe('https://MUSICALL.FR/messages', self::FRONTEND_URL));
    }

    public function test_an_empty_url_and_a_bare_path_are_refused(): void
    {
        self::assertFalse(ReturnUrl::isSafe('', self::FRONTEND_URL));
        self::assertFalse(ReturnUrl::isSafe('messages', self::FRONTEND_URL));
    }

    /**
     * A frontend URL with no host would otherwise make every absolute URL match on null === null.
     */
    public function test_nothing_absolute_is_safe_when_the_frontend_url_has_no_host(): void
    {
        self::assertFalse(ReturnUrl::isSafe('https://evil.example', ''));
        self::assertTrue(ReturnUrl::isSafe('/messages', ''));
    }
}
