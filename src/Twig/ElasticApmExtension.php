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


namespace ElasticApmBundle\Twig;

use ElasticApmBundle\Interactor\ElasticApmInteractorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig extension for APM trace context functions
 */
class ElasticApmExtension extends AbstractExtension
{
    private ElasticApmInteractorInterface $interactor;

    public function __construct(ElasticApmInteractorInterface $interactor)
    {
        $this->interactor = $interactor;
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('apm_trace_context', [$this, 'getTraceContext']),
            new TwigFunction('apm_current_transaction', [$this, 'getCurrentTransaction']),
        ];
    }

    /**
     * Get current trace context for distributed tracing
     */
    public function getTraceContext(): array
    {
        return $this->interactor->getTraceContext();
    }

    /**
     * Get current transaction for debugging purposes
     */
    public function getCurrentTransaction(): ?\ElasticApmBundle\Model\Transaction
    {
        return $this->interactor->getCurrentTransaction();
    }
}