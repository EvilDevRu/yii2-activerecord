<?php
/**
 * @author Dmitriy Yurchenko <evildev@evildev.ru>
 */

namespace evildev\activerecord\traits\ActiveQuery;

/**
 * Добавляет в ActiveQuery дополнительные выборки для работы как с удаленными моделями, так и нет.
 * Свойства модели:
 *
 * Магические свойства:
 */
trait SoftDeleteTrait
{
    public ?string $softDeleteAttribute = 'is_delete';

    /**
     * @return $this
     */
    public function deleted(): static
    {
        return $this->andWhere([$this->softDeleteAttribute => true]);
    }

    /**
     * @return $this
     */
    public function notDeleted(): static
    {
        return $this->andWhere([$this->softDeleteAttribute => false]);
    }
}