<?php

declare(strict_types=1);

namespace App\Tests\Integration\Sanitizer;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;

class PublicationSanitizerTest extends KernelTestCase
{
    private HtmlSanitizerInterface $sanitizer;

    protected function setUp(): void
    {
        $this->sanitizer = static::getContainer()->get('html_sanitizer.sanitizer.app.publication_sanitizer');
        parent::setUp();
    }

    // ========== ALLOWED ELEMENTS (positive tests) ==========

    public function test_allows_paragraph(): void
    {
        $html = '<p>Hello world</p>';
        $this->assertSame('<p>Hello world</p>', $this->sanitizer->sanitize($html));
    }

    public function test_allows_headings_h2_and_h3(): void
    {
        $html = '<h2>Title H2</h2><h3>Title H3</h3>';
        $this->assertSame('<h2>Title H2</h2><h3>Title H3</h3>', $this->sanitizer->sanitize($html));
    }

    public function test_allows_text_formatting(): void
    {
        $html = '<p><strong>Bold</strong> and <em>italic</em></p>';
        $this->assertSame('<p><strong>Bold</strong> and <em>italic</em></p>', $this->sanitizer->sanitize($html));
    }

    public function test_allows_links_with_href(): void
    {
        $html = '<a href="https://example.com">Link</a>';
        $this->assertSame('<a href="https://example.com">Link</a>', $this->sanitizer->sanitize($html));
    }

    public function test_allows_unordered_list(): void
    {
        $html = '<ul><li>Item 1</li><li>Item 2</li></ul>';
        $this->assertSame('<ul><li>Item 1</li><li>Item 2</li></ul>', $this->sanitizer->sanitize($html));
    }

    public function test_allows_ordered_list(): void
    {
        $html = '<ol><li>First</li><li>Second</li></ol>';
        $this->assertSame('<ol><li>First</li><li>Second</li></ol>', $this->sanitizer->sanitize($html));
    }

    public function test_allows_blockquote(): void
    {
        $html = '<blockquote>A famous quote</blockquote>';
        $this->assertSame('<blockquote>A famous quote</blockquote>', $this->sanitizer->sanitize($html));
    }

    public function test_allows_horizontal_rule(): void
    {
        $html = '<p>Before</p><hr><p>After</p>';
        $this->assertSame('<p>Before</p><hr /><p>After</p>', $this->sanitizer->sanitize($html));
    }

    public function test_allows_line_break(): void
    {
        $html = '<p>Line 1<br>Line 2</p>';
        $this->assertSame('<p>Line 1<br />Line 2</p>', $this->sanitizer->sanitize($html));
    }

    public function test_allows_div_with_class(): void
    {
        $html = '<div class="my-class">Content</div>';
        $this->assertSame('<div class="my-class">Content</div>', $this->sanitizer->sanitize($html));
    }

    public function test_allows_span_with_class(): void
    {
        $html = '<span class="highlight">Text</span>';
        $this->assertSame('<span class="highlight">Text</span>', $this->sanitizer->sanitize($html));
    }

    public function test_allows_definition_list(): void
    {
        $html = '<dl><dt>Term</dt><dd>Definition</dd></dl>';
        $this->assertSame('<dl><dt>Term</dt><dd>Definition</dd></dl>', $this->sanitizer->sanitize($html));
    }

    public function test_allows_image_with_allowed_host(): void
    {
        $html = '<img src="https://musicall.com/image.jpg">';
        $this->assertSame('<img src="https://musicall.com/image.jpg" />', $this->sanitizer->sanitize($html));
    }

    public function test_allows_youtube_iframe(): void
    {
        $html = '<iframe src="https://youtube.com/embed/abc123" class="video" frameborder="0" allowfullscreen></iframe>';
        $this->assertSame($html, $this->sanitizer->sanitize($html));

        $html = '<p>Hello world</p><p></p><div data-youtube-video=""><iframe width="640" height="480" allowfullscreen="true" autoplay="false" disablekbcontrols="false" enableiframeapi="false" endtime="0" ivloadpolicy="0" loop="false" modestbranding="false" origin="" playlist="" rel="1" src="https://www.youtube-nocookie.com/embed/ROTwFlTS9kY?rel=1" start="0"></iframe></div><p></p>';
        // cleaning of some attributes
        $this->assertSame('<p>Hello world</p><p></p><div data-youtube-video><iframe allowfullscreen="true" modestbranding="false"></iframe></div><p></p>', $this->sanitizer->sanitize($html));


        // Example of past video in a publication
        $html = '<div class="embed-responsive embed-responsive-21by9"><iframe src="https://www.youtube.com/embed/hVk9ql2vER4" allowfullscreen="true" class="embed-responsive-item" allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture" frameborder="0"></iframe></div>';
        $this->assertSame($html, $this->sanitizer->sanitize($html));
    }

    // ========== BLOCKED ELEMENTS (negative tests) ==========

    public function test_blocks_script_tag(): void
    {
        $html = '<p>Hello</p><script>alert("xss")</script><p>World</p>';
        $result = $this->sanitizer->sanitize($html);
        $this->assertStringNotContainsString('<script', $result);
        $this->assertStringNotContainsString('alert', $result);
    }

    public function test_blocks_style_tag(): void
    {
        $html = '<style>body { display: none; }</style><p>Content</p>';
        $result = $this->sanitizer->sanitize($html);
        $this->assertStringNotContainsString('<style', $result);
        $this->assertStringNotContainsString('display', $result);
    }

    public function test_blocks_h1_heading(): void
    {
        $html = '<h1>Not allowed heading</h1>';
        $result = $this->sanitizer->sanitize($html);
        $this->assertStringNotContainsString('<h1', $result);
        // Note: sanitizer drops content of non-allowed block elements entirely
        $this->assertSame('', $result);
    }

