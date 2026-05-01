<?php

declare(strict_types=1);

namespace App\Models;

class User
{
    public int    $id         = 0;
    public string $username   = '';
    public string $email      = '';
    public string $password   = '';
    public string $created_at = '';

    public static function fromArray(array $data): self
    {
        $user             = new self();
        $user->id         = (int)($data['id']         ?? 0);
        $user->username   = (string)($data['username'] ?? '');
        $user->email      = (string)($data['email']    ?? '');
        $user->password   = (string)($data['password'] ?? '');
        $user->created_at = (string)($data['created_at'] ?? '');
        return $user;
    }

    public function toArray(): array
    {
        return [
            'id'         => $this->id,
            'username'   => $this->username,
            'email'      => $this->email,
            'created_at' => $this->created_at,
        ];
    }
}
