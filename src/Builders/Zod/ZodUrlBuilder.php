<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Builders\Zod;

use Override;

class ZodUrlBuilder extends ZodStringBuilder
{
    /**
     * Custom error message for URL validation failures.
     */
    protected ?string $urlErrorMessage = null;

    /**
     * Pre-built JavaScript regex literal restricting allowed URL protocols.
     */
    protected ?string $urlProtocolPattern = null;

    #[Override]
    protected function getBaseType(): string
    {
        $options = [];

        if ($this->urlErrorMessage !== null && $this->urlErrorMessage !== '') {
            $options[] = "error: '{$this->urlErrorMessage}'";
        } elseif (isset($this->requiredMessage)) {
            $options[] = "error: '{$this->requiredMessage}'";
        }

        if ($this->urlProtocolPattern !== null) {
            $options[] = "protocol: {$this->urlProtocolPattern}";
        }

        $content = empty($options)
            ? 'z.url()'
            : 'z.url({ '.implode(', ', $options).' })';

        if (isset($this->requiredMessage)) {
            $content .= ".refine((val) => val != undefined && val != null && val != '', { error: '{$this->requiredMessage}' })";
        }

        return $content;
    }

    /**
     * Handle URL validation rule details like protocol filters and custom messages.
     */
    public function validateUrl(?array $parameters = [], ?string $message = null): self
    {
        $resolvedMessage = $this->resolveMessage('url', $message);
        if ($resolvedMessage !== null) {
            $this->urlErrorMessage = $this->normalizeMessageForJS($resolvedMessage);
        }

        $protocols = array_filter($parameters ?? [], static fn ($value) => $value !== null && $value !== '');
        if ($protocols !== []) {
            $this->urlProtocolPattern = $this->buildProtocolPattern($protocols);
        }

        return $this;
    }

    /**
     * Build the regex literal restricting acceptable URL protocols.
     *
     * @param  array<int, string>  $protocols
     */
    protected function buildProtocolPattern(array $protocols): string
    {
        $escapedProtocols = array_map(static fn ($protocol) => preg_quote((string) $protocol, '/'), $protocols);

        $pattern = count($escapedProtocols) === 1
            ? '^'.$escapedProtocols[0].'$'
            : '^(?:'.implode('|', $escapedProtocols).')$';

        return '/'.$pattern.'/';
    }
}
