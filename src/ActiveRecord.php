<?php
/**
 * @author Dmitriy Yurchenko <evildev@evildev.ru>
 */

namespace evildev\activerecord;

use DateTime;
use DateTimeZone;
use ReflectionClass;
use Yii;
use yii\base\Exception;
use yii\db\ActiveRecord as BaseActiveRecord;
use yii\db\Expression;
use yii\db\Schema;
use yii\helpers\ArrayHelper;
use yii\helpers\StringHelper;
use yii\web\ForbiddenHttpException;
use yii\web\HttpException;

/**
 * Свойства модели:
 *
 * Магические свойства:
 * @property string $oneError Вернет единственную ошибку из всего списка.
 * @property string $permissionPostfix Постфикс прав пользователя.
 * @property bool $relationListDeleteIgnore Флаг игнорирования зависимостей для isCanDelete.
 * @property bool $isCanDelete Если можно удалить модель.
 * @property bool $isCanSoftDelete Если можно мягко удалить модель.
 * @property bool $isCanUpdate Если можно отредактировать модель.
 */
class ActiveRecord extends BaseActiveRecord
{
    protected string $modelLogClass = '';

    /**
     * @return array<string, string>
     */
    protected function errorMessages(): array
    {
        return [
            'create' => 'Вам не разрешено создание.',
            'update' => 'Вам не разрешено редактирование.',
            'delete' => 'Вам не разрешено удалять.',
            'softdelete' => 'Вам не разрешено удалять.',
            'restore' => 'Вам не разрешено восстанавливать.',
        ];
    }

    /**
     * Вернет массив связей с другими моделями.
     * Заполняется вручную и нужен для проверки жесткого удаления модели.
     * Например, если есть метод getAcceptance, то заполнить надо ['acceptance'].
     * Оставьте пустым, если модель можно удалить без учета связных данных.
     * @return array<string>
     */
    public function relationList(): array
    {
        return [];
    }

    /**
     * Вернет названия колонок по их типу.
     * @param string|array $type
     * @return array
     */
    static function getColumnsByType(string|array $type): array
    {
        $type = is_array($type) ? $type : [$type];
        $attributes = [];
        foreach (static::getTableSchema()->columns as $name => $schema) {
            if (in_array($schema->type, $type)) {
                $attributes[$name] = $schema;
            }
        }

        return $attributes;
    }

    /**
     * Вернет список моделей в формате [id => name].
     * @return array
     */
    public static function listAll(): array
    {
        return ArrayHelper::map(static::find()->all(), 'id', 'name');
    }

    /**
     * Вернет отформатированную дату с учетом часового пояса.
     * @param string $attribute Название аттрибута с датой.
     * @param string $format Формат возвращаемой даты Y.m.d.
     * @return string
     */
    public function dateFormat(string $attribute, string $format = 'php:Y.m.d H:i:s'): ?string
    {
        return $this->getUTC($attribute)
            ? Yii::$app->formatter->asDatetime($this->getUTC($attribute), $format)
            : null;
    }

    /**
     * Вернет дату в UTC.
     * @param string $attribute
     * @return string
     */
    public function getUTC(string $attribute): string
    {
        if (!$this->$attribute) {
            return '';
        }

        return $this->convertToServerDate($this->$attribute,
                Yii::$app->formatter->timeZone,
                Yii::$app->formatter->defaultTimeZone) ?? '';
    }

    /**
     * @inheritdoc
     */
    public function afterFind()
    {
        foreach ($this->dateTimeAttributes() as $attribute) {
            if (!empty($this->$attribute)) {
                $this->$attribute = $this->convertToServerDate($this->$attribute ?? '',
                    Yii::$app->formatter->defaultTimeZone,
                    Yii::$app->formatter->timeZone);
            }
        }

        parent::afterFind();
    }

    /**
     * Вернет массив названий аттрибутов даты и времени, которые будут скорректированы относительно часового пояса пользователя.
     * В базе дата и время хранятся в UTC.
     * @return array
     */
    public function dateTimeAttributes(): array
    {
        return array_keys(static::getColumnsByType(Schema::TYPE_DATETIME));
    }

