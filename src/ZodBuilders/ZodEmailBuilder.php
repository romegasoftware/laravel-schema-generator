<?php

namespace RomegaSoftware\LaravelZodGenerator\ZodBuilders;

class ZodEmailBuilder extends ZodBuilder
{
    protected function getBaseType(): string
    {
        return 'z.email()';
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
        $zodString = 'z.email()';

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
