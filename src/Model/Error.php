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


namespace Coding9\ElasticApmBundle\Model;

class Error
{
    private string $id;
    private \Throwable $exception;
    private ?Transaction $transaction = null;
    private ?string $traceId = null;
    private array $context = [];
    private float $timestamp;
    
    public function __construct(\Throwable $exception, ?Transaction $transaction = null)
    {
        $this->id = $this->generateId();
        $this->exception = $exception;
        $this->transaction = $transaction;
        $this->timestamp = microtime(true);
        
        if ($transaction) {
            $this->traceId = $transaction->getTraceId();
        }
    }
    
    public function getId(): string
    {
        return $this->id;
    }
    
    public function getException(): \Throwable
    {
        return $this->exception;
    }
    
    public function setContext(array $context): void
    {
        $this->context = array_merge($this->context, $context);
    }
    
    public function getContext(): array
    {
        return $this->context;
    }
    
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'timestamp' => (int)($this->timestamp * 1000000), // microseconds
            'trace_id' => $this->traceId,
            'transaction_id' => $this->transaction ? $this->transaction->getId() : null,
            'parent_id' => $this->transaction ? $this->transaction->getId() : null,
            'exception' => $this->formatExceptionChain($this->exception),
            'context' => $this->context,
            'culprit' => $this->getCulprit($this->exception),
        ];
    }
    
    private function formatExceptionChain(\Throwable $exception): array
    {
        $exceptions = [];
        $current = $exception;
        
        // Capture full exception chain
        while ($current !== null) {
            $exceptions[] = [
                'message' => $current->getMessage(),
                'type' => get_class($current),
                'code' => $current->getCode(),
                'stacktrace' => $this->formatStackTrace($current),
                'handled' => false,
            ];
            
            $current = $current->getPrevious();
        }
        
        return $exceptions;
    }
    
    private function formatStackTrace(\Throwable $exception): array
    {
        $frames = [];
        
        // Add the exception location as the first frame
        $frames[] = [
            'filename' => $exception->getFile(),
            'lineno' => $exception->getLine(),
            'function' => '{main}',
            'classname' => null,
            'method' => '{main}',
            'vars' => [],
            'pre_context' => $this->getContextLines($exception->getFile(), $exception->getLine(), -3),
            'context_line' => $this->getContextLine($exception->getFile(), $exception->getLine()),
            'post_context' => $this->getContextLines($exception->getFile(), $exception->getLine(), 3),
        ];
        
        foreach ($exception->getTrace() as $index => $frame) {
            $formattedFrame = [
                'filename' => $frame['file'] ?? 'unknown',
                'lineno' => $frame['line'] ?? 0,
                'function' => $frame['function'] ?? 'unknown',
                'classname' => $frame['class'] ?? null,
                'method' => isset($frame['class']) ? $frame['class'] . '::' . $frame['function'] : $frame['function'] ?? 'unknown',
                'vars' => $this->formatArgs($frame['args'] ?? []),
                'library_frame' => $this->isLibraryFrame($frame['file'] ?? ''),
            ];
            
            // Add context lines if available
            if (isset($frame['file']) && isset($frame['line']) && file_exists($frame['file'])) {
                $formattedFrame['pre_context'] = $this->getContextLines($frame['file'], $frame['line'], -3);
                $formattedFrame['context_line'] = $this->getContextLine($frame['file'], $frame['line']);
                $formattedFrame['post_context'] = $this->getContextLines($frame['file'], $frame['line'], 3);
            }
            
            $frames[] = $formattedFrame;
        }
        
        return $frames;
    }
    
    private function getCulprit(\Throwable $exception): string
    {
        $trace = $exception->getTrace();
        
        if (empty($trace)) {
            return sprintf('%s in %s', get_class($exception), basename($exception->getFile()));
        }
        
        $firstFrame = $trace[0];
        
        if (isset($firstFrame['class'])) {
            return $firstFrame['class'] . '::' . ($firstFrame['function'] ?? 'unknown');
        }
        
        return $firstFrame['function'] ?? basename($exception->getFile());
    }
    
    private function getContextLine(string $file, int $line): ?string
    {
        if (!file_exists($file) || $line <= 0) {
            return null;
        }
        
        $lines = file($file);
        return isset($lines[$line - 1]) ? rtrim($lines[$line - 1]) : null;
    }
    
    private function getContextLines(string $file, int $line, int $offset): array
    {
        if (!file_exists($file) || $line <= 0) {
            return [];
        }
        
        $lines = file($file);
        $contextLines = [];
        
        $start = max(0, $line - 1 + ($offset < 0 ? $offset : 1));
        $end = min(count($lines), $line - 1 + ($offset > 0 ? $offset : 0));
        
        for ($i = $start; $i <= $end; $i++) {
            if (isset($lines[$i]) && $i !== $line - 1) {
                $contextLines[] = rtrim($lines[$i]);
            }
        }
        
        return $contextLines;
    }
    
    private function formatArgs(array $args): array
    {
        $formatted = [];
        
        foreach ($args as $arg) {
            $formatted[] = $this->formatArg($arg);
        }
        
        return $formatted;
    }
    
    private function formatArg($arg): string
    {
        if (is_null($arg)) {
            return 'null';
        }
        
        if (is_bool($arg)) {
            return $arg ? 'true' : 'false';
        }
        
        if (is_string($arg)) {
            return strlen($arg) > 100 ? substr($arg, 0, 100) . '...' : $arg;
        }
        
        if (is_numeric($arg)) {
            return (string) $arg;
        }
        
        if (is_array($arg)) {
            return 'Array(' . count($arg) . ')';
        }
        
        if (is_object($arg)) {
            return get_class($arg);
        }
        
        return gettype($arg);
    }
    
    private function isLibraryFrame(string $file): bool
    {
        return str_contains($file, '/vendor/') || str_contains($file, '/var/cache/');
    }
    
    private function generateId(): string
    {
        return bin2hex(random_bytes(8));
    }
}