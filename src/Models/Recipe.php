<?php

declare(strict_types=1);

namespace App\Models;

class Recipe
{
    public int    $id           = 0;
    public int    $user_id      = 0;
    public string $title        = '';
    public string $author       = '';
    public int    $prep_time    = 0;
    public string $category     = '';
    public int    $category_id  = 0;
    public string $difficulty   = '';
    public string $ingredients  = '';
    public string $instructions = '';
    public string $created_at   = '';
    public string $updated_at   = '';
    /** @var string[] */
    public array  $tags         = [];

    public static function fromArray(array $data): self
    {
        $r               = new self();
        $r->id           = (int)($data['id']           ?? 0);
        $r->user_id      = (int)($data['user_id']      ?? 0);
        $r->title        = (string)($data['title']        ?? '');
        $r->author       = (string)($data['author']       ?? '');
        $r->prep_time    = (int)($data['prep_time']    ?? 0);
        $r->category     = (string)($data['category']     ?? '');
        $r->category_id  = (int)($data['category_id']  ?? 0);
        $r->difficulty   = (string)($data['difficulty']   ?? '');
        $r->ingredients  = (string)($data['ingredients']  ?? '');
        $r->instructions = (string)($data['instructions'] ?? '');
        $r->created_at   = (string)($data['created_at']   ?? '');
        $r->updated_at   = (string)($data['updated_at']   ?? '');
        $r->tags         = (array)($data['tags']           ?? []);
        return $r;
    }

    public function toArray(): array
    {
        return [
            'id'           => $this->id,
            'user_id'      => $this->user_id,
            'title'        => $this->title,
            'author'       => $this->author,
            'prep_time'    => $this->prep_time,
            'category'     => $this->category,
            'category_id'  => $this->category_id,
            'difficulty'   => $this->difficulty,
            'ingredients'  => $this->ingredients,
            'instructions' => $this->instructions,
            'created_at'   => $this->created_at,
            'updated_at'   => $this->updated_at,
            'tags'         => $this->tags,
        ];
    }
}
