<?php

/*
Copyright EXOR Group ltd 2025 
Version 1.0.0.0 
APEX Laravel Auditing
Description: Service for handling multi-language support in APEX Audit. Provides language detection, translation loading, and text formatting with parameter substitution for internationalization.
*/

namespace Apex\Audit\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

class ApexAuditLanguageService
{
    protected ?string $currentLanguage = null;
    protected array $loadedTranslations = [];
    protected array $supportedLanguages = [];

    public function __construct()
    {
        $this->supportedLanguages = config('apex.audit.language.supported', ['en' => 'English']);
        $this->detectLanguage();
    }

    /**
     * Detect the current language based on configuration.
     */
    protected function detectLanguage(): void
    {
        $method = config('apex.audit.language.detection_method', 'app');
        $default = config('apex.audit.language.default', 'en');

        switch ($method) {
            case 'url':
                $this->currentLanguage = $this->detectFromUrl() ?? $default;
                break;
            case 'config':
                $this->currentLanguage = $default;
                break;
            case 'header':
                $this->currentLanguage = $this->detectFromHeader() ?? $default;
                break;
            case 'app':
            default:
                $this->currentLanguage = app()->getLocale() ?? $default;
                break;
        }

        // Validate language is supported
        if (!isset($this->supportedLanguages[$this->currentLanguage])) {
            $this->currentLanguage = $default;
        }

        // Cache in session if enabled
        if (config('apex.audit.language.url_detection.cache_in_session', true)) {
            session(['apex_audit_language' => $this->currentLanguage]);
        }
    }

    /**
     * Detect language from URL.
     */
    protected function detectFromUrl(): ?string
    {
        if (!request()) {
            return null;
        }

        $position = config('apex.audit.language.url_detection.segment_position', 1);
        $segment = request()->segment($position);

        if ($segment && isset($this->supportedLanguages[$segment])) {
            return $segment;
        }

        // Check session cache
        if (config('apex.audit.language.url_detection.cache_in_session', true)) {
            return session('apex_audit_language');
        }

        return null;
    }

    /**
     * Detect language from Accept-Language header.
     */
    protected function detectFromHeader(): ?string
    {
        if (!request()) {
            return null;
        }

        $acceptLanguage = request()->header('Accept-Language');
        if (!$acceptLanguage) {
            return null;
        }

        // Parse Accept-Language header
        $languages = [];
        foreach (explode(',', $acceptLanguage) as $lang) {
            $parts = explode(';', $lang);
            $code = trim($parts[0]);
            $quality = 1.0;

            if (isset($parts[1]) && strpos($parts[1], 'q=') === 0) {
                $quality = (float) substr($parts[1], 2);
            }

            // Extract language code (remove country code)
            $langCode = substr($code, 0, 2);
            $languages[$langCode] = $quality;
        }

        // Sort by quality
        arsort($languages);

        // Find first supported language
        foreach (array_keys($languages) as $langCode) {
            if (isset($this->supportedLanguages[$langCode])) {
                return $langCode;
            }
        }

        return null;
    }

    /**
     * Get the current language.
     */
    public function getCurrentLanguage(): string
    {
        return $this->currentLanguage ?? config('apex.audit.language.default', 'en');
    }

    /**
     * Set the current language.
     */
    public function setLanguage(string $language): void
    {
        if (isset($this->supportedLanguages[$language])) {
            $this->currentLanguage = $language;
            session(['apex_audit_language' => $language]);
        }
    }

    /**
     * Translate a key with optional parameters.
     */
    public function trans(string $key, array $parameters = [], ?string $language = null): string
    {
        $language = $language ?? $this->getCurrentLanguage();
        $translations = $this->loadTranslations($language);

        // Get translation using dot notation
        $translation = $this->getNestedValue($translations, $key);

        if ($translation === null) {
            // Fallback to English
            if ($language !== 'en') {
                $fallbackTranslations = $this->loadTranslations('en');
                $translation = $this->getNestedValue($fallbackTranslations, $key);
            }

            // Return key if no translation found
            if ($translation === null) {
                return $key;
            }
        }

        // Replace parameters
        return $this->replaceParameters($translation, $parameters);
    }

