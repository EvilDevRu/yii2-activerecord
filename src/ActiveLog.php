<?php
/**
 * @author Dmitriy Yurchenko <evildev@evildev.ru>
 */

namespace evildev\activerecord;

use evildev\activerecord\traits\ActiveRecord\DateTimeTrait;
use Yii;
use yii\behaviors\AttributeBehavior;
use yii\db\ActiveQuery;

/**
 * Свойства модели:
 * @property int $id
 * @property int $user_id
 * @property int $model_id
 * @property object $data_before
 * @property object $data_after
 * @property string $date_create
 *
 * Магические свойства:
 * @property ActiveRecord $model
 */
class ActiveLog extends ActiveRecord
{
    use DateTimeTrait;

    /**
     * @var string Класс модели в которую производится запись логов.
     */
    protected string $modelClass;

    /**
     * @inheritDoc
     */
    public function __construct($config = [])
    {
        //  date_update нам не нужен.
        parent::__construct(array_merge($config, [
            'dateUpdateAttribute' => null,
        ]));
    }

    /**
     * @inheritdoc
     */
    public function behaviors(): array
    {
        return array_merge(parent::behaviors(), [
            [
                'class' => AttributeBehavior::class,
                'attributes' => [
                    ActiveRecord::EVENT_BEFORE_INSERT => 'user_id',
                ],
                'value' => Yii::$app->user->id ?? -1,
            ],
        ]);
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'data_before' => 'До',
            'data_after' => 'После',
            'date_create' => 'Создано',
            'user_id' => 'Пользователь',
        ];
    }

    /**
     * @return ActiveQuery
     */
    public function getModel(): ActiveQuery
    {
        return $this->hasOne($this->modelClass, ['id' => 'model_id']);
    }
}