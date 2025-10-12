<?php

/**
 * Content sanitizer for HTML and URLs
 * 
 * Provides allowlist-based sanitization to prevent XSS attacks
 * while allowing safe formatting and links.
 */
class Sanitizer
{
    private static array $allowedTags = [
        'a', 'p', 'br', 'strong', 'em', 'ul', 'ol', 'li', 'blockquote'
    ];
    
    private static array $allowedAttributes = [
        'a' => ['href', 'rel', 'target']
    ];
    
    /**
     * Sanitize HTML content using allowlist approach
     * 
     * @param string|null $html Raw HTML content
     * @return string|null Sanitized HTML or null if input was invalid
     */
    public static function sanitizeHtml(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return null;
        }
        
        // Enforce length limit
        if (strlen($html) > 1000) {
            $html = substr($html, 0, 1000);
        }
        
        // Load HTML into DOMDocument for parsing
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        
        // Wrap in div to handle fragments properly
        $wrappedHtml = '<div>' . $html . '</div>';
        $dom->loadHTML($wrappedHtml, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        
        // Clear libxml errors
        libxml_clear_errors();
        
        // Process all elements recursively
        self::sanitizeNode($dom->documentElement);
        
        // Extract content from wrapper div
        $result = '';
        $wrapper = $dom->documentElement; // The <div> we wrapped around input
        if ($wrapper) {
            foreach ($wrapper->childNodes as $node) {
                $result .= $dom->saveHTML($node);
            }
        }
        
        // Final cleanup - remove any remaining dangerous content
        $result = self::removeJavaScript($result);
        
        return trim($result) !== '' ? trim($result) : null;
    }
    
    /**
     * Recursively sanitize DOM nodes
     */
    private static function sanitizeNode(DOMNode $node): void
    {
        $nodesToRemove = [];
        $nodesToProcess = [];
        
        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMElement) {
                $tagName = strtolower($child->tagName);
                
                if (!in_array($tagName, self::$allowedTags)) {
                    // Remove disallowed tags but keep their content
                    $nodesToRemove[] = $child;
                } else {
                    // Clean attributes for allowed tags
                    self::cleanAttributes($child, $tagName);
                    $nodesToProcess[] = $child;
                }
            } elseif ($child instanceof DOMText) {
                // Text nodes are safe, keep as-is
                continue;
            } else {
                // Remove other node types (comments, etc.)
                $nodesToRemove[] = $child;
            }
        }
        
        // Remove disallowed elements (but preserve their content)
        foreach ($nodesToRemove as $nodeToRemove) {
            if ($nodeToRemove instanceof DOMElement && $nodeToRemove->hasChildNodes()) {
                // Move children to parent before removing
                while ($nodeToRemove->firstChild) {
                    $node->insertBefore($nodeToRemove->firstChild, $nodeToRemove);
                }
            }
            $node->removeChild($nodeToRemove);
        }
        
        // Process remaining allowed elements
        foreach ($nodesToProcess as $childToProcess) {
            self::sanitizeNode($childToProcess);
        }
    }
    
    /**
     * Clean element attributes based on allowlist
     */
    private static function cleanAttributes(DOMElement $element, string $tagName): void
    {
        $allowedAttrs = self::$allowedAttributes[$tagName] ?? [];
        $attributesToRemove = [];
        
        foreach ($element->attributes as $attribute) {
            $attrName = strtolower($attribute->name);
            
            if (!in_array($attrName, $allowedAttrs)) {
                $attributesToRemove[] = $attrName;
            } elseif ($attrName === 'href') {
                // Validate href URLs
                $url = $attribute->value;
                if (!self::isValidHref($url)) {
                    $attributesToRemove[] = $attrName;
                }
            }
        }
        
        // Remove disallowed attributes
        foreach ($attributesToRemove as $attrName) {
            $element->removeAttribute($attrName);
        }
    }
    
    /**
     * Check if href URL is safe
     */
    private static function isValidHref(string $url): bool
    {
        $url = trim($url);
        
        // Block dangerous protocols
        $dangerousProtocols = ['javascript:', 'data:', 'vbscript:', 'file:'];
        foreach ($dangerousProtocols as $protocol) {
            if (stripos($url, $protocol) === 0) {
                return false;
            }
        }
        
        // Require https for external URLs
        if (strpos($url, '://') !== false && stripos($url, 'https://') !== 0) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Remove JavaScript and event handlers from HTML
     */
    private static function removeJavaScript(string $html): string
    {
        // Remove script tags
        $html = preg_replace('/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi', '', $html);
        
        // Remove event handlers
        $html = preg_replace('/\s*on\w+\s*=\s*["\'][^"\']*["\']/i', '', $html);
        
        // Remove javascript: URLs that might have survived
        $html = preg_replace('/href\s*=\s*["\']javascript:[^"\']*["\']/i', '', $html);
        
        return $html;
    }
    
    /**
     * Sanitize and validate URLs for specific domains
     * 
     * @param string|null $url URL to validate
     * @param string $context Context for validation ('nhl_goal' or 'youtube')
     * @return string|null Valid URL or null if invalid
     */
    public static function sanitizeUrl(?string $url, string $context = 'general'): ?string
    {
        if ($url === null || trim($url) === '') {
            return null;
        }
        
        $url = trim($url);
        
        // Enforce length limit
        if (strlen($url) > 1000) {
            return null;
        }
        
        // Must be HTTPS
        if (stripos($url, 'https://') !== 0) {
            return null;
        }
        
        // Context-specific domain validation
        switch ($context) {
            case 'nhl_goal':
                $allowedDomains = ['nhl.com', 'www.nhl.com'];
                break;
                
            case 'youtube':
                $allowedDomains = [
                    'youtube.com', 'www.youtube.com', 
                    'youtu.be', 'youtube-nocookie.com', 'www.youtube-nocookie.com'
                ];
                break;
                
            default:
                // General URL validation - allow any HTTPS URL
                return filter_var($url, FILTER_VALIDATE_URL) ? $url : null;
        }
        
        // Extract domain from URL
        $parsedUrl = parse_url($url);
        if (!$parsedUrl || !isset($parsedUrl['host'])) {
            return null;
        }
        
        $domain = strtolower($parsedUrl['host']);
        
        // Check if domain is in allowlist
        $domainAllowed = false;
        foreach ($allowedDomains as $allowedDomain) {
            if ($domain === $allowedDomain || str_ends_with($domain, '.' . $allowedDomain)) {
                $domainAllowed = true;
                break;
            }
        }
        
        if (!$domainAllowed) {
            return null;
        }
        
        return filter_var($url, FILTER_VALIDATE_URL) ? $url : null;
    }
}