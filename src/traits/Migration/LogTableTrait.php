<?php
/**
 * @author Dmitriy Yurchenko <evildev@evildev.ru>
 */

namespace evildev\activerecord\traits\Migration;

use evildev\activerecord\Migration;

/**
 * Добавляет автоматическое создание таблицы логирования модели.
 *
 * Свойства модели:
 *
 * Магические свойства:
 */
trait LogTableTrait
{
    /**
     * Создаст таблицу.
     * @param string $tableName
     */
    public function createLogTable(string $tableName)
    {
        $this->createTable($tableName, [
            'id' => $this->bigPrimaryKey(),
            'user_id' => $this->integer(),
            'model_id' => $this->integer(),
            'data_before' => $this->json(),
            'data_after' => $this->json(),
            'date_create' => $this->dateTime(),
        ]);
    }
}