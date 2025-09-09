<?php

namespace RomegaSoftware\LaravelSchemaGenerator\ZodBuilders;

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
    public function integer(?string $message = null): self
    {
        $this->isInteger = true;
        $resolvedMessage = $this->resolveMessage('integer', $message);

        if ($resolvedMessage) {
            $escapedMessage = $this->escapeForJS($resolvedMessage);
            $this->integerMessage = $escapedMessage;
        }

        return $this;
    }

    /**
     * Make field required using Zod refine approach
     */
    public function required(?string $message = null): self
    {
        $resolvedMessage = $this->resolveMessage('required', $message);

        if ($resolvedMessage) {
            $escapedMessage = $this->escapeForJS($resolvedMessage);
            $this->requiredMessage = $escapedMessage;
        }

        return $this;
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
     * Add greater than validation
     */
    public function gt(int|float $value, ?string $message = null): self
    {
        $messageStr = $this->formatMessage($message);
        $rule = ".gt({$value}{$messageStr})";

        return $this->replaceRule('gt', $rule);
    }

    /**
     * Add greater than or equal validation
     */
    public function gte(int|float $value, ?string $message = null): self
    {
        $messageStr = $this->formatMessage($message);
        $rule = ".gte({$value}{$messageStr})";

        return $this->replaceRule('gte', $rule);
    }

    /**
     * Add less than validation
     */
    public function lt(int|float $value, ?string $message = null): self
    {
        $messageStr = $this->formatMessage($message);
        $rule = ".lt({$value}{$messageStr})";

        return $this->replaceRule('lt', $rule);
    }

    /**
     * Add less than or equal validation
     */
    public function lte(int|float $value, ?string $message = null): self
    {
        $messageStr = $this->formatMessage($message);
        $rule = ".lte({$value}{$messageStr})";

        return $this->replaceRule('lte', $rule);
    }

    /**
     * Add multiple of validation
     */
    public function multipleOf(int|float $value, ?string $message = null): self
    {
        $messageStr = $this->formatMessage($message);
        $rule = ".multipleOf({$value}{$messageStr})";

        return $this->replaceRule('multipleOf', $rule);
    }

    /**
     * Add positive number validation (> 0)
     */
    public function positive(?string $message = null): self
    {
        if ($message === null) {
            $rule = '.positive()';
        } else {
            // For methods that only take a message parameter, we need to format it without leading comma
            $escapedMessage = str_replace("'", "\\'", $message);
            $rule = ".positive('{$escapedMessage}')";
        }

        return $this->replaceRule('positive', $rule);
    }

    /**
     * Add non-negative number validation (>= 0)
     */
    public function nonNegative(?string $message = null): self
    {
        if ($message === null) {
            $rule = '.nonnegative()';
        } else {
            // For methods that only take a message parameter, we need to format it without leading comma
            $escapedMessage = str_replace("'", "\\'", $message);
            $rule = ".nonnegative('{$escapedMessage}')";
        }

        return $this->replaceRule('nonnegative', $rule);
    }

    /**
     * Add negative number validation (< 0)
     */
    public function negative(?string $message = null): self
    {
        if ($message === null) {
            $rule = '.negative()';
        } else {
            // For methods that only take a message parameter, we need to format it without leading comma
            $escapedMessage = str_replace("'", "\\'", $message);
            $rule = ".negative('{$escapedMessage}')";
        }

        return $this->replaceRule('negative', $rule);
    }

    /**
     * Add non-positive number validation (<= 0)
     */
    public function nonPositive(?string $message = null): self
    {
        if ($message === null) {
            $rule = '.nonpositive()';
        } else {
            // For methods that only take a message parameter, we need to format it without leading comma
            $escapedMessage = str_replace("'", "\\'", $message);
            $rule = ".nonpositive('{$escapedMessage}')";
        }

        return $this->replaceRule('nonpositive', $rule);
    }

    /**
     * Add finite number validation (not infinite or NaN)
     */
    public function finite(?string $message = null): self
    {
        if ($message === null) {
            $rule = '.finite()';
        } else {
            // For methods that only take a message parameter, we need to format it without leading comma
            $escapedMessage = str_replace("'", "\\'", $message);
            $rule = ".finite('{$escapedMessage}')";
        }

        return $this->replaceRule('finite', $rule);
    }

    /**
     * Add decimal places validation
     * Laravel decimal:min,max validation
     */
    public function decimal(int $min, ?int $max = null, ?string $message = null): self
    {
        $resolvedMessage = $this->resolveMessage('decimal', $message) ?? 'Invalid decimal places';
        $escapedMessage = $this->escapeForJS($resolvedMessage);

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

        return $this->addRule($rule);
    }

    /**
     * Add exact digits validation
     * Laravel digits:value validation
     */
    public function digits(int $value, ?string $message = null): self
    {
        $resolvedMessage = $this->resolveMessage('digits', $message) ?? 'Invalid number of digits';
        $escapedMessage = $this->escapeForJS($resolvedMessage);

        $rule = '.refine((val) => {'.
            'const str = String(Math.abs(Math.floor(val))); '.
            "return str.length === {$value}; ".
            "}, { message: '{$escapedMessage}' })";

        return $this->addRule($rule);
    }

    /**
     * Add digits between validation
     * Laravel digits_between:min,max validation
     */
    public function digitsBetween(int $min, int $max, ?string $message = null): self
    {
        $resolvedMessage = $this->resolveMessage('digits_between', $message) ?? 'Invalid number of digits';
        $escapedMessage = $this->escapeForJS($resolvedMessage);

        $rule = '.refine((val) => {'.
            'const str = String(Math.abs(Math.floor(val))); '.
            'const len = str.length; '.
            "return len >= {$min} && len <= {$max}; ".
            "}, { message: '{$escapedMessage}' })";

        return $this->addRule($rule);
    }

    /**
     * Add max digits validation
     * Laravel max_digits:value validation
     */
    public function maxDigits(int $value, ?string $message = null): self
    {
        $resolvedMessage = $this->resolveMessage('max_digits', $message) ?? 'Too many digits';
        $escapedMessage = $this->escapeForJS($resolvedMessage);

        $rule = '.refine((val) => {'.
            'const str = String(Math.abs(Math.floor(val))); '.
            "return str.length <= {$value}; ".
            "}, { message: '{$escapedMessage}' })";

        return $this->addRule($rule);
    }

    /**
     * Add min digits validation
     * Laravel min_digits:value validation
     */
    public function minDigits(int $value, ?string $message = null): self
    {
        $resolvedMessage = $this->resolveMessage('min_digits', $message) ?? 'Not enough digits';
        $escapedMessage = $this->escapeForJS($resolvedMessage);

        $rule = '.refine((val) => {'.
            'const str = String(Math.abs(Math.floor(val))); '.
            "return str.length >= {$value}; ".
            "}, { message: '{$escapedMessage}' })";

        return $this->addRule($rule);
    }

    /**
     * Check if this is marked as an integer type
     */
    public function isInteger(): bool
    {
        return $this->isInteger;
    }
}