    public function test_blocks_onclick_event_handler(): void
    {
        $html = '<p onclick="alert(\'xss\')">Click me</p>';
        $result = $this->sanitizer->sanitize($html);
        $this->assertStringNotContainsString('onclick', $result);
        $this->assertStringContainsString('<p>Click me</p>', $result);
    }

    public function test_blocks_onerror_event_handler(): void
    {
        $html = '<img src="x" onerror="alert(\'xss\')">';
        $result = $this->sanitizer->sanitize($html);
        $this->assertStringNotContainsString('onerror', $result);
    }

    public function test_blocks_javascript_url_in_link(): void
    {
        $html = '<a href="javascript:alert(\'xss\')">Click</a>';
        $result = $this->sanitizer->sanitize($html);
        $this->assertStringNotContainsString('javascript:', $result);
    }

    public function test_blocks_javascript_url_in_image(): void
    {
        $html = '<img src="javascript:alert(\'xss\')">';
        $result = $this->sanitizer->sanitize($html);
        $this->assertStringNotContainsString('javascript:', $result);
    }

    public function test_blocks_data_url_in_image(): void
    {
        $html = '<img src="data:text/html,<script>alert(\'xss\')</script>">';
        $result = $this->sanitizer->sanitize($html);
        $this->assertStringNotContainsString('data:', $result);
    }

    public function test_blocks_form_elements(): void
    {
        $html = '<form action="/steal"><input type="text"><button>Submit</button></form>';
        $result = $this->sanitizer->sanitize($html);
        $this->assertStringNotContainsString('<form', $result);
        $this->assertStringNotContainsString('<input', $result);
        $this->assertStringNotContainsString('<button', $result);
    }

    public function test_blocks_iframe_from_non_allowed_host(): void
    {
        $html = '<iframe src="https://evil.com/malware.html"></iframe>';
        $result = $this->sanitizer->sanitize($html);
        $this->assertStringNotContainsString('evil.com', $result);
    }

    public function test_blocks_image_from_non_allowed_host(): void
    {
        $html = '<img src="https://evil.com/tracking.gif">';
        $result = $this->sanitizer->sanitize($html);
        $this->assertStringNotContainsString('evil.com', $result);
    }

    public function test_blocks_style_attribute(): void
    {
        $html = '<p style="background: url(https://evil.com/track)">Text</p>';
        $result = $this->sanitizer->sanitize($html);
        $this->assertStringNotContainsString('style=', $result);
    }

    public function test_blocks_meta_tag(): void
    {
        $html = '<meta http-equiv="refresh" content="0;url=https://evil.com"><p>Content</p>';
        $result = $this->sanitizer->sanitize($html);
        $this->assertStringNotContainsString('<meta', $result);
    }

    public function test_blocks_object_tag(): void
    {
        $html = '<object data="malware.swf"></object>';
        $result = $this->sanitizer->sanitize($html);
        $this->assertStringNotContainsString('<object', $result);
    }

    public function test_blocks_embed_tag(): void
    {
        $html = '<embed src="malware.swf">';
        $result = $this->sanitizer->sanitize($html);
        $this->assertStringNotContainsString('<embed', $result);
    }

    // ========== ATTRIBUTE FILTERING ==========

    public function test_strips_non_allowed_attributes_from_div(): void
    {
        $html = '<div class="allowed" id="not-allowed" data-custom="no">Content</div>';
        $result = $this->sanitizer->sanitize($html);
        $this->assertStringContainsString('class="allowed"', $result);
        $this->assertStringNotContainsString('id=', $result);
        $this->assertStringNotContainsString('data-custom', $result);
    }

    public function test_strips_non_allowed_attributes_from_link(): void
    {
        $html = '<a href="https://example.com" target="_blank" rel="noopener">Link</a>';
        $result = $this->sanitizer->sanitize($html);
        $this->assertStringContainsString('href="https://example.com"', $result);
        $this->assertStringNotContainsString('target=', $result);
        $this->assertStringNotContainsString('rel=', $result);
    }

    // ========== COMPLEX/MIXED CONTENT ==========

    public function test_sanitizes_complex_mixed_content(): void
    {
        $html = '
            <h2>Article Title</h2>
            <p>Introduction with <strong>bold</strong> and <em>italic</em>.</p>
            <script>alert("xss")</script>
            <ul>
                <li>Point 1</li>
                <li>Point 2</li>
            </ul>
            <blockquote>A quote</blockquote>
            <p onclick="evil()">Clean paragraph</p>
        ';

        $result = $this->sanitizer->sanitize($html);

        // Allowed content preserved
        $this->assertStringContainsString('<h2>Article Title</h2>', $result);
        $this->assertStringContainsString('<strong>bold</strong>', $result);
        $this->assertStringContainsString('<em>italic</em>', $result);
        $this->assertStringContainsString('<ul>', $result);
        $this->assertStringContainsString('<li>Point 1</li>', $result);
        $this->assertStringContainsString('<blockquote>A quote</blockquote>', $result);
        $this->assertStringContainsString('Clean paragraph', $result);

        // Dangerous content removed
        $this->assertStringNotContainsString('<script', $result);
        $this->assertStringNotContainsString('alert', $result);
        $this->assertStringNotContainsString('onclick', $result);
    }

    public function test_handles_empty_string(): void
    {
        $this->assertSame('', $this->sanitizer->sanitize(''));
    }

    public function test_handles_plain_text(): void
    {
        $html = 'Just plain text without any HTML';
        $this->assertSame('Just plain text without any HTML', $this->sanitizer->sanitize($html));
    }
}
