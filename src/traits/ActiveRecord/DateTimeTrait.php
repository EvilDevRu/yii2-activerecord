<?php
/**
 * @author Dmitriy Yurchenko <evildev@evildev.ru>
 */

namespace evildev\activerecord\traits\ActiveRecord;

use yii\db\Expression;

/**
 * Добавляет автоматическое обновление даты создания и даты обновления модели.
 * Свойства модели:
 *
 * Магические свойства:
 */
trait DateTimeTrait
{
    public ?string $dateCreateAttribute = 'date_create';
    public ?string $dateUpdateAttribute = 'date_update';

    /**
     * @return mixed
     */
    public function beforeValidate()
    {
        //  Обновляем date_create и date_update, если те заданы (через behavior не получится т.к. динамически надо).
        if ($this->dateCreateAttribute !== null && $this->isNewRecord) {
            $attribute = $this->dateCreateAttribute;
            $this->$attribute = new Expression('NOW()');
        }

        if ($this->dateUpdateAttribute !== null) {
            $attribute = $this->dateUpdateAttribute;
            $this->$attribute = new Expression('NOW()');
        }

        return parent::beforeValidate();
    }
}