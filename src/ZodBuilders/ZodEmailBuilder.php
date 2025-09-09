<?php

namespace RomegaSoftware\LaravelSchemaGenerator\ZodBuilders;

class ZodEmailBuilder extends ZodBuilder
{
    protected ?string $requiredMessage = null;

    protected ?string $emailErrorMessage = null;

    protected function getBaseType(): string
    {
        $content = 'z.email()';

        if (isset($this->requiredMessage) && ! isset($this->emailErrorMessage)) {
            $content = "z.email({ error: (val) => (val != undefined && val != null ? '{$this->requiredMessage}' : undefined) })";
        }

        if (isset($this->emailErrorMessage)) {
            $content = "z.email({ error: (val) => (val != undefined && val != null ? '{$this->emailErrorMessage}' : undefined) })";
        }

        $content .= '.trim()';

        return $content;
    }

    /**
     * Add trim validation
     */
    public function trim(): self
    {
        if (! $this->hasRule('trim')) {
            return $this->addRule('.trim()');
        }

        return $this;
    }

    /**
     * Add minimum length validation
     */
    public function min(int $length, ?string $message = null): self
    {
        $messageStr = $this->formatMessage($message);
        $rule = ".min({$length}{$messageStr})";

        return $this->replaceRule('min', $rule);
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

        return $this->replaceRule('min', $rule);
    }

    /**
     * Make field required using Zod v4 error callback approach
     */
    public function email(?string $message = null): self
    {
        $resolvedMessage = $this->resolveMessage('email', $message);

        if ($resolvedMessage) {
            $escapedMessage = $this->escapeForJS($resolvedMessage);
            $this->emailErrorMessage = $escapedMessage;
        }

        return $this;
    }

    /**
     * Add maximum length validation
     */
    public function max(int $length, ?string $message = null): self
    {
        $messageStr = $this->formatMessage($message);
        $rule = ".max({$length}{$messageStr})";

        return $this->replaceRule('max', $rule);
    }

    /**
     * Add custom email validation message
     */
    public function emailMessage(string $message): self
    {
        // For custom email message, we need to reconstruct with custom message
        // This is a special case for email validation
        $escapedMessage = str_replace("'", "\\'", $message);
        $rule = ".email('{$escapedMessage}')";

        return $this->replaceRule('email', $rule);
    }

    /**
     * Add non-empty validation (alias for min(1))
     */
    public function nonEmpty(?string $message = null): self
    {
        return $this->min(1, $message);
    }

    /**
     * Override build to handle email with custom message
     */
    public function build(): string
    {
        // Start with base email type
        $zodString = $this->getBaseType();

        // Add all validation rules
        foreach ($this->chain as $rule) {
            $zodString .= $rule;
        }

        // Add nullable if specified
        if ($this->nullable) {
            $zodString .= '.nullable()';
        }

        // Add optional if specified
        if ($this->optional) {
            $zodString .= '.optional()';
        }

        return $zodString;
    }
}
