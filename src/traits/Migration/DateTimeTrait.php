<?php
/**
 * @author Dmitriy Yurchenko <evildev@evildev.ru>
 */

namespace evildev\activerecord\traits\Migration;

use evildev\activerecord\Migration;

/**
 * Добавляет автоматическое обновление даты создания и даты обновления модели.
 *
 * Свойства модели:
 *
 * Магические свойства:
 */
trait DateTimeTrait
{
    public string $dateCreateAttribute = 'date_create';
    public string $dateUpdateAttribute = 'date_update';

    /**
     * Добавит колонки в таблицу.
     * @param string $tableName
     */
    public function addDateTimeColumns(string $tableName)
    {
        $this->addColumn($tableName, $this->dateCreateAttribute, $this->dateTime());
        $this->addColumn($tableName, $this->dateUpdateAttribute, $this->dateTime());
    }

    /**
     * Финальная обработка колонок.
     * @param string $tableName
     */
    public function alterDateTimeColumns(string $tableName)
    {
        $this->alterColumn($tableName, $this->dateCreateAttribute, $this->dateTime()->notNull());
        $this->alterColumn($tableName, $this->dateUpdateAttribute, $this->dateTime()->notNull());
    }

    /**
     * Удалит колонки из таблицы.
     * @param string $tableName
     */
    public function dropDateTimeColumns(string $tableName)
    {
        $this->dropColumn($tableName, $this->dateCreateAttribute);
        $this->dropColumn($tableName, $this->dateUpdateAttribute);
    }
}