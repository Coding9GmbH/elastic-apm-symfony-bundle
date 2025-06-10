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

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Secure RUM extension that embeds configuration directly in templates
 * This avoids exposing configuration via API endpoints
 */
class SecureRumExtension extends AbstractExtension
{
    private array $rumConfig;

    public function __construct(array $rumConfig)
    {
        $this->rumConfig = $rumConfig;
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('apm_rum_script', [$this, 'getRumScript'], ['is_safe' => ['html']]),
            new TwigFunction('apm_rum_config_inline', [$this, 'getRumConfigInline'], ['is_safe' => ['html']]),
        ];
    }

    /**
     * Generate inline RUM script with embedded configuration
     * This is more secure than exposing config via API
     */
    public function getRumScript(): string
    {
        if (!$this->rumConfig['enabled']) {
            return '<!-- APM RUM disabled -->';
        }

        // Only include safe, non-sensitive configuration
        $safeConfig = [
            'serviceName' => $this->rumConfig['service_name'],
            'serverUrl' => $this->rumConfig['server_url'],
            // Removed: version, environment (internal details)
        ];

        $configJson = json_encode($safeConfig, JSON_HEX_TAG | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_APOS);

        return <<<HTML
<!-- Elastic APM RUM Agent (Secure Inline Config) -->
<script src="https://unpkg.com/@elastic/apm-rum@5/dist/bundles/elastic-apm-rum.umd.min.js" crossorigin></script>
<script>
  if (typeof elasticApm !== 'undefined') {
    elasticApm.init({$configJson});
  }
</script>
HTML;
    }

    /**
     * Get RUM configuration as inline JavaScript object
     * Use this if you want to handle RUM initialization yourself
     */
    public function getRumConfigInline(): string
    {
        if (!$this->rumConfig['enabled']) {
            return 'window.apmConfig = { enabled: false };';
        }

        $safeConfig = [
            'enabled' => true,
            'serviceName' => $this->rumConfig['service_name'],
            'serverUrl' => $this->rumConfig['server_url'],
        ];

        $configJson = json_encode($safeConfig, JSON_HEX_TAG | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_APOS);

        return "window.apmConfig = {$configJson};";
    }
}