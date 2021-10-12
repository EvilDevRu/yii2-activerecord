<?php
/**
 * @author Dmitriy Yurchenko <evildev@evildev.ru>
 */

namespace evildev\activerecord\traits\ActiveRecord;

use evildev\activerecord\ActiveRecord;
use yii\behaviors\TimestampBehavior;
use yii\db\Expression;

/**
 * Добавляет автоматическое обновление даты создания и даты обновления модели.
 * Свойства модели:
 * Магические свойства:
 */
trait DateTimeTrait
{
    public ?string $dateCreateAttribute = 'date_create';
    public ?string $dateUpdateAttribute = 'date_update';

    /**
     * @return array<array>
     */
    public function dateTimeBehaviorsTrait(): array
    {
        return [
            [
                'class' => TimestampBehavior::class,
                'attributes' => [
                    ActiveRecord::EVENT_BEFORE_INSERT => array_filter([
                        $this->dateCreateAttribute,
                        $this->dateUpdateAttribute
                    ]),
                    ActiveRecord::EVENT_BEFORE_UPDATE => array_filter([$this->dateUpdateAttribute]),
                ],
                'value' => new Expression('NOW()'),
            ],
        ];
    }
}