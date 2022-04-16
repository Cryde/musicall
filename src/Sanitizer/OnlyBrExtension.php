<?php

namespace App\Sanitizer;

use HtmlSanitizer\Extension\Basic\NodeVisitor\BrNodeVisitor;
use HtmlSanitizer\Extension\ExtensionInterface;

class OnlyBrExtension implements ExtensionInterface
{
    public function getName(): string
    {
        return 'only-br';
    }

    public function createNodeVisitors(array $config = []): array
    {
        return [
            'br' => new BrNodeVisitor($config['tags']['br'] ?? []),
        ];
    }
}