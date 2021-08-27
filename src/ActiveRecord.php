<?php
/**
 * @author Dmitriy Yurchenko <evildev@evildev.ru>
 */

namespace evildev\activerecord;

use DateTime;
use DateTimeZone;
use Exception;
use Yii;
use yii\db\ActiveRecord as BaseActiveRecord;
use yii\db\Expression;
use yii\db\Schema;
use yii\helpers\ArrayHelper;
use yii\helpers\StringHelper;
use yii\web\ForbiddenHttpException;

/**
 * Свойства модели:
 *
 * Магические свойства:
 * @property string $oneError Вернет единствунную одинку из всего списка.
 * @property string $permissionPostfix Постфикс прав пользователя.
 * @property bool $isCanDelete Если можно удалить модель.
 * @property bool $isCanSoftDelete Если можно мягко удалить модель.
 * @property bool $isCanUpdate Если можно отредактировать модель.
 */
class ActiveRecord extends BaseActiveRecord
{
    /**
     * @return string[]
     */
    protected function errorMessages(): array
    {
        return [
            'delete' => 'Вам не разрешено удалять.',
            'softdelete' => 'Вам не разрешено удалять',
        ];
    }

    /**
     * Вернет массив связей с другими моделями.
     * Заполняется вручную и нужен для проверки жесткого удаления модели.
     * Оставьте пустым, если модель можно удалить без учета связных данных.
     * @return array
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
     * @param string $attribute название аттрибута с датой.
     * @param string $format формат возвращаемой даты Y.m.d.
     * @return string
     */
    public function dateFormat(string $attribute, string $format = 'php:Y.m.d H:i:s'): string
    {
        return Yii::$app->formatter->asDatetime($this->getUTC($attribute), $format);
    }

    /**
     * Вернет дату в UTC.
     * @param string $attribute
     * @return string
     */
    public function getUTC(string $attribute): string
    {
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

        //  TODO: Сохранение логов.

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
        return array_merge(parent::rules(), [
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
     * @return bool
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
        foreach ($this->relationList() as $name) {
            if ($this->$name) {
                return false;
            }
        }

        return true;
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
            && !$this->isNewRecord;
    }

    /**
     * Вернет true, если текущему пользователю разрещено действие $permission с учетом текущего контроллера.
     * @param string $permission
     * @return bool
     */
    public function userCan(string $permission): bool
    {
        return Yii::$app->user->can($permission . $this->permissionPostfix);
    }

    /**
     * Вернет true, если модель можно удалить.
     * @return bool
     */
    public function getIsCanUpdate(): bool
    {
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
        $array = array_map('strtolower', reset($array));

        return StringHelper::mb_ucfirst(implode('-', $array));
    }
}