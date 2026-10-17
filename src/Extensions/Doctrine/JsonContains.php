<?php declare(strict_types=1);

namespace App\Extensions\Doctrine;

use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\AST\Node;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;
use Doctrine\ORM\Query\TokenType;

/**
 * `JSON_CONTAINS(target, candidate)`: 1 when the JSON document `candidate` is in `target`. The candidate
 * is JSON too, so a string is passed encoded (`"en"`).
 */
class JsonContains extends FunctionNode
{
    private Node $target;
    private Node $candidate;

    public function parse(Parser $parser): void
    {
        $parser->match(TokenType::T_IDENTIFIER);
        $parser->match(TokenType::T_OPEN_PARENTHESIS);
        $this->target = $parser->StringPrimary();
        $parser->match(TokenType::T_COMMA);
        $this->candidate = $parser->StringPrimary();
        $parser->match(TokenType::T_CLOSE_PARENTHESIS);
    }

    public function getSql(SqlWalker $walker): string
    {
        return sprintf('JSON_CONTAINS(%s, %s)', $this->target->dispatch($walker), $this->candidate->dispatch($walker));
    }
}
