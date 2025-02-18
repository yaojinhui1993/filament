<?php

namespace Filament\Forms\Components;

class KeyValue extends Field
{
    protected string $view = 'forms::components.key-value';

    protected $addButtonLabel = null;

    protected $canAddRows = true;

    protected $canDeleteRows = true;

    protected $canEditKeys = true;

    protected $deleteButtonLabel = null;

    protected $keyLabel = null;

    protected $valueLabel = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->addButtonLabel(__('forms::components.key_value.buttons.add.label'));

        $this->deleteButtonLabel(__('forms::components.key_value.buttons.delete.label'));

        $this->keyLabel(__('forms::components.key_value.fields.key.label'));

        $this->valueLabel(__('forms::components.key_value.fields.value.label'));
    }

    public function addButtonLabel(string | callable $label): static
    {
        $this->addButtonLabel = $label;

        return $this;
    }

    public function deleteButtonLabel(string | callable $label): static
    {
        $this->deleteButtonLabel = $label;

        return $this;
    }

    public function disableAddingRows(bool | callable $condition = true): static
    {
        $this->canAddRows = fn (): bool => ! $this->evaluate($condition);

        return $this;
    }

    public function disableDeletingRows(bool | callable $condition = true): static
    {
        $this->canDeleteRows = fn (): bool => ! $this->evaluate($condition);

        return $this;
    }

    public function disableEditingKeys(bool | callable $condition = true): static
    {
        $this->canEditKeys = fn (): bool => ! $this->evaluate($condition);

        return $this;
    }

    public function keyLabel(string | callable $label): static
    {
        $this->keyLabel = $label;

        return $this;
    }

    public function valueLabel(string | callable $label): static
    {
        $this->valueLabel = $label;

        return $this;
    }

    public function canAddRows(): bool
    {
        return $this->evaluate($this->canAddRows);
    }

    public function canDeleteRows(): bool
    {
        return $this->evaluate($this->canDeleteRows);
    }

    public function canEditKeys(): bool
    {
        return $this->evaluate($this->canEditKeys);
    }

    public function getAddButtonLabel(): string
    {
        return $this->evaluate($this->addButtonLabel);
    }

    public function getDeleteButtonLabel(): string
    {
        return $this->evaluate($this->deleteButtonLabel);
    }

    public function getKeyLabel(): string
    {
        return $this->evaluate($this->keyLabel);
    }

    public function getValueLabel(): string
    {
        return $this->evaluate($this->valueLabel);
    }
}
