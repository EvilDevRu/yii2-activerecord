<?php
/**
 * @author Dmitriy Yurchenko <evildev@evildev.ru>
 */

namespace evildev\activerecord\validators;

use yii\validators\Validator;

/**
 * Валидатор для JSON полей.
 * Свойства модели:
 *
 * Магические свойства:
 */
class JsonValidator extends Validator
{
    /**
     * @inheritDoc
     */
    protected function validateValue($value): ?array
    {
        json_decode($value);
        return json_last_error()
            ? ['Неверный тип данных', ['extendedMessage' => json_last_error_msg()]]
            : null;
    }
}