    /**
     * @param bool $insert
     * @param array $changedAttributes
     */
    public function afterSave($insert, $changedAttributes)
    {
        $this->refresh();

        foreach ($this->dateTimeAttributes() as $attribute) {
            $this->$attribute = $this->convertToServerDate($this->$attribute ?? '',
                Yii::$app->formatter->defaultTimeZone,
                Yii::$app->formatter->timeZone);
        }

        //  Сохранение логов.
        if (class_exists($this->modelLogClass)) {
            $before = [];
            $after = [];

            /** @var ActiveLog $log */
            $log = new ($this->modelLogClass)();
            $log->model_id = $this->id;
            foreach ($changedAttributes as $name => $value) {
                if ($this->$name != $value && !in_array($name, $this->dateTimeAttributes())) {
                    $before[$name] = $value;
                    $after[$name] = $this->getAttribute($name);
                }
            }

            if (!empty($before) && !empty($after)) {
                $log->data_before = (object)$before;
                $log->data_after = (object)$after;

                if (!$log->save()) {
                    throw new \yii\db\Exception('Не удалось сохранить лог. ' . $log->oneError);
                }
            }
        }

        parent::afterSave($insert, $changedAttributes);
    }

    /**
     * @param bool $insert
     * @return bool
     */
    public function beforeSave($insert): bool
    {
        foreach ($this->dateTimeAttributes() as $attribute) {
            if ($this->$attribute instanceof Expression) {
                continue;
            }

            $this->$attribute = $this->convertToServerDate($this->$attribute ?? '',
                Yii::$app->formatter->timeZone,
                Yii::$app->formatter->defaultTimeZone);
        }

        return parent::beforeSave($insert);
    }

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        $stringColumns = array_keys(static::getColumnsByType([Schema::TYPE_STRING, Schema::TYPE_TEXT]));
        $dateTimeColumns = array_keys(static::getColumnsByType(Schema::TYPE_DATETIME));
        $dateColumns = array_keys(static::getColumnsByType(Schema::TYPE_DATE));

        //  Правила из Trait.
        $class = new ReflectionClass(get_called_class());
        $traitRules = [];
        foreach ($class->getMethods() as $method) {
            if (str_contains($method->name, 'RulesTrait')) {
                $traitRules = array_merge($traitRules, $this->{$method->name}());
            }
        }

