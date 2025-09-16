<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Builders\Zod;

/**
 * Specialized builder for password validation rules
 *
 * Handles Laravel's Password rule with its various constraints
 * like letters, mixed case, numbers, symbols, and uncompromised checks
 */
class ZodPasswordBuilder extends ZodStringBuilder
{
    protected bool $hasPasswordRule = false;

    protected array $passwordConstraints = [];

    /**
     * Get the base type for password fields
     */
    protected function getBaseType(): string
    {
        // Start with string base type
        $content = 'z.string()';

        // Apply trim by default for passwords
        $this->replaceRule('trim', '.trim()');

        // Apply required message if set
        if (isset($this->requiredMessage)) {
            $content .= ".refine((val) => val != undefined && val != null && val != '', { error: '{$this->requiredMessage}'})";
        }

        return $content;
    }

    /**
     * Mark that this field has password validation
     */
    public function validatePassword(?array $parameters = [], ?string $message = null): self
    {
        $this->hasPasswordRule = true;

        // Password fields are implicitly strings, no additional validation needed here
        return $this;
    }

    /**
     * Add letters requirement to password
     */
    public function validatePasswordLetters(?array $parameters = [], ?string $message = null): self
    {
        $resolvedMessage = $message ?? 'The password must contain at least one letter.';
        $escapedMessage = $this->normalizeMessageForJS($resolvedMessage);

        // Use regex to check for at least one letter
        $pattern = '/[a-zA-Z]/';
        $rule = ".regex({$pattern}, '{$escapedMessage}')";

        $this->addRule($rule);
        $this->passwordConstraints['letters'] = true;

        return $this;
    }

    /**
     * Add mixed case requirement to password
     */
    public function validatePasswordMixed(?array $parameters = [], ?string $message = null): self
    {
        $resolvedMessage = $message ?? 'The password must contain at least one uppercase and one lowercase letter.';
        $escapedMessage = $this->normalizeMessageForJS($resolvedMessage);

        // Use refine to check for both uppercase and lowercase
        $rule = ".refine((val) => /[a-z]/.test(val) && /[A-Z]/.test(val), { message: '{$escapedMessage}' })";

        $this->addRule($rule);
        $this->passwordConstraints['mixed'] = true;

        return $this;
    }

    /**
     * Add numbers requirement to password
     */
    public function validatePasswordNumbers(?array $parameters = [], ?string $message = null): self
    {
        $resolvedMessage = $message ?? 'The password must contain at least one number.';
        $escapedMessage = $this->normalizeMessageForJS($resolvedMessage);

        // Use regex to check for at least one number
        $pattern = '/\\d/';
        $rule = ".regex(/\\d/, '{$escapedMessage}')";

        $this->addRule($rule);
        $this->passwordConstraints['numbers'] = true;

        return $this;
    }

    /**
     * Add symbols requirement to password
     */
    public function validatePasswordSymbols(?array $parameters = [], ?string $message = null): self
    {
        $resolvedMessage = $message ?? 'The password must contain at least one symbol.';
        $escapedMessage = $this->normalizeMessageForJS($resolvedMessage);

        // Use regex to check for at least one special character
        $pattern = '/[^a-zA-Z0-9]/';
        $rule = ".regex(/[^a-zA-Z0-9]/, '{$escapedMessage}')";

        $this->addRule($rule);
        $this->passwordConstraints['symbols'] = true;

        return $this;
    }

    /**
     * Add uncompromised check to password
     * Note: This would typically require an external API check in the frontend
     */
    public function validatePasswordUncompromised(?array $parameters = [], ?string $message = null): self
    {
        // This is validated by the backend

        return $this;
    }
}
