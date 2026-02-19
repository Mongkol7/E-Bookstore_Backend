<?php

namespace App\Models;

class AdminModel
{
    public ?int $id;
    public string $first_name;
    public string $last_name;
    public string $email;
    public string $password;
    public ?string $phone;
    public ?string $address;
    public ?string $created_at;
    public ?string $last_login;

    public function __construct(
        ?int $id,
        string $first_name,
        string $last_name,
        string $email,
        string $password,
        ?string $phone = null,
        ?string $address = null,
        ?string $created_at = null,
        ?string $last_login = null
    ) {
        $this->id = $id;
        $this->first_name = $first_name;
        $this->last_name = $last_name;
        $this->email = $email;
        $this->password = $password;
        $this->phone = $phone;
        $this->address = $address;
        $this->created_at = $created_at;
        $this->last_login = $last_login;
    }

    // Getters
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFirstName(): string
    {
        return $this->first_name;
    }

    public function getLastName(): string
    {
        return $this->last_name;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function getCreatedAt(): ?string
    {
        return $this->created_at;
    }

    public function getLastLogin(): ?string
    {
        return $this->last_login;
    }

    // Setters
    public function setFirstName(string $first_name): void
    {
        $this->first_name = $first_name;
    }

    public function setLastName(string $last_name): void
    {
        $this->last_name = $last_name;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    public function setPassword(string $password): void
    {
        $this->password = $password;
    }

    public function setPhone(?string $phone): void
    {
        $this->phone = $phone;
    }

    public function setAddress(?string $address): void
    {
        $this->address = $address;
    }

    public function setLastLogin(?string $last_login): void
    {
        $this->last_login = $last_login;
    }
}
