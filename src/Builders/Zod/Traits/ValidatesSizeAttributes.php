<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Builders\Zod\Traits;

trait ValidatesSizeAttributes
{
    /**
     * Add minimum length validation
     */
    public function validateMin(?array $parameters = [], ?string $message = null): self
    {
        [$length] = $parameters;
        $resolvedMessage = $this->resolveMessage('min', $message, $parameters);
        $messageStr = $this->formatMessageAsParameter($resolvedMessage);
        $rule = ".min({$length}{$messageStr})";

        $this->replaceRule('min', $rule);

        return $this;
    }

    /**
     * Add less than validation
     */
    public function validateLt(?array $parameters = [], ?string $message = null): self
    {
        [$length] = $parameters;
        $resolvedMessage = $this->resolveMessage('lt', $message, $parameters);
        $this->validateMax([$length - 1], $resolvedMessage);

        return $this;
    }

    /**
     * Add less than or equal to validation
     */
    public function validateLte(?array $parameters = [], ?string $message = null): self
    {
        [$length] = $parameters;
        $resolvedMessage = $this->resolveMessage('lte', $message, $parameters);
        $this->validateMax([$length], $resolvedMessage);

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
     * Add greater than validation
     */
    public function validateGt(?array $parameters = [], ?string $message = null): self
    {
        [$length] = $parameters;
        $resolvedMessage = $this->resolveMessage('gt', $message, $parameters);
        $this->validateMin([$length + 1], $resolvedMessage);

        return $this;
    }

    /**
     * Add greater than or equal to validation
     */
    public function validateGte(?array $parameters = [], ?string $message = null): self
    {
        [$length] = $parameters;
        $resolvedMessage = $this->resolveMessage('gte', $message, $parameters);
        $this->validateMin([$length], $resolvedMessage);

        return $this;
    }

    /**
     * Make field required using Zod refine approach
     */
    public function validateRequired(?array $parameters = [], ?string $message = null): self
    {
        $resolvedMessage = $this->resolveMessage('required', $message);

        if ($resolvedMessage) {
            $escapedMessage = $this->normalizeMessageForJS($resolvedMessage);
            $this->requiredMessage = $escapedMessage;
        }

        return $this;
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
     * Add between validation
     */
    public function validateBetween(?array $parameters = [], ?string $message = null): self
    {
        [$min, $max] = $parameters;
        $resolvedMessage = $this->resolveMessage('between', $message, $parameters);

        $this->validateMin([$min], $resolvedMessage);
        $this->validateMax([$max], $resolvedMessage);

        return $this;
    }

    /**
     * Add exact size validation (in kilobytes)
     */
    public function validateSize(?array $parameters = [], ?string $message = null): self
    {
        [$length] = $parameters;
        $resolvedMessage = $this->resolveMessage('size', $message, $parameters);

        $messageStr = $this->formatMessageAsParameter($resolvedMessage);
        $rule = ".length({$length}{$messageStr})";

        $this->replaceRule('size', $rule);

        return $this;
    }
}
