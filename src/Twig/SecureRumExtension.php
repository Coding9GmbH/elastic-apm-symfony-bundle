<?php

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