    /**
     * Load translations for a language.
     */
    protected function loadTranslations(string $language): array
    {
        if (isset($this->loadedTranslations[$language])) {
            return $this->loadedTranslations[$language];
        }

        $cacheKey = "apex_audit_lang_{$language}";
        $cacheEnabled = config('apex.audit.language.cache.enabled', true);
        $cacheTtl = config('apex.audit.language.cache.ttl', 3600);

        if ($cacheEnabled && Cache::has($cacheKey)) {
            $this->loadedTranslations[$language] = Cache::get($cacheKey);
            return $this->loadedTranslations[$language];
        }

        $translations = $this->loadLanguageFiles($language);
        $this->loadedTranslations[$language] = $translations;

        if ($cacheEnabled) {
            Cache::put($cacheKey, $translations, $cacheTtl);
        }

        return $translations;
    }

    /**
     * Load language files for a specific language.
     */
    protected function loadLanguageFiles(string $language): array
    {
        $langPath = __DIR__ . "/../Lang/{$language}";
        $translations = [];

        if (!File::isDirectory($langPath)) {
            return [];
        }

        $files = File::files($langPath);
        foreach ($files as $file) {
            if ($file->getExtension() === 'php') {
                $filename = $file->getFilenameWithoutExtension();
                $content = require $file->getPathname();
                if (is_array($content)) {
                    $translations[$filename] = $content;
                }
            }
        }

        return $translations;
    }

    /**
     * Get nested value using dot notation.
     */
    protected function getNestedValue(array $array, string $key): ?string
    {
        $keys = explode('.', $key);
        $value = $array;

        foreach ($keys as $segment) {
            if (!is_array($value) || !isset($value[$segment])) {
                return null;
            }
            $value = $value[$segment];
        }

        return is_string($value) ? $value : null;
    }

    /**
     * Replace parameters in translation string.
     */
    protected function replaceParameters(string $translation, array $parameters): string
    {
        foreach ($parameters as $key => $value) {
            $translation = str_replace(":{$key}", $value, $translation);
            $translation = str_replace("{{$key}}", $value, $translation);
        }

        return $translation;
    }

    /**
     * Get all supported languages.
     */
    public function getSupportedLanguages(): array
    {
        return $this->supportedLanguages;
    }

    /**
     * Check if a language is supported.
     */
    public function isLanguageSupported(string $language): bool
    {
        return isset($this->supportedLanguages[$language]);
    }

    /**
     * Clear language cache.
     */
    public function clearCache(?string $language = null): void
    {
        if ($language) {
            Cache::forget("apex_audit_lang_{$language}");
            unset($this->loadedTranslations[$language]);
        } else {
            foreach (array_keys($this->supportedLanguages) as $lang) {
                Cache::forget("apex_audit_lang_{$lang}");
            }
            $this->loadedTranslations = [];
        }
    }

    /**
     * Get language direction (LTR or RTL).
     */
    public function getLanguageDirection(?string $language = null): string
    {
        $language = $language ?? $this->getCurrentLanguage();
        $rtlLanguages = ['ar', 'he', 'fa', 'ur'];

        return in_array($language, $rtlLanguages) ? 'rtl' : 'ltr';
    }

    /**
     * Format a date according to language locale.
     */
    public function formatDate(\DateTime $date, string $format = 'medium', ?string $language = null): string
    {
        $language = $language ?? $this->getCurrentLanguage();

        $formats = [
            'short' => 'M j, Y',
            'medium' => 'M j, Y g:i A',
            'long' => 'F j, Y g:i:s A',
            'full' => 'l, F j, Y g:i:s A T',
        ];

        $dateFormat = $formats[$format] ?? $format;
        return $date->format($dateFormat);
    }

    /**
     * Get language-specific number formatting.
     */
    public function formatNumber(float $number, int $decimals = 0, ?string $language = null): string
    {
        $language = $language ?? $this->getCurrentLanguage();

        $separators = [
            'en' => ['.', ','],
            'es' => [',', '.'],
            'fr' => [',', ' '],
            'de' => [',', '.'],
            'it' => [',', '.'],
        ];

        $sep = $separators[$language] ?? $separators['en'];
        return number_format($number, $decimals, $sep[0], $sep[1]);
    }
}
