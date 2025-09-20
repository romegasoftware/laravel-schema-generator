<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Builders\Zod;

use Override;

class ZodEmailBuilder extends ZodBuilder
{
    protected ?string $requiredMessage = null;

    protected ?string $emailErrorMessage = null;

    protected function getBaseType(): string
    {
        $content = 'z.email()';

        if (isset($this->requiredMessage) && ! isset($this->emailErrorMessage)) {
            $content = "z.email({ error: '{$this->requiredMessage}' })";
        }

        if (isset($this->emailErrorMessage)) {
            $content = "z.email({ error: '{$this->emailErrorMessage}' })";
        }

        $content .= '.trim()';

        return $content;
    }

    /**
     * Make field required using Zod v4 error callback approach
     */
    #[Override]
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
