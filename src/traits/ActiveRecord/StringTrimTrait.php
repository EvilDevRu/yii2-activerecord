<?php
/**
 * @author Dmitriy Yurchenko <evildev@evildev.ru>
 */

namespace evildev\activerecord\traits\ActiveRecord;

use yii\db\Schema;

/**
 * Добавит правило тримить все строки.
 * Свойства модели:
 *
 * Магические свойства:
 */
trait StringTrimTrait
{
    /**
     * @return array
     */
    public function stringTrimRulesTrait(): array
    {
        $stringColumns = array_keys(static::getColumnsByType([Schema::TYPE_STRING, Schema::TYPE_TEXT]));
        return [
            [$stringColumns, 'filter', 'filter' => 'trim'],
        ];
    }
}