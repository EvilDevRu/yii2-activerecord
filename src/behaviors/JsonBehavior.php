<?php
/**
 * @author Dmitriy Yurchenko <evildev@evildev.ru>
 */

namespace evildev\activerecord\behaviors;

use evildev\activerecord\ActiveRecord;
use Exception;
use yii\base\Behavior;
use yii\base\Event;

/**
 * Поведение сохраняющее и восстанавливающее json данные т.к. напрямую с ними работать не получится.
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
            try {
                $model->$arrayColumn = $model->getAttribute($jsonColumn);
            } catch (Exception $e) {
                $model->$arrayColumn = null;
            }
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
            $model->setAttribute($jsonColumn, $model->$arrayColumn);
        }
    }
}