<?php

namespace App\Message;

class ProcessDataMessage
{
    public function __construct(
        private string $id,
        private array $data
    ) {}

    public function getId(): string
    {
        return $this->id;
    }

    public function getData(): array
    {
        return $this->data;
    }
}