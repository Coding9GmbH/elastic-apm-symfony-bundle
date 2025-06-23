<?php
// Copyright (c) 2025 Coding 9 GmbH
//
// Permission is hereby granted, free of charge, to any person obtaining a copy of
// this software and associated documentation files (the "Software"), to deal in
// the Software without restriction, including without limitation the rights to
// use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of
// the Software, and to permit persons to whom the Software is furnished to do so,
// subject to the following conditions:
//
// The above copyright notice and this permission notice shall be included in all
// copies or substantial portions of the Software.
//
// THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
// IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS
// FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR
// COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER
// IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN
// CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.


namespace Coding9\ElasticApmBundle\Listener;

use Coding9\ElasticApmBundle\Interactor\ElasticApmInteractorInterface;
use Doctrine\DBAL\Logging\SQLLogger;

class DoctrineListener implements SQLLogger
{
    private ElasticApmInteractorInterface $interactor;
    private array $querySpans = [];
    private int $queryCount = 0;

    public function __construct(ElasticApmInteractorInterface $interactor)
    {
        $this->interactor = $interactor;
    }

    public function startQuery($sql, ?array $params = null, ?array $types = null): void
    {
        $queryId = ++$this->queryCount;
        
        // Parse query type
        $queryType = $this->getQueryType($sql);
        
        $span = $this->interactor->startSpan(
            sprintf('DB %s', $queryType),
            'db',
            'sql'
        );
        
        // Add query context
        $this->interactor->setCustomContext([
            'db' => [
                'statement' => $this->sanitizeQuery($sql),
                'type' => 'sql',
                'instance' => 'default',
            ]
        ]);
        
        $this->querySpans[$queryId] = $span;
    }

    public function stopQuery(): void
    {
        $queryId = $this->queryCount;
        
        if (isset($this->querySpans[$queryId])) {
            $this->interactor->stopSpan($this->querySpans[$queryId]);
            unset($this->querySpans[$queryId]);
        }
    }
    
    private function getQueryType(string $sql): string
    {
        $sql = trim($sql);
        $firstWord = strtoupper(substr($sql, 0, strpos($sql . ' ', ' ')));
        
        return match ($firstWord) {
            'SELECT' => 'SELECT',
            'INSERT' => 'INSERT',
            'UPDATE' => 'UPDATE',
            'DELETE' => 'DELETE',
            'CREATE' => 'CREATE',
            'DROP' => 'DROP',
            'ALTER' => 'ALTER',
            default => 'QUERY',
        };
    }
    
    private function sanitizeQuery(string $sql): string
    {
        // Limit query length for performance
        if (strlen($sql) > 10000) {
            return substr($sql, 0, 10000) . '... (truncated)';
        }
        
        return $sql;
    }
}