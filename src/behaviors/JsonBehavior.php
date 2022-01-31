<?php
/**
 * @author Dmitriy Yurchenko <evildev@evildev.ru>
 */

namespace evildev\activerecord\behaviors;

use evildev\activerecord\ActiveRecord;
use yii\base\Behavior;
use yii\base\Event;
use yii\helpers\Json;

/**
 * Поведение преобразующее json в массив и обратно.
 */
class JsonBehavior extends Behavior
{
    /**
     * @var array Данные в формате (аттрибут массив => аттрибут json).
     */
    public array $attributes = [];

    /**
     * @return string[]
     */
    public function events(): array
    {
        return [
            ActiveRecord::EVENT_AFTER_FIND => 'onAfterFind',
            ActiveRecord::EVENT_BEFORE_INSERT => 'onBeforeSave',
            ActiveRecord::EVENT_BEFORE_UPDATE => 'onBeforeSave',
        ];
    }

    /**
     * @param Event $event
     * @return void
     */
    public function onAfterFind(Event $event): void
    {
        /** @var ActiveRecord $model */
        $model = $event->sender;
        foreach ($this->attributes as $arrayColumn => $jsonColumn) {
            $model->$arrayColumn = Json::decode($model->getAttribute($jsonColumn)) ?? [];
        }
    }

    /**
     * @param Event $event
     * @return void
     */
    public function onBeforeSave(Event $event): void
    {
        /** @var ActiveRecord $model */
        $model = $event->sender;
        foreach ($this->attributes as $arrayColumn => $jsonColumn) {
            $model->setAttribute($jsonColumn, Json::encode($model->$arrayColumn));
        }
    }
}