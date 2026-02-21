<?php

namespace App\Models;
class BookModel{
    public ?int $id;
    public string $title;
    public string $description;
    public float $price;
    public int $stock;
    public int $author_id;
    public int $category_id;
    public string $image;
    public string $published_date;
    public ?string $author_name;
    public ?string $category_name;
    public ?float $rating;
    public ?int $sales_count;

    //Constructor:
        public function __construct(?int $id, string $title, string $description, float $price, int $stock, int $author_id, int $category_id, string $image, string $published_date, ?string $author_name = null, ?string $category_name = null, ?float $rating = null, ?int $sales_count = null){
        $this->id = $id;
        $this->title = $title;
        $this->description = $description;
        $this->price = $price;
        $this->stock = $stock;
        $this->author_id = $author_id;
        $this->category_id = $category_id;
        $this->image = $image;
        $this->published_date = $published_date;
        $this->author_name = $author_name;
        $this->category_name = $category_name;
        $this->rating = $rating;
        $this->sales_count = $sales_count;
    }

    //Getters:
    public function getId(): ?int{
        return $this->id;
    }
    public function getTitle(): string{
        return $this->title;
    }
    public function getDescription(): string{
        return $this->description;
    }
    public function getPrice(): float{
        return $this->price;
    }
    public function getStock(): int{
        return $this->stock;
    }
    public function getAuthorId(): int{
        return $this->author_id;
    }
    public function getCategoryId(): int{
        return $this->category_id;
    }
    public function getImage(): string{
        return $this->image;
    }
    public function getPublishedDate(): string{
        return $this->published_date;
    }
    public function getAuthorName(): ?string{
        return $this->author_name;
    }
    public function getCategoryName(): ?string{
        return $this->category_name;
    }
    public function getRating(): ?float{
        return $this->rating;
    }
    public function getSalesCount(): ?int{
        return $this->sales_count;
    }

    //Setters:
    public function setTitle(string $title): void{
        $this->title = $title;
    }
    public function setDescription(string $description): void{
        $this->description = $description;
    }
    public function setPrice(float $price): void{
        $this->price = $price;
    }
    public function setStock(int $stock): void{
        $this->stock = $stock;
    }
    public function setAuthorId(int $author_id): void{
        $this->author_id = $author_id;
    }
    public function setCategoryId(int $category_id): void{
        $this->category_id = $category_id;
    }
    public function setImage(string $image): void{
        $this->image = $image;
    }
    public function setPublishedDate(string $published_date): void{
        $this->published_date = $published_date;
    }
}
