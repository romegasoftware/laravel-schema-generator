<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Builders\Zod;

use Override;

class ZodStringBuilder extends ZodBuilder
{
    protected ?string $requiredMessage = null;

    protected ?string $baseOverride = null;

    protected function setBaseOverride(string $expression): self
    {
        $this->baseOverride = $expression;

        return $this;
    }

    protected function getBaseType(): string
    {
        if ($this->baseOverride !== null) {
            return $this->baseOverride;
        }

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

        if ($this->baseOverride !== null) {
            $messageText = $resolvedMessage ?? 'This field is required.';
            $escapedMessage = $this->normalizeMessageForJS($messageText);

            $this->removeRuleContaining('__required_refine__');

            $rule = '.refine((val) => {'
                .' if (val === undefined || val === null) { return false; }'
                .' if (typeof val === "string") { return val.trim() !== ""; }'
                .' return true;'
                ." }, { message: '{$escapedMessage}' }) /*__required_refine__*/";

            $this->addRule($rule);

            return $this;
        }

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

    public function validateAlpha(?array $parameters = [], ?string $message = null): self
    {
        return $this->applyRegexRule('__alpha__', '/^[A-Za-z]+$/', $this->resolveMessage('alpha', $message));
    }

    public function validateAlphaDash(?array $parameters = [], ?string $message = null): self
    {
        return $this->applyRegexRule('__alpha_dash__', '/^[A-Za-z0-9_-]+$/', $this->resolveMessage('alpha_dash', $message));
    }

    public function validateAlphaNum(?array $parameters = [], ?string $message = null): self
    {
        return $this->applyRegexRule('__alpha_num__', '/^[A-Za-z0-9]+$/', $this->resolveMessage('alpha_num', $message));
    }

    public function validateAlphaSpaces(?array $parameters = [], ?string $message = null): self
    {
        return $this->applyRegexRule('__alpha_spaces__', '/^[A-Za-z\s]+$/', $this->resolveMessage('alpha_spaces', $message));
    }

    public function validateAscii(?array $parameters = [], ?string $message = null): self
    {
        return $this->applyRegexRule('__ascii__', '/^[\\x00-\\x7F]+$/', $this->resolveMessage('ascii', $message));
    }

    public function validateLowercase(?array $parameters = [], ?string $message = null): self
    {
        $resolvedMessage = $this->resolveMessage('lowercase', $message);
        $messageText = $resolvedMessage ?? 'The value must be lowercase.';
        $escapedMessage = $this->normalizeMessageForJS($messageText);

        $rule = ".lowercase('{$escapedMessage}')";

        return $this->replaceRule('lowercase', $rule);
    }

    public function validateUppercase(?array $parameters = [], ?string $message = null): self
    {
        $resolvedMessage = $this->resolveMessage('uppercase', $message);
        $messageText = $resolvedMessage ?? 'The value must be uppercase.';
        $escapedMessage = $this->normalizeMessageForJS($messageText);

        $rule = ".uppercase('{$escapedMessage}')";

        return $this->replaceRule('uppercase', $rule);
    }

    public function validateStartsWith(?array $parameters = [], ?string $message = null): self
    {
        if (empty($parameters)) {
            return $this;
        }

        $resolvedMessage = $this->resolveMessage('starts_with', $message);

        if (count($parameters) === 1) {
            $prefix = $this->normalizeMessageForJS((string) $parameters[0]);
            $messageStr = $this->formatMessageAsParameter($resolvedMessage);
            $rule = ".startsWith('{$prefix}'{$messageStr})";

            return $this->replaceRule('startsWith', $rule);
        }

        $messageText = $resolvedMessage ?? 'The value has an invalid prefix.';
        $escapedMessage = $this->normalizeMessageForJS($messageText);

        $prefixes = array_map(fn ($prefix) => "'".$this->normalizeMessageForJS((string) $prefix)."'", $parameters);
        $prefixArray = '['.implode(', ', $prefixes).']';

        $rule = '.refine((val) => {'
            .' if (val === undefined || val === null) { return true; }'
            .' if (typeof val !== "string") { return false; }'
            ." return {$prefixArray}.some((prefix) => val.startsWith(prefix));"
            ." }, { message: '{$escapedMessage}' })";

        return $this->replaceRule('startsWith', $rule);
    }

    public function validateEndsWith(?array $parameters = [], ?string $message = null): self
    {
        if (empty($parameters)) {
            return $this;
        }

        $resolvedMessage = $this->resolveMessage('ends_with', $message);

        if (count($parameters) === 1) {
            $suffix = $this->normalizeMessageForJS((string) $parameters[0]);
            $messageStr = $this->formatMessageAsParameter($resolvedMessage);
            $rule = ".endsWith('{$suffix}'{$messageStr})";

            return $this->replaceRule('endsWith', $rule);
        }

        $messageText = $resolvedMessage ?? 'The value has an invalid ending.';
        $escapedMessage = $this->normalizeMessageForJS($messageText);

        $suffixes = array_map(fn ($suffix) => "'".$this->normalizeMessageForJS((string) $suffix)."'", $parameters);
        $suffixArray = '['.implode(', ', $suffixes).']';

        $rule = '.refine((val) => {'
            .' if (val === undefined || val === null) { return true; }'
            .' if (typeof val !== "string") { return false; }'
            ." return {$suffixArray}.some((suffix) => val.endsWith(suffix));"
            ." }, { message: '{$escapedMessage}' })";

        return $this->replaceRule('endsWith', $rule);
    }

    public function validateDoesntStartWith(?array $parameters = [], ?string $message = null): self
    {
        if (empty($parameters)) {
            return $this;
        }

        $resolvedMessage = $this->resolveMessage('doesnt_start_with', $message);
        $messageText = $resolvedMessage ?? 'The value has a forbidden prefix.';
        $escapedMessage = $this->normalizeMessageForJS($messageText);

        $prefixes = array_map(fn ($prefix) => "'".$this->normalizeMessageForJS((string) $prefix)."'", $parameters);
        $prefixArray = '['.implode(', ', $prefixes).']';

        $rule = '.refine((val) => {'
            .' if (val === undefined || val === null) { return true; }'
            .' if (typeof val !== "string") { return true; }'
            ." return !{$prefixArray}.some((prefix) => val.startsWith(prefix));"
            ." }, { message: '{$escapedMessage}' })";

        return $this->addRule($rule);
    }

    public function validateDoesntEndWith(?array $parameters = [], ?string $message = null): self
    {
        if (empty($parameters)) {
            return $this;
        }

        $resolvedMessage = $this->resolveMessage('doesnt_end_with', $message);
        $messageText = $resolvedMessage ?? 'The value has a forbidden ending.';
        $escapedMessage = $this->normalizeMessageForJS($messageText);

        $suffixes = array_map(fn ($suffix) => "'".$this->normalizeMessageForJS((string) $suffix)."'", $parameters);
        $suffixArray = '['.implode(', ', $suffixes).']';

        $rule = '.refine((val) => {'
            .' if (val === undefined || val === null) { return true; }'
            .' if (typeof val !== "string") { return true; }'
            ." return !{$suffixArray}.some((suffix) => val.endsWith(suffix));"
            ." }, { message: '{$escapedMessage}' })";

        return $this->addRule($rule);
    }

    public function validateJson(?array $parameters = [], ?string $message = null): self
    {
        $resolvedMessage = $this->resolveMessage('json', $message);
        $messageText = $resolvedMessage ?? 'The value must be valid JSON.';
        $escapedMessage = $this->normalizeMessageForJS($messageText);

        $rule = '.refine((val) => {'
            .' if (val === undefined || val === null) { return true; }'
            .' if (typeof val !== "string") { return false; }'
            .' try { JSON.parse(val); return true; } catch (error) { return false; }'
            ." }, { message: '{$escapedMessage}' })";

        return $this->addRule($rule);
    }

    public function validateIp(?array $parameters = [], ?string $message = null): self
    {
        $resolvedMessage = $this->resolveMessage('ip', $message);

        if ($resolvedMessage === null) {
            return $this->setBaseOverride('z.union([z.ipv4(), z.ipv6()])');
        }

        $escapedMessage = $this->normalizeMessageForJS($resolvedMessage);

        return $this->setBaseOverride("z.union([z.ipv4({ message: '{$escapedMessage}' }), z.ipv6({ message: '{$escapedMessage}' })])");
    }

    public function validateIpv4(?array $parameters = [], ?string $message = null): self
    {
        $resolvedMessage = $this->resolveMessage('ipv4', $message);
        if ($resolvedMessage === null) {
            return $this->setBaseOverride('z.ipv4()');
        }

        $escapedMessage = $this->normalizeMessageForJS($resolvedMessage);

        return $this->setBaseOverride("z.ipv4({ message: '{$escapedMessage}' })");
    }

    public function validateIpv6(?array $parameters = [], ?string $message = null): self
    {
        $resolvedMessage = $this->resolveMessage('ipv6', $message);
        if ($resolvedMessage === null) {
            return $this->setBaseOverride('z.ipv6()');
        }

        $escapedMessage = $this->normalizeMessageForJS($resolvedMessage);

        return $this->setBaseOverride("z.ipv6({ message: '{$escapedMessage}' })");
    }

    public function validateMacAddress(?array $parameters = [], ?string $message = null): self
    {
        $resolvedMessage = $this->resolveMessage('mac_address', $message);
        $messageText = $resolvedMessage ?? 'The value must be a valid MAC address.';
        $escapedMessage = $this->normalizeMessageForJS($messageText);

        $pattern = '/^(?:[0-9A-Fa-f]{2}[:-]){5}[0-9A-Fa-f]{2}$|^[0-9A-Fa-f]{4}\\.[0-9A-Fa-f]{4}\\.[0-9A-Fa-f]{4}$/';

        $this->removeRuleContaining('__mac_refine__');

        return $this->addRule(
            ".refine((val) => { if (val === undefined || val === null) { return true; } if (typeof val !== \"string\") { return false; } return {$pattern}.test(val); }, { message: '{$escapedMessage}' }) /*__mac_refine__*/"
        );
    }

    public function validateUlid(?array $parameters = [], ?string $message = null): self
    {
        $resolvedMessage = $this->resolveMessage('ulid', $message);
        if ($resolvedMessage === null) {
            return $this->setBaseOverride('z.ulid()');
        }

        $escapedMessage = $this->normalizeMessageForJS($resolvedMessage);

        return $this->setBaseOverride("z.ulid({ message: '{$escapedMessage}' })");
    }

    public function validateDate(?array $parameters = [], ?string $message = null): self
    {
        $resolvedMessage = $this->resolveMessage('date', $message);
        $messageText = $resolvedMessage ?? 'The value must be a valid date.';
        $escapedMessage = $this->normalizeMessageForJS($messageText);

        $rule = '.refine((val) => {'
            .' if (val === undefined || val === null) { return true; }'
            .' if (typeof val !== "string") { return false; }'
            .' const timestamp = Date.parse(val);'
            .' return !Number.isNaN(timestamp);'
            ." }, { message: '{$escapedMessage}' })";

        return $this->addRule($rule);
    }

    protected function applyRegexRule(string $marker, string $pattern, ?string $message): self
    {
        $this->removeRuleContaining($marker);
        $messageStr = $this->formatMessageAsParameter($message);
        $rule = ".regex({$pattern}{$messageStr}) /*{$marker}*/";

        return $this->addRule($rule);
    }

    protected function removeRuleContaining(string $needle): void
    {
        $this->chain = array_values(array_filter($this->chain, static fn ($rule) => ! str_contains($rule, $needle)));
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

        // In JavaScript, dots and non-range hyphens don't need escaping inside character classes
        $pattern = preg_replace_callback(
            '/\[[^\]]*\]/',
            fn ($matches) => $this->normalizeCharacterClass($matches[0]),
            $pattern
        );

        // Return as a JavaScript regex literal with flags if present
        return '/'.$pattern.'/'.$flags;
    }

    protected function normalizeCharacterClass(string $characterClass): string
    {
        $length = strlen($characterClass);

        if ($length < 2 || $characterClass[0] !== '[' || $characterClass[$length - 1] !== ']') {
            return $characterClass;
        }

        $body = substr($characterClass, 1, -1);
        $isNegated = str_starts_with($body, '^');

        if ($isNegated) {
            $body = substr($body, 1);
            $body = $body === false ? '' : $body;
        }

        $body = $this->normalizeCharacterClassBody($body);

        return '['.($isNegated ? '^' : '').$body.']';
    }

    /**
     * Remove unnecessary escapes within a character class.
     */
    private function normalizeCharacterClassBody(string $body): string
    {
        $result = '';
        $length = strlen($body);
        $hasLiteralBefore = false;

        for ($i = 0; $i < $length; $i++) {
            $char = $body[$i];

            if ($char === '\\') {
                $nextChar = $body[$i + 1] ?? null;

                if ($nextChar === null) {
                    $result .= '\\';
                    break;
                }

                if ($nextChar === '.') {
                    $result .= '.';
                    $hasLiteralBefore = true;
                    $i++;
                    continue;
                }

                if ($nextChar === '-') {
                    $hasLiteralAfter = $this->characterClassBodyHasLiteralAfter($body, $i + 2);

                    if ($hasLiteralBefore && $hasLiteralAfter) {
                        $result .= '\-';
                    } else {
                        $result .= '-';
                    }

                    $hasLiteralBefore = true;
                    $i++;
                    continue;
                }

                $result .= '\\'.$nextChar;
                $hasLiteralBefore = true;
                $i++;
                continue;
            }

            $result .= $char;
            $hasLiteralBefore = true;
        }

        return $result;
    }

    private function characterClassBodyHasLiteralAfter(string $body, int $startIndex): bool
    {
        $length = strlen($body);

        for ($i = $startIndex; $i < $length; $i++) {
            if ($body[$i] === '\\') {
                return isset($body[$i + 1]);
            }

            return true;
        }

        return false;
    }
}
