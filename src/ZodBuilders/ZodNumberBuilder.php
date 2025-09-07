<?php

namespace RomegaSoftware\LaravelZodGenerator\ZodBuilders;

class ZodNumberBuilder extends ZodBuilder
{
    protected bool $isInteger = false;

    protected function getBaseType(): string
    {
        return 'z.number()';
    }

    /**
     * Add minimum value validation
     */
    public function min(int|float $value, ?string $message = null): self
    {
        $messageStr = $this->formatMessage($message);
        $rule = ".min({$value}{$messageStr})";

        return $this->replaceRule('min', $rule);
    }

    /**
     * Add maximum value validation
     */
    public function max(int|float $value, ?string $message = null): self
    {
        $messageStr = $this->formatMessage($message);
        $rule = ".max({$value}{$messageStr})";

        return $this->replaceRule('max', $rule);
    }

    /**
     * Add integer validation
     */
    public function int(?string $message = null): self
    {
        $this->isInteger = true;
        $messageStr = $this->formatMessage($message);
        $rule = ".int({$messageStr})";
        // Remove leading comma if no message
        if ($message === null) {
            $rule = '.int()';
        }

        return $this->replaceRule('int', $rule);
    }

    /**
     * Add positive number validation (> 0)
     */
    public function positive(?string $message = null): self
    {
        $messageStr = $this->formatMessage($message);
        $rule = ".positive({$messageStr})";
        // Remove leading comma if no message
        if ($message === null) {
            $rule = '.positive()';
        }

        return $this->replaceRule('positive', $rule);
    }

    /**
     * Add non-negative number validation (>= 0)
     */
    public function nonNegative(?string $message = null): self
    {
        $messageStr = $this->formatMessage($message);
        $rule = ".nonnegative({$messageStr})";
        // Remove leading comma if no message
        if ($message === null) {
            $rule = '.nonnegative()';
        }

        return $this->replaceRule('nonnegative', $rule);
    }

    /**
     * Add negative number validation (< 0)
     */
    public function negative(?string $message = null): self
    {
        $messageStr = $this->formatMessage($message);
        $rule = ".negative({$messageStr})";
        // Remove leading comma if no message
        if ($message === null) {
            $rule = '.negative()';
        }

        return $this->replaceRule('negative', $rule);
    }

    /**
     * Add non-positive number validation (<= 0)
     */
    public function nonPositive(?string $message = null): self
    {
        $messageStr = $this->formatMessage($message);
        $rule = ".nonpositive({$messageStr})";
        // Remove leading comma if no message
        if ($message === null) {
            $rule = '.nonpositive()';
        }

        return $this->replaceRule('nonpositive', $rule);
    }

    /**
     * Add finite number validation (not infinite or NaN)
     */
    public function finite(?string $message = null): self
    {
        $messageStr = $this->formatMessage($message);
        $rule = ".finite({$messageStr})";
        // Remove leading comma if no message
        if ($message === null) {
            $rule = '.finite()';
        }

        return $this->replaceRule('finite', $rule);
    }

    /**
     * Check if this is marked as an integer type
     */
    public function isInteger(): bool
    {
        return $this->isInteger;
    }
}
