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
    public function addSoftDeleteColumns(string $tableName)
    {
        $this->addColumn($tableName, $this->softDeleteAttribute, $this->boolean()->defaultValue(false));

        $tableName = str_replace(['{', '%', '}'], '', $tableName);
        $this->createIndex('idx_' . mb_strtolower($tableName) . '_is_delete', $tableName, ['is_delete']);
    }

    /**
     * Удалит колонки из таблицы.
     * @param string $tableName
     */
    public function dropSoftDeleteColumns(string $tableName)
    {
        $this->dropColumn($tableName, $this->softDeleteAttribute);
    }
}