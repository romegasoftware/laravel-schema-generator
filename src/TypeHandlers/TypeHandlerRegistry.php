<?php

namespace RomegaSoftware\LaravelSchemaGenerator\TypeHandlers;

use RomegaSoftware\LaravelSchemaGenerator\Contracts\TypeHandlerInterface;
use RomegaSoftware\LaravelSchemaGenerator\Data\SchemaPropertyData;

class TypeHandlerRegistry
{
    /** @var TypeHandlerInterface[] */
    protected array $handlers = [];

    protected bool $sorted = false;

    /**
     * Register a type handler
     */
    public function register(TypeHandlerInterface $handler): void
    {
        $this->handlers[] = $handler;
        $this->sorted = false;
    }

    /**
     * Get the appropriate handler for a given type
     */
    public function getHandler(string $type): ?TypeHandlerInterface
    {
        $this->ensureSorted();

        foreach ($this->handlers as $handler) {
            if ($handler->canHandle($type)) {
                return $handler;
            }
        }

        return null;
    }

    /**
     * Get the appropriate handler for a given property
     */
    public function getHandlerForProperty(SchemaPropertyData $property): ?TypeHandlerInterface
    {
        $this->ensureSorted();

        foreach ($this->handlers as $handler) {
            if ($handler->canHandleProperty($property)) {
                return $handler;
            }
        }

        return null;
    }

    /**
     * Get all registered handlers
     */
    public function getHandlers(): array
    {
        $this->ensureSorted();

        return $this->handlers;
    }

    /**
     * Clear all handlers (useful for testing)
     */
    public function clear(): void
    {
        $this->handlers = [];
        $this->sorted = false;
    }

    /**
     * Register multiple handlers at once
     */
    public function registerMany(array $handlers): void
    {
        foreach ($handlers as $handler) {
            $this->register($handler);
        }
    }

    /**
     * Ensure handlers are sorted by priority (highest first)
     */
    protected function ensureSorted(): void
    {
        if (! $this->sorted) {
            usort($this->handlers, function (TypeHandlerInterface $a, TypeHandlerInterface $b) {
                return $b->getPriority() <=> $a->getPriority();
            });
            $this->sorted = true;
        }
    }
}
