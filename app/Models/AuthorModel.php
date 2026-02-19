<?php

namespace App\Models;

class AuthorModel
{
    public ?int $id;
    public string $name;
    public ?string $bio;

    public function __construct(?int $id, string $name, ?string $bio = null)
    {
        $this->id = $id;
        $this->name = $name;
        $this->bio = $bio;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getBio(): ?string
    {
        return $this->bio;
    }

    public function setBio(?string $bio): void
    {
        $this->bio = $bio;
    }
}