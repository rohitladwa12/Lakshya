<?php
/**
 * Basic PDF Parser
 * Now using smalot/pdfparser for robust extraction.
 * Fallback to manual regex if not available.
 */

if (file_exists(__DIR__ . '/../../vendor/autoload.php')) {
    require_once __DIR__ . '/../../vendor/autoload.php';
}

class BasicPdfParser {
    
    public function parseFile($filename) {
        if (!file_exists($filename)) {
            return "";
        }

        try {
            if (class_exists('\Smalot\PdfParser\Parser')) {
                $parser = new \Smalot\PdfParser\Parser();
                $pdf = $parser->parseFile($filename);
                $text = $pdf->getText();
                // Clean up excessive newlines but preserve structure
                $text = preg_replace('/[\r\n]{3,}/', "\n\n", $text);
                return trim($text);
            }
        } catch (Exception $e) {
            // Ignore error and use fallback
        }

        $content = file_get_contents($filename);
        if (!$content) {
            return "";
        }
        return $this->extractText($content);
    }

    private function extractText($data) {
        // Find all text objects
        // Text is usually between BT (Begin Text) and ET (End Text) commands
        // and inside () or [] inside those blocks.
        
        $text = '';
        
        // Very basic extraction: Look for text within parentheses ( )
        // This misses text in hex brackets < > and compressed streams.
        // But for a fallback, it's better than nothing.
        
        // Attempt to handle simple object streams if possible (often impossible without zlib decompress)
        // Check if we can use gzuncompress on streams
        if (function_exists('gzuncompress')) {
            // Find streams
            preg_match_all('/stream[\r\n]+(.*?)[\r\n]+endstream/s', $data, $matches);
            foreach ($matches[1] as $stream) {
                // Try to uncompress
                $uncompressed = @gzuncompress($stream);
                if ($uncompressed) {
                    $text .= $this->extractDetails($uncompressed);
                }
            }
        }
        
        // Extract from raw data as well (for uncompressed parts)
        $text .= $this->extractDetails($data);
        
        // Clean up: preserve newlines, collapse horizontal space
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/[\r\n]+/', "\n", $text);
        return trim($text);
    }

    private function extractDetails($content) {
        $text = '';
        // Look for string literals in parentheses: (Hello World)
        // Handle escaped parentheses \( and \)
        preg_match_all('/\((.|[\r\n])*?\)/', $content, $matches);
        
        foreach ($matches[0] as $match) {
            // Strip outer parens
            $str = substr($match, 1, -1);
            // Unescape
            $str = str_replace(['\(', '\)', '\\\\'], ['(', ')', '\\'], $str);
            $text .= $str . ' ';
        }
        
        return $text;
    }
}