        return array_merge(parent::rules(), $traitRules, [
            [$this->xssAttributes(), 'filter', 'filter' => '\yii\helpers\HtmlPurifier::process'],
            [$stringColumns, 'filter', 'filter' => 'trim', 'skipOnArray' => true],
            [
                $dateTimeColumns,
                'datetime',
                'format' => 'php:Y-m-d H:i:s',
                'when' => function (self $model, string $attribute) {
                    return !($model->$attribute instanceof Expression);
                }
            ],
            [
                $dateColumns,
                'datetime',
                'format' => 'php:Y-m-d',
                'when' => function (self $model, string $attribute) {
                    return !($model->$attribute instanceof Expression);
                }
            ],
        ]);
    }

    /**
     * @inheritDoc
     */
    public function behaviors(): array
    {
        //  Поведения из Trait.
        $class = new ReflectionClass(get_called_class());
        $traitBehaviors = [];
        foreach ($class->getMethods() as $method) {
            if (str_contains($method->name, 'BehaviorsTrait')) {
                $traitBehaviors = array_merge($traitBehaviors, $this->{$method->name}());
            }
        }

        return array_merge(parent::behaviors(), $traitBehaviors);
    }

    /**
     * Вернет массив названий аттрибутов строк и текста для предотвращения xss атак.
     * @return array
     */
    public function xssAttributes(): array
    {
        return array_keys(static::getColumnsByType([Schema::TYPE_STRING, Schema::TYPE_TEXT]));
    }

    /**
     * Вернет первую и единственную ошибку.
     * @return string
     */
    public function getOneError(): string
    {
        $_errors = $this->firstErrors;
        return reset($_errors);
    }

    /**
     * Вернет true, если модель можно удалить.
     * @param bool $isMute
     * @return bool
     * @throws Exception
     * @throws ForbiddenHttpException
     */
    public function getIsCanDelete(bool $isMute = false): bool
    {
        if (!$this->userCan('delete') && !$isMute) {
            throw new ForbiddenHttpException($this->errorMessages()['delete']);
        }

        if (!$this->userCan('delete') && !$this->isNewRecord) {
            return false;
        }

        //  Проверяем связные данные если они есть.
        if (!$this->relationListDeleteIgnore) {
            foreach ($this->relationList() as $name) {
                if (!$this->hasProperty($name)) {
                    throw new Exception('Не верно указана связь ' . $name . ' в модели.');
                }

                if ($this->$name) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Если вернет true, то модель можно удалить игнорируя relationList().
     * В этом случае будут удалены все связные данные, если они указаны.
     * @return bool
     */
    public function getRelationListDeleteIgnore(): bool
    {
        return false;
    }

    /**
     * Вернет true, если модель можно создать.
     * @param bool $isMute
     * @return bool
     * @throws ForbiddenHttpException
     */
    public function getIsCanCreate(bool $isMute = false): bool
    {
        if (!$this->userCan('create') && !$isMute) {
            throw new ForbiddenHttpException($this->errorMessages()['create']);
        }

        return $this->userCan('create')
            && $this->isNewRecord;
    }

    /**
     * @inheritDoc
     */
    public function beforeDelete()
    {
        if (!$this->isCanDelete || !parent::beforeDelete()) {
            return false;
        }

        //  Удаляем зависимости.
        Yii::$app->transaction->execute(function () {
            foreach (static::relationList() as $relation) {
                foreach ($this->$relation as $item) {
                    if (!$item->delete()) {
                        throw new HttpException(500,
                            'Не удалось удалить зависимые данные.');
                    }
                }
            }
        }, function (\Exception $e) {
            $this->addError($e->getMessage());
        });

        return !$this->hasErrors();
    }

    /**
     * Вернет true, если текущему пользователю разрешено действие $permission с учетом текущего контроллера.
     * @param string $permission
     * @return bool
     */
    public function userCan(string $permission): bool
    {
        return Yii::$app->user->can($permission . $this->permissionPostfix);
    }

    /**
     * Вернет true, если модель можно отредактировать.
     * @return bool
     */
    public function getIsCanUpdate(bool $isMute = false): bool
    {
        if (!$this->userCan('update') && !$isMute) {
            throw new ForbiddenHttpException($this->errorMessages()['update']);
        }

        return $this->userCan('update') && !$this->isNewRecord;
    }

    /**
     * @param string $date
     * @param string $userTimeZone
     * @param string $serverTimeZone
     * @return string
     */
    protected function convertToServerDate(string $date, string $userTimeZone, string $serverTimeZone): string
    {
        if (empty($date)) {
            return '';
        }

        try {
            $dateTime = new DateTime($date, new DateTimeZone($userTimeZone));
            $dateTime->setTimezone(new DateTimeZone($serverTimeZone));
            return $dateTime->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            return '';
        }
    }

    /**
     * Вернет постфикс контроллера для разграничения прав. Смотри метод userCan.
     * @return string
     */
    protected function getPermissionPostfix(): string
    {
        $array = explode('\\', static::class);
        preg_match_all('/((?:^|[A-Z])[a-z]+)/', end($array), $array);
        $array = array_map('strtolower', (array)reset($array));

        return StringHelper::mb_ucfirst(implode('-', $array));
    }

    /**
     * Попытается найти модель по $attributes, если не найдет, то создаст новую с аттрибутами $attributes.
     * @param array $attributes
     * @return static
     */
    public static function findOrCreate(array $attributes): self
    {
        $model = static::find()->where($attributes)->one() ?? new static();
        $model->setAttributes($attributes);
        $model->save();
        return $model;
    }
}