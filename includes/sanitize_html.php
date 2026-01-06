<?php
/**
 * sanitize_html.php
 * Conservative HTML sanitizer aligned with TinyMCE media allowlist.
 * - Keeps common formatting tags and media (iframe/audio/video/source)
 * - Strips disallowed tags/attributes and dangerous protocols
 * - Restricts iframe src to a safe list of domains
 */

if (!function_exists('sanitize_html')) {
    function sanitize_html($html, array $options = []) {
        $html = (string)$html;
        if ($html === '') return '';

        $allowedDomains = $options['allowed_iframe_domains'] ?? [
            'youtube.com','www.youtube.com','youtu.be',
            'vimeo.com','player.vimeo.com',
            'soundcloud.com','w.soundcloud.com'
        ];

        $allowedTags = [
            // Inline/formatting
            'b','strong','i','em','u','s','span','small','sub','sup','code','kbd','mark','abbr','cite',
            // Blocks
            'p','br','div','section','article','blockquote','pre',
            // Lists
            'ul','ol','li',
            // Headings
            'h1','h2','h3','h4',
            // Tables
            'table','thead','tbody','tr','th','td','tfoot','caption',
            // Links
            'a',
            // Media
            'iframe','audio','video','source'
        ];

        $allowedAttrs = [
            'a' => ['href','title','target','rel'],
            'span' => ['style','class'],
            'div' => ['style','class'],
            'p' => ['style','class'],
            'h1' => ['style','class'], 'h2' => ['style','class'], 'h3' => ['style','class'], 'h4' => ['style','class'],
            'ul' => ['class','style'], 'ol' => ['class','style'], 'li' => ['class','style'],
            'table' => ['class','style','border','cellpadding','cellspacing'],
            'thead' => ['class','style'], 'tbody' => ['class','style'], 'tr' => ['class','style'],
            'th' => ['class','style','colspan','rowspan'], 'td' => ['class','style','colspan','rowspan'], 'tfoot' => ['class','style'], 'caption' => ['class','style'],
            // Media attributes
            'iframe' => ['src','width','height','frameborder','style','scrolling','class','name','allow','allowfullscreen','loading','referrerpolicy'],
            'audio' => ['controls','src','class','preload','autoplay','loop','muted','playsinline','controlslist','crossorigin'],
            'video' => ['controls','src','class','width','height','poster','preload','autoplay','loop','muted','playsinline','controlslist','crossorigin'],
            'source' => ['src','type']
        ];

        // First pass (optional): HTMLPurifier for robust cleaning of general HTML
        $purified = null;
        $autoloader = dirname(__DIR__) . '/vendor/autoload.php';
        if (file_exists($autoloader)) {
            /** @noinspection PhpIncludeInspection */
            @require_once $autoloader;
            if (class_exists('HTMLPurifier')) {
                $config = HTMLPurifier_Config::createDefault();
                $config->set('Cache.DefinitionImpl', null);
                // Allow a conservative set of elements/attributes
                $allowed = [
                    // inline
                    'b','strong','i','em','u','s','span','small','sub','sup','code','kbd','mark','abbr','cite',
                    // blocks
                    'p','br','div','section','article','blockquote','pre',
                    // lists
                    'ul','ol','li',
                    // headings
                    'h1','h2','h3','h4',
                    // tables
                    'table','thead','tbody','tr','th','td','tfoot','caption',
                    // links
                    'a',
                    // media elements must be kept here so they survive purifier
                    'iframe','audio','video','source'
                ];
                $config->set('HTML.AllowedElements', implode(',', $allowed));
                $config->set('HTML.AllowedAttributes', [
                    'a.href', 'a.title', 'a.target', 'a.rel',
                    'span.style', 'span.class',
                    'div.style', 'div.class',
                    'p.style', 'p.class',
                    'h1.style','h1.class','h2.style','h2.class','h3.style','h3.class','h4.style','h4.class',
                    'ul.class','ul.style','ol.class','ol.style','li.class','li.style',
                    'table.class','table.style','table.border','table.cellpadding','table.cellspacing',
                    'thead.class','thead.style','tbody.class','tbody.style','tr.class','tr.style',
                    'th.class','th.style','th.colspan','th.rowspan','td.class','td.style','td.colspan','td.rowspan','tfoot.class','tfoot.style','caption.class','caption.style',
                    // media attributes (exclude attributes not supported by this HTMLPurifier version)
                    'iframe.src','iframe.width','iframe.height','iframe.frameborder','iframe.style','iframe.scrolling','iframe.class','iframe.name','iframe.allow','iframe.allowfullscreen',
                    'audio.controls','audio.src','audio.class','audio.preload','audio.autoplay','audio.loop','audio.muted','audio.playsinline','audio.controlslist','audio.crossorigin',
                    'video.controls','video.src','video.class','video.width','video.height','video.poster','video.preload','video.autoplay','video.loop','video.muted','video.playsinline','video.controlslist','video.crossorigin',
                    'source.src','source.type'
                ]);
                // Safe CSS properties
                $config->set('CSS.AllowedProperties', [
                    'color','background-color','text-align','float','margin','margin-left','margin-right','margin-top','margin-bottom',
                    'padding','padding-left','padding-right','padding-top','padding-bottom',
                    'border','border-color','border-width','border-style','border-collapse',
                    'width','height','max-width','min-width','max-height','min-height',
                    'font','font-size','font-weight','font-style','text-decoration','vertical-align','display'
                ]);
                $config->set('Attr.AllowedFrameTargets', ['_blank','_self']);
                // Allow safe iframes for a curated list of domains
                // Build a SafeIframeRegexp from the $allowedDomains array
                $domains = array_map(function($d){
                    $d = preg_quote($d, '#');
                    // allow subdomains
                    return '(?:[^/]+\.)?' . $d;
                }, $allowedDomains);
                $domainPattern = implode('|', $domains);
                $safeIframeRegex = '#^(https?:)?//(' . $domainPattern . ')/#i';
                $config->set('HTML.SafeIframe', true);
                $config->set('URI.SafeIframeRegexp', $safeIframeRegex);

                // Define basic HTML5 elements that HTMLPurifier doesn't include by default
                // to avoid warnings like: Element 'mark' is not supported
                $config->set('HTML.DefinitionID', 'ak23downloadapp-html5');
                $config->set('HTML.DefinitionRev', 1);
                if ($def = $config->maybeGetRawHTMLDefinition()) {
                    // Block-level sections
                    if (!isset($def->info['section'])) {
                        $def->addElement('section', 'Block', 'Flow', 'Common');
                    }
                    if (!isset($def->info['article'])) {
                        $def->addElement('article', 'Block', 'Flow', 'Common');
                    }
                    // Inline highlight
                    if (!isset($def->info['mark'])) {
                        $def->addElement('mark', 'Inline', 'Inline', 'Common');
                    }

                    // HTML5 media elements
                    // Iframe (safe, with curated domains handled via SafeIframeRegexp)
                    if (!isset($def->info['iframe'])) {
                        $def->addElement('iframe', 'Inline', 'Flow', 'Common', [
                            'src' => 'URI',
                            'width' => 'Length',
                            'height' => 'Length',
                            'frameborder' => 'Text',
                            'style' => 'Text',
                            'scrolling' => 'Enum#auto,yes,no',
                            'class' => 'Text',
                            'name' => 'Text',
                            'allow' => 'Text',
                            'allowfullscreen' => 'Bool'
                        ]);
                    } else {
                        // Ensure modern attributes exist even if iframe is predefined
                        $def->addAttribute('iframe', 'allow', 'Text');
                        $def->addAttribute('iframe', 'allowfullscreen', 'Bool');
                        $def->addAttribute('iframe', 'name', 'Text');
                        $def->addAttribute('iframe', 'class', 'Text');
                        $def->addAttribute('iframe', 'scrolling', 'Enum#auto,yes,no');
                        $def->addAttribute('iframe', 'frameborder', 'Text');
                    }
                    if (!isset($def->info['audio'])) {
                        $def->addElement('audio', 'Block', 'Flow', 'Common', [
                            'src' => 'URI',
                            'controls' => 'Bool',
                            'preload' => 'Enum#auto,metadata,none',
                            'autoplay' => 'Bool',
                            'loop' => 'Bool',
                            'muted' => 'Bool',
                            'class' => 'Text'
                        ]);
                        // Add modern attributes
                        $def->addAttribute('audio', 'playsinline', 'Bool');
                        $def->addAttribute('audio', 'controlslist', 'Text');
                        $def->addAttribute('audio', 'crossorigin', 'Text');
                    } else {
                        // Ensure modern attributes on existing definition
                        $def->addAttribute('audio', 'playsinline', 'Bool');
                        $def->addAttribute('audio', 'controlslist', 'Text');
                        $def->addAttribute('audio', 'crossorigin', 'Text');
                    }
                    if (!isset($def->info['video'])) {
                        $def->addElement('video', 'Block', 'Flow', 'Common', [
                            'src' => 'URI',
                            'controls' => 'Bool',
                            'width' => 'Length',
                            'height' => 'Length',
                            'poster' => 'URI',
                            'preload' => 'Enum#auto,metadata,none',
                            'autoplay' => 'Bool',
                            'loop' => 'Bool',
                            'muted' => 'Bool',
                            'class' => 'Text'
                        ]);
                        // Add modern attributes
                        $def->addAttribute('video', 'playsinline', 'Bool');
                        $def->addAttribute('video', 'controlslist', 'Text');
                        $def->addAttribute('video', 'crossorigin', 'Text');
                    } else {
                        // Ensure modern attributes on existing definition
                        $def->addAttribute('video', 'playsinline', 'Bool');
                        $def->addAttribute('video', 'controlslist', 'Text');
                        $def->addAttribute('video', 'crossorigin', 'Text');
                    }
                    if (!isset($def->info['source'])) {
                        // 'source' is an empty element used inside audio/video
                        $def->addElement('source', 'Block', 'Empty', 'Common', [
                            'src' => 'URI',
                            'type' => 'Text'
                        ]);
                    }
                }

                $purifier = new HTMLPurifier($config);
                // Suppress CSS property warnings while keeping security
                set_error_handler(function($errno, $errstr) {
                    // Only suppress warnings about style attributes
                    if ($errno === E_USER_WARNING && strpos($errstr, 'Style attribute') === 0) {
                        return true; // suppress this warning
                    }
                    // Let other errors through
                    return false;
                }, E_USER_WARNING);
                
                try {
                    $purified = $purifier->purify($html);
                } finally {
                    // Restore the previous error handler
                    restore_error_handler();
                }
            }
        }

        if (is_string($purified)) {
            $html = $purified;
        }

        // Drop script/style blocks fast (in case remaining)
        $html = preg_replace('#<(script|style)[^>]*>.*?</\\1>#is', '', $html);

        $doc = new DOMDocument();
        $doc->encoding = 'UTF-8';
        $doc->preserveWhiteSpace = false;
        // Wrap fragment to parse reliably
        $wrapped = '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body><div id="__wrap__">' . $html . '</div></body></html>';
        libxml_use_internal_errors(true);
        $doc->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $xpath = new DOMXPath($doc);
        $container = $xpath->query('//div[@id="__wrap__"]')->item(0);
        if (!$container) return '';

        $safeUrl = function($url) {
            $url = trim($url);
            if ($url === '') return '';
            // Allow http, https, mailto, tel, blob, data, and relative URLs
            if (preg_match('#^(https?:|mailto:|tel:|blob:|data:|/)#i', $url)) return $url;
            return '';
        };

        $isAllowedDomain = function($url) use ($allowedDomains) {
            if ($url === '') return false;
            $parts = @parse_url($url);
            if (!$parts || empty($parts['host'])) return false;
            $host = strtolower($parts['host']);
            foreach ($allowedDomains as $d) {
                $d = strtolower($d);
                if ($host === $d || substr($host, -strlen('.'.$d)) === '.'.$d) return true;
            }
            return false;
        };

        $walk = function(DOMNode $node) use (&$walk, $doc, $allowedTags, $allowedAttrs, $safeUrl, $isAllowedDomain) {
            // Copy list first as we may remove children
            foreach (iterator_to_array($node->childNodes) as $child) {
                if ($child->nodeType === XML_ELEMENT_NODE) {
                    $tag = strtolower($child->nodeName);
                    if (!in_array($tag, $allowedTags, true)) {
                        // Drop tag but keep its children (unwrap)
                        while ($child->firstChild) {
                            $node->insertBefore($child->firstChild, $child);
                        }
                        $node->removeChild($child);
                        continue;
                    }
                    // Clean attributes
                    $allowed = $allowedAttrs[$tag] ?? [];
                    if ($child->hasAttributes()) {
                        // Collect to remove safely
                        $toRemove = [];
                        foreach ($child->attributes as $attr) {
                            $an = strtolower($attr->nodeName);
                            $av = $attr->nodeValue;
                            // Remove event handlers and scripts
                            if (strpos($an, 'on') === 0) { $toRemove[] = $an; continue; }
                            if (!in_array($an, $allowed, true)) { $toRemove[] = $an; continue; }
                            if (in_array($an, ['href','src'], true)) {
                                $safe = $safeUrl($av);
                                if ($safe === '') { $toRemove[] = $an; continue; }
                                if ($tag === 'iframe' && $an === 'src' && !$isAllowedDomain($safe)) { $toRemove[] = $an; continue; }
                                $child->setAttribute($an, $safe);
                            }
                        }
                        foreach ($toRemove as $rm) { $child->removeAttribute($rm); }
                    }
                    // For iframe without src after cleaning, drop it
                    if ($tag === 'iframe' && !$child->hasAttribute('src')) {
                        $node->removeChild($child);
                        continue;
                    }
                    // Recurse
                    $walk($child);
                } elseif ($child->nodeType === XML_COMMENT_NODE) {
                    // Strip comments
                    $node->removeChild($child);
                } else {
                    // Keep text nodes
                }
            }
        };

        $walk($container);

        // Return innerHTML of container
        $out = '';
        foreach ($container->childNodes as $cn) {
            $out .= $doc->saveHTML($cn);
        }
        return $out;
    }
}
