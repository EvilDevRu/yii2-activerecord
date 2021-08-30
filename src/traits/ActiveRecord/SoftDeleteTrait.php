<?php
/**
 * @author Dmitriy Yurchenko <evildev@evildev.ru>
 */

namespace evildev\activerecord\traits\ActiveRecord;

use yii\web\ForbiddenHttpException;

/**
 * Работа с флагом удаленных моделей, чтобы не удалялись физически из базы.
 * Свойства модели:
 *
 * Магические свойства:
 * @property bool $isCanSoftDelete
 * @property bool $isCanRestore
 */
trait SoftDeleteTrait
{
    public ?string $softDeleteAttribute = 'is_delete';

    /**
     * @return array<array>
     */
    public function softDeleteRulesTrait(): array
    {
        return [
            [$this->softDeleteAttribute, 'boolean'],
        ];
    }

    /**
     * Поставит флаг, что модель удалена.
     * @return false|int
     */
    public function softDelete(): false|int
    {
        if ($this->hasProperty($this->softDeleteAttribute) && $this->isCanSoftDelete) {
            $this->{$this->softDeleteAttribute} = true;
            return $this->update(true, [$this->softDeleteAttribute]);
        }

        return $this->softDeleteAttribute === null ? 0 : false;
    }

    /**
     * Уберет флаг, что модель удалена.
     * @return false|int
     */
    public function restore(): false|int
    {
        if ($this->hasProperty($this->softDeleteAttribute) && $this->isCanRestore) {
            $this->{$this->softDeleteAttribute} = false;
            return $this->update(true, [$this->softDeleteAttribute]);
        }

        return $this->softDeleteAttribute === null ? 0 : false;
    }

    /**
     * Вернет true, если модель можно мягко удалить.
     * @param bool $isMute
     * @return bool
     * @throws ForbiddenHttpException
     */
    public function getIsCanSoftDelete(bool $isMute = false): bool
    {
        if (!$this->userCan('softdelete') && !$isMute) {
            throw new ForbiddenHttpException($this->errorMessages()['softdelete']);
        }

        return $this->userCan('softdelete')
            && !$this->{$this->softDeleteAttribute}
            && !$this->isNewRecord;
    }

    /**
     * Вернет true, если модель можно восстановить после мягкого удаления.
     * @param bool $isMute
     * @return bool
     * @throws ForbiddenHttpException
     */
    public function getIsCanRestore(bool $isMute = false): bool
    {
        if (!$this->userCan('restore') && !$isMute) {
            throw new ForbiddenHttpException($this->errorMessages()['restore']);
        }

        return $this->userCan('restore')
            && $this->{$this->softDeleteAttribute}
            && !$this->isNewRecord;
    }
}