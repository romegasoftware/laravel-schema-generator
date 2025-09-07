<?php

namespace RomegaSoftware\LaravelZodGenerator\ZodBuilders;

class ZodArrayBuilder extends ZodBuilder
{
    protected string $itemType;

    public function __construct(string $itemType = 'z.any()')
    {
        $this->itemType = $itemType;
    }

    protected function getBaseType(): string
    {
        return "z.array({$this->itemType})";
    }

    /**
     * Set the array item type
     */
    public function of(string $itemType): self
    {
        $this->itemType = $itemType;

        return $this;
    }

    /**
     * Add minimum array length validation
     */
    public function min(int $length, ?string $message = null): self
    {
        $messageStr = $this->formatMessage($message);
        $rule = ".min({$length}{$messageStr})";

        return $this->replaceRule('min', $rule);
    }

    /**
     * Add maximum array length validation
     */
    public function max(int $length, ?string $message = null): self
    {
        $messageStr = $this->formatMessage($message);
        $rule = ".max({$length}{$messageStr})";

        return $this->replaceRule('max', $rule);
    }

    /**
     * Add exact array length validation
     */
    public function length(int $length, ?string $message = null): self
    {
        $messageStr = $this->formatMessage($message);
        $rule = ".length({$length}{$messageStr})";

        return $this->replaceRule('length', $rule);
    }

    /**
     * Add non-empty array validation (alias for min(1))
     */
    public function nonEmpty(?string $message = null): self
    {
        return $this->min(1, $message);
    }

    /**
     * Get the current item type
     */
    public function getItemType(): string
    {
        return $this->itemType;
    }
}
