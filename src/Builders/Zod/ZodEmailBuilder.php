<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Builders\Zod;

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
    public function validateTrim(?array $parameters = [], ?string $message = null): self
    {
        if (! $this->hasRule('trim')) {
            $this->addRule('.trim()');
        }

        return $this;
    }

    /**
     * Add minimum length validation
     */
    public function validateMin(?array $parameters = [], ?string $message = null): self
    {
        [$length] = $parameters;
        $messageStr = $this->formatMessageAsParameter($message);
        $rule = ".min({$length}{$messageStr})";

        $this->replaceRule('min', $rule);

        return $this;
    }

    /**
     * Make field required using Zod v4 error callback approach
     */
    public function validateRequired(?array $parameters = [], ?string $message = null): self
    {
        $resolvedMessage = $this->resolveMessage('required', $message);

        if ($resolvedMessage) {
            $escapedMessage = $this->normalizeMessageForJS($resolvedMessage);
            $this->requiredMessage = $escapedMessage;
        }

        $length = 1;
        $messageStr = $this->formatMessageAsParameter($resolvedMessage);

        $rule = ".min({$length}{$messageStr})";

        $this->replaceRule('min', $rule);

        return $this;
    }

    /**
     * Make field required using Zod v4 error callback approach
     */
    public function validateEmail(?array $parameters = [], ?string $message = null): self
    {
        $resolvedMessage = $this->resolveMessage('email', $message);

        if ($resolvedMessage) {
            $escapedMessage = $this->normalizeMessageForJS($resolvedMessage);
            $this->emailErrorMessage = $escapedMessage;
        }

        return $this;
    }

    /**
     * Add maximum length validation
     */
    public function validateMax(?array $parameters = [], ?string $message = null): self
    {
        [$length] = $parameters;
        $messageStr = $this->formatMessageAsParameter($message);
        $rule = ".max({$length}{$messageStr})";

        $this->replaceRule('max', $rule);

        return $this;
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
