<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Builders\Zod;

use Override;
use RomegaSoftware\LaravelSchemaGenerator\Builders\Traits\HandlesFiles;

class ZodFileBuilder extends ZodBuilder
{
    use HandlesFiles;

    protected array $mimeTypes = [];

    protected array $extensions = [];

    protected ?int $minSize = null;

    protected ?int $maxSize = null;

    protected function getBaseType(): string
    {
        return 'z.file()';
    }

    /**
     * Mark as a file validation
     */
    public function validateFile(?array $parameters = [], ?string $message = null): self
    {
        // File is already handled by getBaseType
        // Message parameter kept for interface compatibility
        return $this;
    }

    /**
     * Mark as an image file
     */
    public function validateImage(?array $parameters = [], ?string $message = null): self
    {
        // Common image MIME types
        $imageMimeTypes = [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/bmp',
            'image/webp',
        ];

        if (in_array('allow_svg', $parameters)) {
            $imageMimeTypes[] = 'image/svg+xml';
        }

        $this->mimeTypes = array_unique(array_merge($this->mimeTypes, $imageMimeTypes));

        $resolvedMessage = $this->resolveMessage('image', $message);
        $messageStr = $this->formatMessageAsParameter($resolvedMessage);
        $mimeTypesStr = implode("', '", $this->mimeTypes);

        // If user has defined mimes already, we will respect those.
        if (! $this->hasRule('mime')) {
            $this->replaceRule('mime', ".mime(['{$mimeTypesStr}']{$messageStr})");
        }

        return $this;
    }

    /**
     * Add MIME type validation
     */
    public function validateMimes(?array $parameters = [], ?string $message = null): self
    {
        $mimeTypes = $this->convertMimesToMimeTypes($parameters);

        $resolvedMessage = $this->resolveMessage('mimes', $message);
        $this->validateMimetypes($mimeTypes, $resolvedMessage);

        return $this;
    }

    /**
     * Add MIME type by extension validation
     */
    public function validateMimetypes(?array $parameters = [], ?string $message = null): self
    {
        $resolvedMessage = $this->resolveMessage('mimes', $message);
        $messageStr = $this->formatMessageAsParameter($resolvedMessage);
        $mimeTypesStr = implode("', '", array_unique($parameters));

        $this->replaceRule('mime', ".mime(['{$mimeTypesStr}']{$messageStr})");

        return $this;
    }

    /**
     * Add file extension validation
     */
    public function validateExtensions(?array $parameters = [], ?string $message = null): self
    {
        $mimeTypes = $this->convertExtensionsToMimeTypes($parameters);

        $resolvedMessage = $this->resolveMessage('extensions', $message);

        $this->validateMimetypes($mimeTypes, $resolvedMessage);

        return $this;
    }

    /**
     * Add minimum file size validation (in kilobytes)
     */
    #[Override]
    public function validateMin(?array $parameters = [], ?string $message = null): self
    {
        [$sizeInKb] = $parameters;
        $this->minSize = $sizeInKb * 1024; // Convert KB to bytes

        $resolvedMessage = $this->resolveMessage('min', $message, ['min' => $sizeInKb]);
        $messageStr = $this->formatMessageAsParameter($resolvedMessage);
        $rule = ".min({$this->minSize}{$messageStr})";

        $this->replaceRule('min', $rule);

        return $this;
    }

    /**
     * Add maximum file size validation (in kilobytes)
     */
    #[Override]
    public function validateMax(?array $parameters = [], ?string $message = null): self
    {
        [$sizeInKb] = $parameters;
        $this->maxSize = $sizeInKb * 1024; // Convert KB to bytes

        $resolvedMessage = $this->resolveMessage('max', $message, ['max' => $sizeInKb]);
        $messageStr = $this->formatMessageAsParameter($resolvedMessage);
        $rule = ".max({$this->maxSize}{$messageStr})";

        $this->replaceRule('max', $rule);

        return $this;
    }

    /**
     * Add exact file size validation (in kilobytes)
     */
    public function validateSize(?array $parameters = [], ?string $message = null): self
    {
        [$sizeInKb] = $parameters;
        $resolvedMessage = $this->resolveMessage('size', $message, ['size' => $sizeInKb]);

        $this->validateMin([$sizeInKb], $resolvedMessage);
        $this->validateMax([$sizeInKb], $resolvedMessage);

        return $this;
    }

    /**
     * Add file size between validation (in kilobytes)
     */
    public function validateBetween(?array $parameters = [], ?string $message = null): self
    {
        [$minSizeInKb, $maxSizeInKb] = $parameters;
        $resolvedMessage = $this->resolveMessage('between', $message, ['min' => $minSizeInKb, 'max' => $maxSizeInKb]);

        $this->validateMin([$minSizeInKb], $resolvedMessage);
        $this->validateMax([$maxSizeInKb], $resolvedMessage);

        return $this;
    }

    /**
     * Add image dimensions validation
     *
     * @param  array  $constraints  Array with keys: min_width, max_width, min_height, max_height, width, height, ratio
     */
    public function validateDimensions(?array $parameters = [], ?string $message = null): self
    {
        $constraints = $this->extractDimensions($parameters);

        // Build dimension checks
        $checks = [];
        $resolvedMessage = $this->resolveMessage('dimensions', $message);

        if (isset($constraints['width'])) {
            $checks[] = "img.width === {$constraints['width']}";
        }

        if (isset($constraints['height'])) {
            $checks[] = "img.height === {$constraints['height']}";
        }

        if (isset($constraints['min_width'])) {
            $checks[] = "img.width >= {$constraints['min_width']}";
        }

        if (isset($constraints['max_width'])) {
            $checks[] = "img.width <= {$constraints['max_width']}";
        }

        if (isset($constraints['min_height'])) {
            $checks[] = "img.height >= {$constraints['min_height']}";
        }

        if (isset($constraints['max_height'])) {
            $checks[] = "img.height <= {$constraints['max_height']}";
        }

        if (isset($constraints['ratio'])) {
            // Parse ratio (e.g., "3/2" or "1.5")
            if (str_contains($constraints['ratio'], '/')) {
                [$width, $height] = explode('/', $constraints['ratio']);
                $ratio = (float) $width / (float) $height;
            } else {
                $ratio = (float) $constraints['ratio'];
            }
            $checks[] = "Math.abs((img.width / img.height) - {$ratio}) < 0.01";
        }

        if (empty($checks)) {
            return $this;
        }

        $resolvedMessage = $this->resolveMessage('dimensions', $message);
        $escapedMessage = $this->normalizeMessageForJS($resolvedMessage);

        // Create a validation that loads the image and checks dimensions
        // Note: This requires async validation in the frontend
        $checksStr = implode(' && ', $checks);

        $rule = ".refine((file) =>
            new Promise((resolve) => {
                const reader = new FileReader();
                reader.onload = (e) => {
                const img = new Image();
                img.onload = () => {
                    const meetsDimensions = {$checksStr};
                    resolve(meetsDimensions);
                };
                img.src = e.target?.result as string;
                };
                reader.readAsDataURL(file);
            }),
            { error: '{$escapedMessage}'})";

        $this->replaceRule('dimensions', $rule);

        return $this;
    }
}
