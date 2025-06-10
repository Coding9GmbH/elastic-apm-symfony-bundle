<?php

namespace ElasticApmBundle\TransactionNamingStrategy;

use Symfony\Component\HttpFoundation\Request;

/**
 * Names transactions based on message queue operations
 * This strategy is used when transactions are initiated from message processing
 */
class MessageNamingStrategy implements TransactionNamingStrategyInterface
{
    public function getTransactionName(Request $request): string
    {
        // For HTTP requests, fall back to basic naming
        $method = $request->getMethod();
        $pathInfo = $request->getPathInfo();
        
        // Check if this might be a message-related endpoint
        if (str_contains($pathInfo, '/messenger/') || str_contains($pathInfo, '/queue/')) {
            return sprintf('%s Message endpoint %s', $method, $pathInfo);
        }
        
        return sprintf('%s %s', $method, $pathInfo);
    }

    /**
     * Get transaction name specifically for message processing
     */
    public function getMessageTransactionName(
        string $messageClass, 
        string $operation = 'process', 
        ?string $transportName = null,
        ?array $messageData = null
    ): string {
        $shortClassName = $this->getShortClassName($messageClass);
        
        // Try to build more descriptive name from message data
        if ($messageData) {
            if (isset($messageData['messageType'], $messageData['messageAction'])) {
                return sprintf('%s %s.%s', 
                    ucfirst($operation), 
                    $messageData['messageType'], 
                    $messageData['messageAction']
                );
            }
            
            if (isset($messageData['type'], $messageData['action'])) {
                return sprintf('%s %s.%s', 
                    ucfirst($operation), 
                    $messageData['type'], 
                    $messageData['action']
                );
            }
        }
        
        // Handle specific message patterns
        if (str_ends_with($shortClassName, 'Command')) {
            $commandName = substr($shortClassName, 0, -7); // Remove 'Command'
            return sprintf('%s %s command', ucfirst($operation), $commandName);
        }
        
        if (str_ends_with($shortClassName, 'Message')) {
            $messageName = substr($shortClassName, 0, -7); // Remove 'Message'
            return sprintf('%s %s message', ucfirst($operation), $messageName);
        }
        
        // Default format
        $baseName = sprintf('%s %s', ucfirst($operation), $shortClassName);
        
        if ($transportName && $transportName !== 'unknown') {
            return sprintf('%s via %s', $baseName, $transportName);
        }
        
        return $baseName;
    }

    /**
     * Get transaction name for external message operations
     */
    public function getExternalMessageTransactionName(
        string $messageType,
        string $messageAction, 
        string $operation = 'send',
        ?string $transportName = null
    ): string {
        $name = sprintf('%s %s.%s', ucfirst($operation), $messageType, $messageAction);
        
        if ($transportName && $transportName !== 'unknown') {
            return sprintf('%s via %s', $name, $transportName);
        }
        
        return $name;
    }

    private function getShortClassName(string $className): string
    {
        return substr(strrchr($className, '\\'), 1) ?: $className;
    }
}