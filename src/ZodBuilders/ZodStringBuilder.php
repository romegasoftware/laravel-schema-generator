<?php

namespace RomegaSoftware\LaravelSchemaGenerator\ZodBuilders;

class ZodStringBuilder extends ZodBuilder
{
    protected ?string $requiredMessage = null;

    protected function getBaseType(): string
    {
        $content = 'z.string().trim()';

        if (isset($this->requiredMessage)) {
            $content .= ".refine((val) => val != undefined && val != null && val != '', { error: '{$this->requiredMessage}'})";
        }

        return $content;
    }

    /**
     * Add trim validation
     */
    public function trim(): self
    {
        if (! $this->hasRule('trim')) {
            $this->addRule('.trim()');
        }

        return $this;
    }

    /**
     * Add minimum length validation
     */
    public function min(int $length, ?string $message = null): self
    {
        $resolvedMessage = $this->resolveMessage('min', $message, ['min' => $length]);
        $messageStr = $this->formatMessage($resolvedMessage);
        $rule = ".min({$length}{$messageStr})";

        $this->replaceRule('min', $rule);

        return $this;
    }

    /**
     * Make field required using Zod v4 error callback approach
     */
    public function required(?string $message = null): self
    {
        $resolvedMessage = $this->resolveMessage('required', $message);

        if ($resolvedMessage) {
            $escapedMessage = $this->escapeForJS($resolvedMessage);
            $this->requiredMessage = $escapedMessage;
        }

        $length = 1;
        $messageStr = $this->formatMessage($resolvedMessage);

        $rule = ".min({$length}{$messageStr})";

        $this->replaceRule('min', $rule);

        return $this;
    }

    /**
     * Add maximum length validation
     */
    public function max(int $length, ?string $message = null): self
    {
        $resolvedMessage = $this->resolveMessage('max', $message, ['max' => $length]);
        $messageStr = $this->formatMessage($resolvedMessage);
        $rule = ".max({$length}{$messageStr})";

        $this->replaceRule('max', $rule);

        return $this;
    }

    /**
     * Add regex validation
     */
    public function regex(string $pattern, ?string $message = null): self
    {
        $resolvedMessage = $this->resolveMessage('regex', $message);
        $messageStr = $this->formatMessage($resolvedMessage);
        $rule = ".regex({$pattern}{$messageStr})";

        $this->replaceRule('regex', $rule);

        return $this;
    }

    /**
     * Add URL validation
     */
    public function url(?string $message = null): self
    {
        $resolvedMessage = $this->resolveMessage('url', $message);

        if ($resolvedMessage === null) {
            $rule = '.url()';
        } else {
            // For methods that only take a message parameter, we need to format it without leading comma
            $escapedMessage = str_replace("'", "\\'", $resolvedMessage);
            $rule = ".url('{$escapedMessage}')";
        }

        $this->replaceRule('url', $rule);

        return $this;
    }

    /**
     * Add UUID validation
     */
    public function uuid(?string $message = null): self
    {
        $resolvedMessage = $this->resolveMessage('uuid', $message);

        if ($resolvedMessage === null) {
            $rule = '.uuid()';
        } else {
            // For methods that only take a message parameter, we need to format it without leading comma
            $escapedMessage = str_replace("'", "\\'", $resolvedMessage);
            $rule = ".uuid('{$escapedMessage}')";
        }

        $this->replaceRule('uuid', $rule);

        return $this;
    }

    /**
     * Add length validation (exact length)
     */
    public function length(int $length, ?string $message = null): self
    {
        $resolvedMessage = $this->resolveMessage('size', $message, ['size' => $length]);
        $messageStr = $this->formatMessage($resolvedMessage);
        $rule = ".length({$length}{$messageStr})";

        $this->replaceRule('length', $rule);

        return $this;
    }

    /**
     * Add non-empty validation (alias for min(1))
     */
    public function nonEmpty(?string $message = null): self
    {
        $this->min(1, $message);

        return $this;
    }
}
