<?php

declare(strict_types=1);

/** Hiển thị thông báo lỗi của 1 trường nếu có. */
function field_error(array $errors, string $key): string
{
    return isset($errors[$key]) ? '<span class="form-text-error">' . e($errors[$key]) . '</span>' : '';
}

/** Class is-invalid khi trường có lỗi. */
function invalid_class(array $errors, string $key): string
{
    return isset($errors[$key]) ? ' is-invalid' : '';
}
