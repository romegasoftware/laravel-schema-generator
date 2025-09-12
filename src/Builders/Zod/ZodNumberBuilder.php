<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Builders\Zod;

class ZodNumberBuilder extends ZodBuilder
{
    protected bool $isInteger = false;

    protected ?string $requiredMessage = null;

    protected ?string $integerMessage = null;

    protected function getBaseType(): string
    {
        $content = 'z.number()';

        if (isset($this->integerMessage)) {
            $content = "z.number({error: (val) => (val != undefined && val != null ? '{$this->integerMessage}' : undefined)})";
        }

        if (isset($this->requiredMessage) && ! isset($this->integerMessage)) {
            $content .= ".refine((val) => val != undefined && val != null, { error: '{$this->requiredMessage}'})";
        }

        return $content;
    }

    /**
     * Make field required using Zod refine approach
     */
    public function validateInteger(?array $parameters = [], ?string $message = null): self
    {
        $this->isInteger = true;
        $resolvedMessage = $this->resolveMessage('integer', $message);

        if ($resolvedMessage) {
            $escapedMessage = $this->normalizeMessageForJS($resolvedMessage);
            $this->integerMessage = $escapedMessage;
        }

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
     * Add minimum value validation
     */
    public function validateMin(?array $parameters = [], ?string $message = null): self
    {
        [$value] = $parameters;
        $messageStr = $this->formatMessageAsParameter($message);
        $rule = ".min({$value}{$messageStr})";

        $this->replaceRule('min', $rule);

        return $this;
    }

    /**
     * Add maximum value validation
     */
    public function validateMax(?array $parameters = [], ?string $message = null): self
    {
        [$value] = $parameters;
        $messageStr = $this->formatMessageAsParameter($message);
        $rule = ".max({$value}{$messageStr})";

        $this->replaceRule('max', $rule);

        return $this;
    }

    /**
     * Add greater than validation
     */
    public function validateGt(?array $parameters = [], ?string $message = null): self
    {
        [$value] = $parameters;
        $messageStr = $this->formatMessageAsParameter($message);
        $rule = ".gt({$value}{$messageStr})";

        $this->replaceRule('gt', $rule);

        return $this;
    }

    /**
     * Add greater than or equal validation
     */
    public function validateGte(?array $parameters = [], ?string $message = null): self
    {
        [$value] = $parameters;
        $messageStr = $this->formatMessageAsParameter($message);
        $rule = ".gte({$value}{$messageStr})";

        $this->replaceRule('gte', $rule);

        return $this;
    }

    /**
     * Add less than validation
     */
    public function validateLt(?array $parameters = [], ?string $message = null): self
    {
        [$value] = $parameters;
        $messageStr = $this->formatMessageAsParameter($message);
        $rule = ".lt({$value}{$messageStr})";

        $this->replaceRule('lt', $rule);

        return $this;
    }

    /**
     * Add less than or equal validation
     */
    public function validateLte(?array $parameters = [], ?string $message = null): self
    {
        [$value] = $parameters;
        $messageStr = $this->formatMessageAsParameter($message);
        $rule = ".lte({$value}{$messageStr})";

        $this->replaceRule('lte', $rule);

        return $this;
    }

    /**
     * Add multiple of validation
     */
    public function validateMultipleOf(?array $parameters = [], ?string $message = null): self
    {
        [$value] = $parameters;
        $messageStr = $this->formatMessageAsParameter($message);
        $rule = ".multipleOf({$value}{$messageStr})";

        $this->replaceRule('multipleOf', $rule);

        return $this;
    }

    /**
     * Add decimal places validation
     * Laravel decimal:min,max validation
     */
    public function validateDecimal(?array $parameters = [], ?string $message = null): self
    {
        $min = $parameters[0];
        $max = $parameters[1] ?? null;
        $resolvedMessage = $this->resolveMessage('decimal', $message);
        $escapedMessage = $this->normalizeMessageForJS($resolvedMessage);

        if ($max === null) {
            // Exact decimal places
            $rule = '.refine((val) => {'.
                'const str = String(val); '.
                "const parts = str.split('.'); ".
                "return parts.length === 1 || (parts.length === 2 && parts[1].length === {$min}); ".
                "}, { message: '{$escapedMessage}' })";
        } else {
            // Range of decimal places
            $rule = '.refine((val) => {'.
                'const str = String(val); '.
                "const parts = str.split('.'); ".
                'if (parts.length === 1) return true; '.
                'const decimals = parts[1].length; '.
                "return decimals >= {$min} && decimals <= {$max}; ".
                "}, { message: '{$escapedMessage}' })";
        }

        $this->addRule($rule);

        return $this;
    }

    /**
     * Add exact digits validation
     * Laravel digits:value validation
     */
    public function validateDigits(?array $parameters = [], ?string $message = null): self
    {
        [$value] = $parameters;
        $resolvedMessage = $this->resolveMessage('digits', $message);
        $escapedMessage = $this->normalizeMessageForJS($resolvedMessage);

        $rule = '.refine((val) => {'.
            'const str = String(Math.abs(Math.floor(val))); '.
            "return str.length === {$value}; ".
            "}, { message: '{$escapedMessage}' })";

        $this->addRule($rule);

        return $this;
    }

    /**
     * Add digits between validation
     * Laravel digits_between:min,max validation
     */
    public function validateDigitsBetween(?array $parameters = [], ?string $message = null): self
    {
        [$min, $max] = $parameters;
        $resolvedMessage = $this->resolveMessage('digits_between', $message);

        $this->validateMinDigits([$min], $resolvedMessage);
        $this->validateMaxDigits([$max], $resolvedMessage);

        return $this;
    }

    /**
     * Add max digits validation
     * Laravel max_digits:value validation
     */
    public function validateMaxDigits(?array $parameters = [], ?string $message = null): self
    {
        [$value] = $parameters;
        $resolvedMessage = $this->resolveMessage('max_digits', $message);
        $escapedMessage = $this->normalizeMessageForJS($resolvedMessage);

        $rule = '.refine((val) => {'.
            'const str = String(Math.abs(Math.floor(val))); '.
            "return str.length <= {$value}; ".
            "}, { message: '{$escapedMessage}' })";

        $this->addRule($rule);

        return $this;
    }

    /**
     * Add min digits validation
     * Laravel min_digits:value validation
     */
    public function validateMinDigits(?array $parameters = [], ?string $message = null): self
    {
        [$value] = $parameters;
        $resolvedMessage = $this->resolveMessage('min_digits', $message);
        $escapedMessage = $this->normalizeMessageForJS($resolvedMessage);

        $rule = '.refine((val) => {'.
            'const str = String(Math.abs(Math.floor(val))); '.
            "return str.length >= {$value}; ".
            "}, { message: '{$escapedMessage}' })";

        $this->addRule($rule);

        return $this;
    }

    /**
     * Check if this is marked as an integer type
     */
    public function isInteger(): bool
    {
        return $this->isInteger;
    }
}
