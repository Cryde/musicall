<?php

namespace App\Extensions\Doctrine;

use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\AST\Node;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\QueryException;
use Doctrine\ORM\Query\SqlWalker;
use Doctrine\ORM\Query\TokenType;

class MatchAgainst extends FunctionNode
{
    protected ?array $pathExp = null;
    protected Node|null $against = null;
    protected bool $booleanMode = false;
    protected bool $queryExpansion = false;

    /**
     * @throws QueryException
     */
    public function parse(Parser $parser): void
    {
        // match
        $parser->match(TokenType::T_IDENTIFIER);
        $parser->match(TokenType::T_OPEN_PARENTHESIS);
        // first Path Expression is mandatory
        $this->pathExp = [];
        $this->pathExp[] = $parser->StateFieldPathExpression();
        // Subsequent Path Expressions are optional
        $lexer = $parser->getLexer();
        while ($lexer->isNextToken(TokenType::T_COMMA)) {
            $parser->match(TokenType::T_COMMA);
            $this->pathExp[] = $parser->StateFieldPathExpression();
        }

        $parser->match(TokenType::T_CLOSE_PARENTHESIS);
        // against
        if (strtolower($lexer->lookahead->value) !== 'against') {
            $parser->syntaxError('against');
        }
        $parser->match(TokenType::T_IDENTIFIER);
        $parser->match(TokenType::T_OPEN_PARENTHESIS);
        $this->against = $parser->StringPrimary();
        if (strtolower($lexer->lookahead->value) === 'boolean') {
            $parser->match(TokenType::T_IDENTIFIER);
            $this->booleanMode = true;
        }
        if (strtolower($lexer->lookahead->value) === 'expand') {
            $parser->match(TokenType::T_IDENTIFIER);
            $this->queryExpansion = true;
        }
        $parser->match(TokenType::T_CLOSE_PARENTHESIS);
    }

    public function getSql(SqlWalker $walker): string
    {
        $fields = [];
        foreach ($this->pathExp as $pathExp) {
            $fields[] = $pathExp->dispatch($walker);
        }
        $against = $walker->walkStringPrimary($this->against)
            . ($this->booleanMode ? ' IN BOOLEAN MODE' : '')
            . ($this->queryExpansion ? ' WITH QUERY EXPANSION' : '');

        return sprintf('MATCH (%s) AGAINST (%s)', implode(', ', $fields), $against);
    }
}