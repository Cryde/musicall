<?php

namespace App\Extensions\Doctrine;

use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\Lexer;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;

class STGeomFromText extends FunctionNode
{
    protected $expressions = [];

    public function parse(Parser $parser)
    {
        $parser->match(Lexer::T_IDENTIFIER);
        $parser->match(Lexer::T_OPEN_PARENTHESIS);

        $this->expressions[] = $parser->ArithmeticFactor();

        $lexer = $parser->getLexer();

        if ($lexer->lookahead['type'] === Lexer::T_COMMA) {
            $parser->match(Lexer::T_COMMA);
            $this->expressions[] = $parser->ArithmeticFactor();
        }

        $parser->match(Lexer::T_CLOSE_PARENTHESIS);
    }

    public function getSql(SqlWalker $sqlWalker)
    {
        $arguments = [];

        foreach ($this->expressions as $expression) {
            $arguments[] = $expression->dispatch($sqlWalker);
        }

        return 'ST_GeomFromText(' . implode(', ', $arguments) . ')';
    }
}