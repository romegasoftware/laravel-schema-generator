<?php

namespace RomegaSoftware\LaravelZodGenerator\ZodBuilders;

class ZodStringBuilder extends ZodBuilder
{
    protected function getBaseType(): string
    {
        return 'z.string()';
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
     * Add regex validation
     */
    public function regex(string $pattern, ?string $message = null): self
    {
        $messageStr = $this->formatMessage($message);
        $rule = ".regex({$pattern}{$messageStr})";

        return $this->replaceRule('regex', $rule);
    }

    /**
     * Add URL validation
     */
    public function url(?string $message = null): self
    {
        $messageStr = $this->formatMessage($message);
        $rule = ".url({$messageStr})";
        // Remove leading comma if no message
        if ($message === null) {
            $rule = '.url()';
        }

        return $this->replaceRule('url', $rule);
    }

    /**
     * Add UUID validation
     */
    public function uuid(?string $message = null): self
    {
        $messageStr = $this->formatMessage($message);
        $rule = ".uuid({$messageStr})";
        // Remove leading comma if no message
        if ($message === null) {
            $rule = '.uuid()';
        }

        return $this->replaceRule('uuid', $rule);
    }

    /**
     * Add length validation (exact length)
     */
    public function length(int $length, ?string $message = null): self
    {
        $messageStr = $this->formatMessage($message);
        $rule = ".length({$length}{$messageStr})";

        return $this->replaceRule('length', $rule);
    }

    /**
     * Add non-empty validation (alias for min(1))
     */
    public function nonEmpty(?string $message = null): self
    {
        return $this->min(1, $message);
    }
}
