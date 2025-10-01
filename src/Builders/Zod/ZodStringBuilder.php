<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Builders\Zod;

use Override;

class ZodStringBuilder extends ZodBuilder
{
    protected ?string $requiredMessage = null;

    protected function getBaseType(): string
    {
        $content = 'z.string()';

        if (isset($this->requiredMessage)) {
            $content = "z.string({ error: '{$this->requiredMessage}' }).trim()";
            $content .= ".refine((val) => val != undefined && val != null && val != '', { error: '{$this->requiredMessage}' })";
        } else {
            $this->replaceRule('trim', '.trim()');
        }

        return $content;
    }

    /**
     * Make field required using Zod v4 error callback approach
     */
    #[Override]
    public function validateRequired(?array $parameters = [], ?string $message = null): self
    {
        $resolvedMessage = $this->resolveMessage('required', $message);

        $escapedMessage = $this->normalizeMessageForJS($resolvedMessage);
        $this->requiredMessage = $escapedMessage;

        $length = 1;
        $messageStr = $this->formatMessageAsParameter($resolvedMessage);

        $rule = ".min({$length}{$messageStr})";

        $this->replaceRule('min', $rule);

        return $this;
    }

    /**
     * Add regex validation
     */
    public function validateRegex(?array $parameters = [], ?string $message = null): self
    {
        [$pattern] = $parameters;
        $convertedPattern = $this->convertPhpRegexToJavaScript($pattern);

        $resolvedMessage = $this->resolveMessage('regex', $message);
        $messageStr = $this->formatMessageAsParameter($resolvedMessage);
        $rule = ".regex({$convertedPattern}{$messageStr})";

        $this->replaceRule('regex', $rule);

        return $this;
    }

    /**
     * Add UUID validation
     */
    public function validateUuid(?array $parameters = [], ?string $message = null): self
    {
        $resolvedMessage = $this->resolveMessage('uuid', $message);

        if ($resolvedMessage === null) {
            $rule = '.uuid()';
        } else {
            // For methods that only take a message parameter, we need to format it without leading comma
            $escapedMessage = $this->normalizeMessageForJS($resolvedMessage);
            $rule = ".uuid('{$escapedMessage}')";
        }

        $this->replaceRule('uuid', $rule);

        return $this;
    }

    /**
     * Add length validation (exact length)
     */
    public function validateLength(?array $parameters = [], ?string $message = null): self
    {
        [$length] = $parameters;
        $resolvedMessage = $this->resolveMessage('size', $message, ['size' => $length]);
        $messageStr = $this->formatMessageAsParameter($resolvedMessage);
        $rule = ".length({$length}{$messageStr})";

        $this->replaceRule('length', $rule);

        return $this;
    }

    /**
     * Convert PHP regex to JavaScript regex
     */
    public function convertPhpRegexToJavaScript(string $phpRegex): string
    {
        $pattern = $phpRegex;
        $flags = '';

        // Handle double-wrapped regex like //pattern/flags/
        // This can happen when a regex is mistakenly wrapped twice
        if (preg_match('/^\/\/(.*)\/(.*)\/$/', $phpRegex, $matches)) {
            $pattern = $matches[1];
            $flags = $matches[2];
        }
        // Check if it's a PHP regex with delimiters and possibly flags
        // Match: /pattern/flags where flags are optional
        elseif (preg_match('/^\/(.*)\/([a-zA-Z]*)$/', $phpRegex, $matches)) {
            $pattern = $matches[1];
            $flags = $matches[2];
        }

        // In JavaScript, dots don't need escaping inside character classes
        $pattern = preg_replace_callback(
            '/\[[^\]]*\]/',
            fn ($matches) => str_replace('\.', '.', $matches[0]),
            $pattern
        );

        // Return as a JavaScript regex literal with flags if present
        return '/'.$pattern.'/'.$flags;
    }
}
