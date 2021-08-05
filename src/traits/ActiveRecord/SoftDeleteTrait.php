<?php
/**
 * @author Dmitriy Yurchenko <evildev@evildev.ru>
 */

namespace evildev\activerecord\traits\ActiveRecord;

/**
 * Работа с флагом удаленных моделей, чтобы не удалялись физически из базы.
 * Свойства модели:
 *
 * Магические свойства:
 */
trait SoftDeleteTrait
{
    public ?string $softDeleteAttribute = 'is_delete';

    /**
     * @return array
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            [$this->softDeleteAttribute, 'boolean'],
        ]);
    }

    /**
     * Поставит флаг, что модель удалена.
     * @return false|int
     */
    public function softDelete(): false|int
    {
        if ($this->hasProperty($this->softDeleteAttribute)) {
            $attributeName = $this->softDeleteAttribute;
            $this->$attributeName = true;
            return $this->update(true, [$attributeName]);
        }

        return $this->softDeleteAttribute === null ? 0 : false;
    }
}