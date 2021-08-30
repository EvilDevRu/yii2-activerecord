<?php
/**
 * @author Dmitriy Yurchenko <evildev@evildev.ru>
 */

namespace evildev\activerecord\traits\Migration;

/**
 * Работа с флагом удаленных моделей, чтобы не удалялись физически из базы.
 * Свойства модели:
 *
 * Магические свойства:
 */
trait SoftDeleteTrait
{
    public string $softDeleteAttribute = 'is_delete';

    /**
     * Добавит колонки в таблицу.
     * @param string $tableName
     */
    public function addColumns(string $tableName)
    {
        $this->addColumn($tableName, $this->softDeleteAttribute, $this->boolean()->defaultValue(false));
    }

    /**
     * Удалит колонки из таблицы.
     * @param string $tableName
     */
    public function dropColumns(string $tableName)
    {
        $this->dropColumn($tableName, $this->softDeleteAttribute);
    }
}