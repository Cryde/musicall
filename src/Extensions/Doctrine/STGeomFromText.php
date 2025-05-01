<?php

namespace App\Extensions\Doctrine;

use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;
use Doctrine\ORM\Query\TokenType;

class STGeomFromText extends FunctionNode
{
    protected $expressions = [];

    public function parse(Parser $parser): void
    {
        $parser->match(TokenType::T_IDENTIFIER);
        $parser->match(TokenType::T_OPEN_PARENTHESIS);

        $this->expressions[] = $parser->ArithmeticFactor();

        $lexer = $parser->getLexer();

        if ($lexer->lookahead->type === TokenType::T_COMMA) {
            $parser->match(TokenType::T_COMMA);
            $this->expressions[] = $parser->ArithmeticFactor();
        }

        $parser->match(TokenType::T_CLOSE_PARENTHESIS);
    }

    public function getSql(SqlWalker $sqlWalker): string
    {
        $arguments = [];

        foreach ($this->expressions as $expression) {
            $arguments[] = $expression->dispatch($sqlWalker);
        }

        return 'ST_GeomFromText(' . implode(', ', $arguments) . ')';
    }
}