<?php
/**
 * @author Dmitriy Yurchenko <evildev@evildev.ru>
 */

namespace evildev\activerecord\validators;

use yii\validators\Validator;

/**
 * Валидатор для array полей.
 * Свойства модели:
 * Магические свойства:
 */
class ArrayValidator extends Validator
{
    /**
     * @inheritDoc
     */
    protected function validateValue($value): ?array
    {
        return !is_array($value)
            ? ['{attribute} не является массивом', []]
            : null;
    }
}