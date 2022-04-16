<?php

namespace App\Sanitizer;

use HtmlSanitizer\Extension\Basic\NodeVisitor\ANodeVisitor;
use HtmlSanitizer\Extension\Basic\NodeVisitor\BlockquoteNodeVisitor;
use HtmlSanitizer\Extension\Basic\NodeVisitor\BrNodeVisitor;
use HtmlSanitizer\Extension\Basic\NodeVisitor\DelNodeVisitor;
use HtmlSanitizer\Extension\Basic\NodeVisitor\DivNodeVisitor;
use HtmlSanitizer\Extension\Basic\NodeVisitor\EmNodeVisitor;
use HtmlSanitizer\Extension\Basic\NodeVisitor\FigcaptionNodeVisitor;
use HtmlSanitizer\Extension\Basic\NodeVisitor\FigureNodeVisitor;
use HtmlSanitizer\Extension\Basic\NodeVisitor\H1NodeVisitor;
use HtmlSanitizer\Extension\Basic\NodeVisitor\H2NodeVisitor;
use HtmlSanitizer\Extension\Basic\NodeVisitor\H3NodeVisitor;
use HtmlSanitizer\Extension\Basic\NodeVisitor\H4NodeVisitor;
use HtmlSanitizer\Extension\Basic\NodeVisitor\INodeVisitor;
use HtmlSanitizer\Extension\Basic\NodeVisitor\PNodeVisitor;
use HtmlSanitizer\Extension\Basic\NodeVisitor\QNodeVisitor;
use HtmlSanitizer\Extension\Basic\NodeVisitor\SpanNodeVisitor;
use HtmlSanitizer\Extension\Basic\NodeVisitor\StrongNodeVisitor;
use HtmlSanitizer\Extension\Basic\NodeVisitor\SubNodeVisitor;
use HtmlSanitizer\Extension\Basic\NodeVisitor\SupNodeVisitor;
use HtmlSanitizer\Extension\Basic\NodeVisitor\UNodeVisitor;
use HtmlSanitizer\Extension\ExtensionInterface;
use HtmlSanitizer\Extension\Extra\Node\HrNode;
use HtmlSanitizer\Extension\Extra\NodeVisitor\HrNodeVisitor;
use HtmlSanitizer\Extension\Iframe\NodeVisitor\IframeNodeVisitor;
use HtmlSanitizer\Extension\Image\NodeVisitor\ImgNodeVisitor;
use HtmlSanitizer\Extension\Listing\NodeVisitor\DdNodeVisitor;
use HtmlSanitizer\Extension\Listing\NodeVisitor\DlNodeVisitor;
use HtmlSanitizer\Extension\Listing\NodeVisitor\DtNodeVisitor;
use HtmlSanitizer\Extension\Listing\NodeVisitor\LiNodeVisitor;
use HtmlSanitizer\Extension\Listing\NodeVisitor\OlNodeVisitor;
use HtmlSanitizer\Extension\Listing\NodeVisitor\UlNodeVisitor;

class PublicationExtension implements ExtensionInterface
{
    public function getName(): string
    {
        return 'publication';
    }

    public function createNodeVisitors(array $config = []): array
    {
        return [
            # Basics
            'a' => new ANodeVisitor($config['tags']['a'] ?? []),
            'blockquote' => new BlockquoteNodeVisitor($config['tags']['blockquote'] ?? []),
            'br' => new BrNodeVisitor($config['tags']['br'] ?? []),
            'div' => new DivNodeVisitor($config['tags']['div'] ?? []),
            'em' => new EmNodeVisitor($config['tags']['em'] ?? []),
            'h2' => new H2NodeVisitor($config['tags']['h2'] ?? []),
            'h3' => new H3NodeVisitor($config['tags']['h3'] ?? []),
            'p' => new PNodeVisitor($config['tags']['p'] ?? []),
            'span' => new SpanNodeVisitor($config['tags']['span'] ?? []),
            'strong' => new StrongNodeVisitor($config['tags']['strong'] ?? []),
            'hr' => new HrNodeVisitor($config['tags']['hr'] ?? []),

            # Listing
            'dd' => new DdNodeVisitor($config['tags']['dd'] ?? []),
            'dl' => new DlNodeVisitor($config['tags']['dl'] ?? []),
            'dt' => new DtNodeVisitor($config['tags']['dt'] ?? []),
            'li' => new LiNodeVisitor($config['tags']['li'] ?? []),
            'ol' => new OlNodeVisitor($config['tags']['ol'] ?? []),
            'ul' => new UlNodeVisitor($config['tags']['ul'] ?? []),

            # Image
            'img' => new ImgNodeVisitor($config['tags']['img'] ?? []),

            # Iframe
            'iframe' => new IframeNodeVisitor($config['tags']['iframe'] ?? []),
        ];
    }
}