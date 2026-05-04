<?php

declare(strict_types=1);

namespace App\Validators;

/**
 * Валидатор данных пользователя (регистрация/создание).
 *
 * Проверяет username, email и password по базовым правилам.
 * Уникальность не проверяется — это ответственность слоя БД.
 *
 * @package App\Validators
 */
class UserValidator
{
    /** @var array<string,string> Карта ошибок: поле → сообщение */
    private array $errors = [];

    /**
     * Валидирует данные пользователя.
     *
     * @param  array $data Поля: username, email, password
     * @return bool        true если ошибок нет
     */
    public function validate(array $data): bool
    {
        $this->errors = [];
        $this->validateUsername($data['username'] ?? '');
        $this->validateEmail($data['email'] ?? '');
        $this->validatePassword($data['password'] ?? '');
        return empty($this->errors);
    }

    /**
     * Возвращает карту ошибок после validate().
     *
     * @return array<string,string>
     */
    public function getErrors(): array { return $this->errors; }

    /**
     * @param string $value Только латиница, цифры и _, 3–50 символов
     */
    private function validateUsername(string $value): void
    {
        if (empty($value))           { $this->errors['username'] = 'Введите имя пользователя'; }
        elseif (strlen($value) < 3)  { $this->errors['username'] = 'Минимум 3 символа'; }
        elseif (strlen($value) > 50) { $this->errors['username'] = 'Максимум 50 символов'; }
        elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $value)) {
            $this->errors['username'] = 'Только латиница, цифры и _';
        }
    }

    /**
     * @param string $value Валидный email-адрес
     */
    private function validateEmail(string $value): void
    {
        if (empty($value)) {
            $this->errors['email'] = 'Введите email';
        } elseif (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->errors['email'] = 'Неверный формат email';
        }
    }

    /**
     * @param string $value Минимум 6 символов
     */
    private function validatePassword(string $value): void
    {
        if (empty($value))           { $this->errors['password'] = 'Введите пароль'; }
        elseif (strlen($value) < 6)  { $this->errors['password'] = 'Минимум 6 символов'; }
    }
}
